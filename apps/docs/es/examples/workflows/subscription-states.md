# Workflow de suscripción (SaaS billing)

> Ejemplo de state machine para suscripciones SaaS, demostrando transiciones disparadas por webhooks de proveedor de pagos (Stripe), side-effects en cache/quotas y uso intensivo de `metadata` para idempotencia.

## Resumen

En SaaS billing, la state machine de una suscripción es el punto donde el dominio del producto encuentra al sistema de pagos externo. **La mayoría de transiciones no son iniciadas por un humano** — Stripe (o Mercado Pago, o Adyen) envía webhooks diciendo "payment succeeded", "attempt failed", "customer canceled in the portal". Esto cambia cómo pensamos la autorización: el "actor" típico es el sistema mismo, y lo que necesitamos garantizar es (a) **idempotencia** — el mismo evento no puede procesarse dos veces; (b) **side-effects consistentes** — cuando la suscripción entra en `PastDue`, el cache de feature flags de ese tenant debe ser invalidado **antes** del próximo request HTTP del usuario; y (c) **trazabilidad completa** — los auditores necesitan ver "qué webhook de Stripe causó esta transición" para reconciliar con extractos.

El workflow es `Trialing → Active → PastDue → Canceled`, con el branch `Active → Paused` (pausa manual del cliente, retomada vía `Paused → Active`). `Trialing` es el estado inicial tras el signup; `Active` significa pago al día; `PastDue` es facturación fallida (pero todavía dentro de la ventana de retry); `Canceled` es terminal (cualquier futuro downgrade crea una nueva suscripción, no reactiva esta). La transición `PastDueToActive` (recovery — Stripe consigue cobrar en el retry) es la más sensible: necesita restaurar quotas, reactivar features, y NO debe disparar un email de "bienvenida" porque el cliente ya es cliente.

La elección central de diseño en este ejemplo es **usar el `metadata` de la transición como contrato de auditoría**. Cada transición disparada por webhook lleva como mínimo `subscription_id` (id del proveedor), `webhook_event_id` (id único de Stripe), `event_type` (`invoice.payment_succeeded`, `invoice.payment_failed`, etc.) y `processed_at`. Antes de procesar, el controlador hace `StateTransition::where('metadata->webhook_event_id', $eventId)->exists()` para rechazar duplicados. Este patrón reemplaza las idempotency keys de Redis y es más auditable.

## Diagrama de estados

```mermaid
graph LR
    Trialing -->|TrialingToActive<br/>Stripe webhook| Active
    Active -->|ActiveToPastDue<br/>payment failed| PastDue
    PastDue -->|PastDueToActive<br/>recovery| Active
    PastDue -->|ActiveToCanceled<br/>retry exhausted| Canceled
    Active -->|ActiveToCanceled<br/>user opt-out| Canceled
    Active -->|ActiveToPaused<br/>user request| Paused
    Paused -->|PausedToActive<br/>user resume| Active
    Trialing -->|ActiveToCanceled<br/>trial declined| Canceled
```

`ActiveToCanceled` es alcanzable desde `Trialing`, `Active` y `PastDue` — implementada como una única clase de transición con `from(): [Trialing, Active, PastDue]`. Los usuarios pueden cancelar inmediatamente (transición directa) o al final del período (la UI agenda un job para `now()->addDays($daysRemaining)` que dispara la transición). Ambos paths pasan por la misma clase — lo que cambia es el `context`.

## Modelo Eloquent

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\SubscriptionState;
use App\Workflows\Subscriptions\Transitions;
use Arqel\Workflow\Concerns\HasWorkflow;
use Arqel\Workflow\WorkflowDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Subscription extends Model
{
    use HasWorkflow;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'subscription_state',
        'stripe_subscription_id',
        'current_period_end',
        'trial_ends_at',
        'canceled_at',
        'paused_until',
    ];

    protected $casts = [
        'subscription_state' => SubscriptionState::class,
        'current_period_end' => 'datetime',
        'trial_ends_at'      => 'datetime',
        'canceled_at'        => 'datetime',
        'paused_until'       => 'datetime',
    ];

    public function arqelWorkflow(): WorkflowDefinition
    {
        return WorkflowDefinition::make('subscription_state')
            ->states([
                SubscriptionState\Trialing::class => ['label' => 'Trialing',         'color' => 'info',        'icon' => 'gift'],
                SubscriptionState\Active::class   => ['label' => 'Active',           'color' => 'success',     'icon' => 'check-circle'],
                SubscriptionState\PastDue::class  => ['label' => 'Payment past due', 'color' => 'warning',     'icon' => 'alert-triangle'],
                SubscriptionState\Paused::class   => ['label' => 'Paused',           'color' => 'secondary',   'icon' => 'pause-circle'],
                SubscriptionState\Canceled::class => ['label' => 'Canceled',         'color' => 'destructive', 'icon' => 'x-octagon'],
            ])
            ->transitions([
                Transitions\TrialingToActive::class,
                Transitions\ActiveToPastDue::class,
                Transitions\PastDueToActive::class,
                Transitions\ActiveToCanceled::class,
                Transitions\ActiveToPaused::class,
                Transitions\PausedToActive::class,
            ]);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
```

## Resource

```php
<?php

declare(strict_types=1);

namespace App\Arqel\Resources;

use App\Models\Subscription;
use Arqel\Core\Resource;
use Arqel\Fields\DateTime;
use Arqel\Fields\Text;
use Arqel\Workflow\Fields\StateTransitionField;

final class SubscriptionResource extends Resource
{
    protected static string $model = Subscription::class;

    protected static ?string $navigationGroup = 'Billing';

    public function fields(): array
    {
        return [
            Text::make('tenant.name')->label('Tenant')->searchable(),
            Text::make('plan.name')->label('Plan'),
            Text::make('stripe_subscription_id')->label('Stripe ID')->copyable(),

            StateTransitionField::make('subscription_state')
                ->label('Status')
                ->showDescription()
                ->showHistory(),

            DateTime::make('current_period_end')->label('Current period end'),
            DateTime::make('trial_ends_at')->label('Trial ends')->placeholder('—'),
            DateTime::make('canceled_at')->label('Canceled at')->placeholder('—'),
        ];
    }
}
```

## Clase de transición con authorizeFor — solo webhook

```php
<?php

declare(strict_types=1);

namespace App\Workflows\Subscriptions\Transitions;

use App\Models\Subscription;
use App\Models\SubscriptionState;
use Illuminate\Contracts\Auth\Authenticatable;

final class TrialingToActive
{
    public function __construct(
        private readonly Subscription $subscription,
    ) {}

    /** @return list<class-string> */
    public static function from(): array
    {
        return [SubscriptionState\Trialing::class];
    }

    public static function to(): string
    {
        return SubscriptionState\Active::class;
    }

    /**
     * This transition is NEVER initiated by humans — only by the Stripe webhook handler.
     * We deny it for any authenticated user to hide the UI button; the webhook
     * controller calls transitionTo() outside the Auth context, which bypasses this check
     * (TransitionAuthorizer accepts a null user as the "system actor" when authorizeFor authorizes it).
     */
    public static function authorizeFor(?Authenticatable $user, mixed $record): bool
    {
        return $user === null; // system only
    }

    public function handle(): Subscription
    {
        $this->subscription->subscription_state = SubscriptionState\Active::class;
        $this->subscription->trial_ends_at = null;
        $this->subscription->save();

        return $this->subscription;
    }
}
```

## Controlador de webhook (Stripe)

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionState;
use Arqel\Workflow\Models\StateTransition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class StripeWebhookController
{
    public function __invoke(Request $request): \Illuminate\Http\Response
    {
        $payload = $this->verifyAndParse($request);
        $eventId = $payload['id'];

        // Idempotency: have we already processed this event?
        if (StateTransition::where('metadata->webhook_event_id', $eventId)->exists()) {
            return response('already_processed', 200);
        }

        $subscription = Subscription::where('stripe_subscription_id', $payload['data']['object']['subscription'])
            ->firstOrFail();

        $context = [
            'subscription_id'  => $payload['data']['object']['subscription'],
            'webhook_event_id' => $eventId,
            'event_type'       => $payload['type'],
            'processed_at'     => now()->toIso8601String(),
        ];

        DB::transaction(function () use ($subscription, $payload, $context): void {
            match ($payload['type']) {
                'invoice.payment_succeeded' => $subscription->subscription_state instanceof SubscriptionState\Trialing
                    ? $subscription->transitionTo(SubscriptionState\Active::class, $context)
                    : ($subscription->subscription_state instanceof SubscriptionState\PastDue
                        ? $subscription->transitionTo(SubscriptionState\Active::class, $context + ['recovery' => true])
                        : null),

                'invoice.payment_failed' => $subscription->transitionTo(SubscriptionState\PastDue::class, $context + [
                    'attempt_count' => $payload['data']['object']['attempt_count'] ?? 1,
                ]),

                'customer.subscription.deleted' => $subscription->transitionTo(SubscriptionState\Canceled::class, $context),

                default => null,
            };
        });

        return response('ok', 200);
    }

    /** @return array<string,mixed> */
    private function verifyAndParse(Request $request): array
    {
        // Stripe signature verification (omitted for brevity)
        return $request->json()->all();
    }
}
```

Nota tres cosas: (1) **idempotencia vía metadata** — la query `where('metadata->webhook_event_id', ...)` aprovecha el índice JSON de Postgres/MySQL; (2) **transaction wrapping** — la transición y los side-effects del listener corren en un único commit; (3) el `match` decide la transición en base al estado actual + tipo de evento, pero la complejidad está contenida en el controlador.

## Filtro por estado en la Table

```php
use App\Models\Subscription;
use App\Models\SubscriptionState;
use Arqel\Workflow\Filters\StateFilter;

public function table(): Table
{
    return Table::make()
        ->columns([
            TextColumn::make('tenant.name'),
            TextColumn::make('plan.name'),
            BadgeColumn::make('subscription_state')->colorsFromWorkflow(Subscription::class),
            DateTimeColumn::make('current_period_end'),
        ])
        ->filters([
            StateFilter::make('subscription_state', Subscription::class)
                ->label('Status'),
        ])
        ->defaultFilters([
            'subscription_state' => [
                SubscriptionState\PastDue::class,
                SubscriptionState\Active::class,
            ],
        ])
        ->actions([
            // Billing-ops actions backed by StateFilter filters
            Action::make('retry_failed_payments')
                ->visible(fn () => request('filter.subscription_state') === SubscriptionState\PastDue::class)
                ->action(fn () => RetryFailedPaymentsJob::dispatch()),
        ]);
}
```

## Listener — invalidar cache + ajustar quotas

```php
<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Subscription;
use App\Models\SubscriptionState;
use App\Services\FeatureFlagCache;
use App\Services\QuotaManager;
use Arqel\Workflow\Events\StateTransitioned;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

final class ApplySubscriptionStateSideEffects implements ShouldQueue
{
    public function __construct(
        private readonly FeatureFlagCache $flags,
        private readonly QuotaManager $quotas,
    ) {}

    public function handle(StateTransitioned $event): void
    {
        if (! $event->record instanceof Subscription) {
            return;
        }

        $subscription = $event->record;

        // 1. Always invalidate the tenant's feature flag cache — any state change
        //    can alter what they can/can't use.
        $this->flags->invalidateForTenant($subscription->tenant_id);

        // 2. Adjust quotas according to the destination state.
        match ($event->to) {
            SubscriptionState\Active::class   => $this->quotas->restorePlanQuotas($subscription),
            SubscriptionState\PastDue::class  => $this->quotas->applyGracePeriodLimits($subscription),
            SubscriptionState\Canceled::class => $this->quotas->revokeAll($subscription),
            SubscriptionState\Paused::class   => $this->quotas->freezeUsage($subscription),
            default => null,
        };

        // 3. Retention email when entering PastDue (not Canceled — too late by then).
        if ($event->to === SubscriptionState\PastDue::class && $subscription->tenant?->billingContact !== null) {
            Mail::to($subscription->tenant->billingContact)
                ->send(new \App\Mail\PaymentFailedRetention(
                    subscription: $subscription,
                    attemptCount: (int) ($event->context['attempt_count'] ?? 1),
                    webhookEventId: $event->context['webhook_event_id'] ?? null,
                ));
        }

        // 4. Recovery (PastDue → Active): do NOT send a welcome email.
        //    Log it for the recovery rate metric.
        if ($event->from === SubscriptionState\PastDue::class && $event->to === SubscriptionState\Active::class) {
            \App\Metrics\BillingMetrics::recordRecovery(
                subscriptionId: $subscription->id,
                webhookEventId: $event->context['webhook_event_id'] ?? null,
            );
        }
    }
}
```

Puntos a destacar:

- El listener es `ShouldQueue` — los side-effects pueden ser lentos (invalidación de cache distribuido, envío de email), y los retrasos no deben bloquear el ACK del webhook a Stripe.
- La invalidación del cache de feature flags ocurre **siempre**, independientemente del estado destino — es más barato invalidar que tratar de deducir cuándo exactamente cambió un flag.
- `event->context['webhook_event_id']` se propaga a métricas y emails — permitiéndote reconciliar todo con el dashboard de Stripe después.

## Metadata en el history — ejemplo práctico

Tras una transición disparada por webhook, la entrada en `arqel_state_transitions` se ve así:

```json
{
  "id": 1042,
  "model_type": "App\\Models\\Subscription",
  "model_id": 17,
  "from_state": "App\\Models\\SubscriptionState\\Trialing",
  "to_state": "App\\Models\\SubscriptionState\\Active",
  "transitioned_by_user_id": null,
  "metadata": {
    "subscription_id": "sub_1NxYzABC123",
    "webhook_event_id": "evt_1NxYzABC123",
    "event_type": "invoice.payment_succeeded",
    "processed_at": "2026-04-30T14:32:01+00:00"
  },
  "created_at": "2026-04-30 14:32:01"
}
```

Un auditor buscando `evt_1NxYzABC123` en el Stripe Dashboard puede emparejarlo directamente con esta fila, y vice versa. Para `recovery: true`, la columna distingue un pago exitoso normal de una recuperación de delinquencia — una métrica clave en SaaS.

## Resumen de decisiones

- **Sistema como actor**: `authorizeFor` devuelve `true` cuando el usuario es `null` para los webhooks; los humanos solo ven botones para `Cancel` y `Pause`/`Resume`.
- **Idempotencia vía `metadata->webhook_event_id`**: reemplaza idempotency keys de Redis; es más auditable.
- **Side-effects en un listener `ShouldQueue`**: ACK rápido del webhook a Stripe; el trabajo real asíncrono.
- **`recovery: true` en el context**: distingue retry-success del pago inicial — importante para emails y métricas.
- **Sin retorno desde `Canceled`**: estado terminal. La reactivación crea una nueva suscripción.

# SKILL.md — arqel/workflow

> Contexto canônico para AI agents trabalhando no pacote `arqel/workflow`.

## Purpose

`arqel/workflow` entrega o sistema de **state machines** do Arqel: define os estados possíveis de um model Eloquent, suas metadatas de UI (label/color/icon), a lista de transições válidas entre eles, eventos de auditoria, autorização central e histórico append-only. Cobre RF-IN-06.

A escolha é **integrar quando útil, sem amarrar**: a integração canônica é com [`spatie/laravel-model-states`](https://spatie.be/docs/laravel-model-states) (state classes + transition classes), mas o pacote é totalmente *duck-typed* — funciona também com PHP enums, slugs string ou tokens custom. `spatie/laravel-model-states` fica em `suggest:` no composer.json, nunca em `require`.

## Status

### Entregue (WF-001 .. WF-009)

**Definition + Trait (WF-001/002).** `WorkflowDefinition` (final) é o builder fluent: `make(string $field)` valida campo não-vazio; `states(array)` aceita `array<string, {label?, color?, icon?}>` com chaves tipicamente `class-string<State>` (ou enum value/slug arbitrário); `transitions(list<class-string>)` registra a lista de transições. Labels ausentes derivam do PascalCase final do FQCN (`OrderState\PendingPayment` → `Pending Payment`); `color`/`icon` ausentes caem em `'secondary'`/`'circle'`. `getStateMetadata()`, `getStates()`, `getTransitions()`, e `toArray()` (`{field, states, transitions}`) servem o React. `HasWorkflow` é o trait consumido por user-land Eloquent models — declara `arqelWorkflow(): WorkflowDefinition` e expõe `getCurrentStateMetadata()`, `getAvailableTransitions()` (filtra por `from()` estático ou trata como sempre-aberta quando ausente), e `transitionTo()`. A resolução de chave (`resolveStateKey`) é polimórfica: objetos → `::class`, `BackedEnum` → `value`, string → ela mesma, resto → `null`.

**Service Provider + Config.** `WorkflowServiceProvider` é auto-discovered via `extra.laravel.providers`, boota via `Spatie\LaravelPackageTools\PackageServiceProvider` (`name('arqel-workflow')`, `hasConfigFile('arqel-workflow')` + `hasMigrations(...)`), e registra o listener canônico de história. `config/arqel-workflow.php` expõe blocos `audit`, `authorization`, `history`, `user_model`.

**Eventos (WF-004).** `Events\StateTransitioned` (final) é disparado *após* uma transição bem-sucedida. Carrega `record: Model`, `from: string`, `to: string`, `userId: ?int`, `context: array<string,mixed>`. Implementa `Dispatchable` + `SerializesModels`. **Não** implementa `ShouldBroadcast` por design — broadcasting é opt-in via listener dedicado. `HasWorkflow::transitionTo($newState, array $context = [])` captura state atual, delega à API spatie quando o state object atual tem `transitionTo()`, ou faz attribute assignment + save direto, e em seguida dispara o evento (capturando `Auth::id()`). Suprime quando `arqel-workflow.audit.enabled === false` ou `audit.log_via !== 'event'` — útil para migrations/seeders. O trait opcional `Concerns\RecordsStateTransition` permite que classes spatie `Transition` despachem o evento canônico do próprio `handle()`.

**Authorization (WF-006).** `Authorization\TransitionAuthorizer` (final readonly) decide `(transitionClass, ?Authenticatable $user, mixed $record)` em 3 camadas: (1) `authorizeFor(?Authenticatable, mixed): bool` na transition (estático ou instância — preferido); (2) Gate `transition-{fromSlug}-to-{toSlug}` quando registrada; (3) **deny by default** — flag opt-out via `arqel-workflow.authorization.deny_when_undefined => false` (preserva legado WF-003). Exceções no `authorizeFor` degradam para `false` (fail closed). O helper `slugifyState(string)` gera kebab-case do segmento final do FQCN, removendo sufixo `State` (`'PendingPayment'` → `'pending-payment'`, `'PaidState'` → `'paid'`, `''`/`'*'` → `'*'`).

**Histórico append-only (WF-007).** `Models\StateTransition` (final, `$timestamps = false`, `metadata` cast `array`, `created_at` cast `datetime`) persiste cada transição. Migration `2026_05_01_000000_create_arqel_state_transitions_table` cria `arqel_state_transitions` com `morphs('model')`, `from_state` (nullable), `to_state`, `transitioned_by_user_id` (indexed), `metadata` (JSON), `created_at` com `useCurrent()` (sem `updated_at` — append-only). `Listeners\PersistStateTransitionToHistory` (auto-registado pelo provider) escuta `StateTransitioned` e grava — skip silencioso quando `arqel-workflow.history.enabled === false`; captura `Throwable` para não bloquear a transição do domínio. `HasWorkflow::stateTransitions(): MorphMany` ordena por `created_at desc, id desc`. `StateTransition::user()` retorna `?BelongsTo` defensivo (lê `arqel-workflow.user_model`, default `App\\Models\\User`; `null` quando ausente). `Fields\StateTransitionField::resolveHistory()` lê o histórico real filtrado por `(model_type, model_id)` com limit configurável (`arqel-workflow.history.limit`, default 50) — best-effort com `Throwable` capturado.

**Field React-bound (WF-005/006).** `Fields\StateTransitionField` (extends `Arqel\Fields\Field`) serializa `currentState: {name, label, color, icon}|null`, `transitions: list<{from, to, label, authorized}>` (delegando ao `TransitionAuthorizer`) e `history` (vinda de `StateTransition`). Métodos fluentes: `showDescription()`, `showHistory()`, `transitionsAttribute()`, `record()`. O componente React `arqel/workflow/StateTransition` (slice C29) consome estes props.

**Filter standalone (WF-008).** `Filters\StateFilter` (final readonly) extrai automaticamente as options do `WorkflowDefinition` de um model com `HasWorkflow`. Construtor valida classe + trait (lança `InvalidArgumentException`). `make($field, $modelClass)`, `toArray(): {field, type: 'state', label, options}`, `optionsArray(): array<string, {value, label, color, icon}>`, `apply(Builder, mixed)` — string → `where`, array → `whereIn` (filtra valores não-string/vazios), `null`/empty → no-op. `Filters\StateFilterFactory::forResource($modelClass, ?$field)` resolve o field via `arqelWorkflow()->getField()` quando omitido. **Sem hard-dep em `arqel/table`** por design — o user-land pluga `StateFilter::toArray()` em `Table::filters([...])`.

**Cobertura de testes (WF-009).** 67 testes Pest 3 (Orchestra Testbench + sqlite in-memory): `WorkflowDefinitionTest` (7), `HasWorkflowTest` (6), `StateTransitionedEventTest` (8), `StateTransitionHistoryTest` (7), `WorkflowServiceProviderTest` (2), `StateTransitionFieldTest` (~10), `TransitionAuthorizerTest` (10), `StateFilterTest` (12), e o novo `Unit/Coverage/CoverageGapsTest` (9 cobrindo edge-cases de `guessLabel`, ordem de `getTransitions`, replace vs merge em `states()`, `slugifyState` para wildcard/digits/bare strings, `apply()` com array misto, defaults em `optionsArray`, round-trip de cast `metadata`, MorphTo `model()`, e ordem em `resolveAvailableTransitions`). PHPStan level max clean.

### Por chegar (diferidos para Fase 3 follow-up)

- **WF-010** — `Http\Controllers\TransitionController`: endpoint `POST /admin/{resource}/{record}/transition/{transition}` que valida + dispara a transição (depende do registro `arqel/core` Resource + auth).
- **WorkflowVisualizer React component** — diagrama interativo do workflow (states + transitions) consumindo `WorkflowDefinition::toArray()`.
- **Integração canônica com `spatie/laravel-model-states`** — guard helpers + casts + transition events; o suggest entra em `require` quando o usuário opta in.

## Conventions

- `declare(strict_types=1)` obrigatório em todos os arquivos PHP.
- `WorkflowDefinition`, `StateFilter`, `TransitionAuthorizer`, `StateTransition`, `Listeners\PersistStateTransitionToHistory`, `Events\StateTransitioned` são `final`. `HasWorkflow` e `RecordsStateTransition` são traits (consumidos por user-land, portanto não-final).
- Resolução de chave de state (`HasWorkflow::resolveStateKey`): objeto → `::class` (alinhado com spatie), `BackedEnum` → `value`, string não-vazia → ela mesma, resto → `null` (graceful).
- Transições sem `from()` estático são tratadas como **sempre disponíveis** (pattern "any-to-X", e.g. `Cancel`).
- Labels ausentes derivam pela última parte do FQCN, com PascalCase splitado por espaço.
- Authorization é **deny-by-default** (WF-006). Apps em migração que precisem do legado preservado definem `arqel-workflow.authorization.deny_when_undefined => false`.
- Audit/history toleram falha — listeners capturam `Throwable` para não impedir a transição de domínio.

## Anti-patterns

- **Adicionar `spatie/laravel-model-states` em `require`** do pacote — quebra o design *suggest-only*. Cast spatie é declarado em `require` da **app**, não do pacote.
- **Chamar API spatie diretamente do trait** (`->canTransitionTo(...)`, `transitionTo(...)` sem checagem de `method_exists`) — quebra duck-typing.
- **Usar `getAvailableTransitions()` como autorização** — é metadata para UI. Authorization real fica em `TransitionAuthorizer` (ADR-017).
- **Mutar state dentro de listener de `StateTransitioned`** — listeners devem ser side-effect only (audit, notify, broadcast). Mutar state cria loops e quebra a invariante append-only do histórico.
- **Registrar a mesma state em chaves diferentes** (`OrderState\Paid::class` + `'paid'`) — `getCurrentStateMetadata()` resolve para uma chave canônica; entradas duplicadas vão silently miss.

## Examples

### Exemplos completos (WF-010)

Três workflows reais, com diagrama Mermaid, model Eloquent, Resource, transitions, Gate/`authorizeFor`, `StateFilter` e listeners — em [`docs/examples/workflows/`](../../docs/examples/workflows/README.md):

- [`order-states.md`](../../docs/examples/workflows/order-states.md) — pedidos e-commerce: autorização por papel, webhook de transportadora, "any-to-Cancelled".
- [`article-states.md`](../../docs/examples/workflows/article-states.md) — CMS editorial: rejeição com feedback, autorização 100% via Gate, integração com `arqel/versioning`.
- [`subscription-states.md`](../../docs/examples/workflows/subscription-states.md) — SaaS billing: webhooks Stripe, side-effects em cache/quotas, idempotência via `metadata->webhook_event_id`.

### Setup mínimo de workflow

```php
use Arqel\Workflow\Concerns\HasWorkflow;
use Arqel\Workflow\WorkflowDefinition;
use Illuminate\Database\Eloquent\Model;

final class Order extends Model
{
    use HasWorkflow;

    protected $casts = [
        'order_state' => OrderState::class, // spatie state cast (opcional)
    ];

    public function arqelWorkflow(): WorkflowDefinition
    {
        return WorkflowDefinition::make('order_state')
            ->states([
                OrderState\Pending::class   => ['label' => 'Pending',   'color' => 'warning',     'icon' => 'clock'],
                OrderState\Paid::class      => ['label' => 'Paid',      'color' => 'info',        'icon' => 'credit-card'],
                OrderState\Shipped::class   => ['label' => 'Shipped',   'color' => 'primary',     'icon' => 'truck'],
                OrderState\Delivered::class => ['label' => 'Delivered', 'color' => 'success',     'icon' => 'check-circle'],
                OrderState\Cancelled::class => ['label' => 'Cancelled', 'color' => 'destructive', 'icon' => 'x-circle'],
            ])
            ->transitions([
                Transitions\PendingToPaid::class,
                Transitions\PaidToShipped::class,
                Transitions\ShippedToDelivered::class,
                Transitions\AnyToCancelled::class,
            ]);
    }
}
```

Consumo em controller / Inertia:

```php
$order = Order::find($id);

$current = $order->getCurrentStateMetadata();
// ['label' => 'Paid', 'color' => 'info', 'icon' => 'credit-card']

$available = $order->getAvailableTransitions();
// [Transitions\PaidToShipped::class, Transitions\AnyToCancelled::class]

$payload = $order->arqelWorkflow()->toArray();
// {field, states, transitions} — pronto para Inertia props
```

### Listener de auditoria custom

```php
// EventServiceProvider
protected $listen = [
    \Arqel\Workflow\Events\StateTransitioned::class => [
        \App\Listeners\LogTransitionToAudit::class,
        \App\Listeners\BroadcastTransition::class,
    ],
];
```

Histórico nativo (WF-007) já é persistido pelo listener `PersistStateTransitionToHistory` registrado pelo provider. Para timeline:

```php
// Latest transition de um order
$last = $order->stateTransitions()->first();

// Timeline completo
foreach ($order->stateTransitions as $entry) {
    echo "{$entry->from_state} → {$entry->to_state} at {$entry->created_at}\n";
}

// Disabled em jobs / seeders
config()->set('arqel-workflow.history.enabled', false);
$order->transitionTo(NewState::class); // não grava em arqel_state_transitions
```

### Filter por state em Table

```php
use Arqel\Workflow\Filters\StateFilter;
use Arqel\Workflow\Filters\StateFilterFactory;

Table::make()->filters([
    StateFilter::make('order_state', Order::class),
    // ou via factory (resolve o field automaticamente):
    StateFilterFactory::forResource(Order::class),
]);
```

### Authorization com authorizeFor

```php
final class PaidToShipped
{
    /** @return list<class-string> */
    public static function from(): array
    {
        return [OrderState\Paid::class];
    }

    public static function authorizeFor(?Authenticatable $user, mixed $record): bool
    {
        return $user !== null && $user->can('ship-orders', $record);
    }
}
```

Ou via Gate registrada em `AuthServiceProvider`:

```php
Gate::define('transition-paid-to-shipped', fn ($user, Order $order): bool
    => $user->can('ship-orders', $order));
```

## Related

- Source: [`packages/workflow/src/`](./src/)
- Testes: [`packages/workflow/tests/`](./tests/)
- Tickets: [`PLANNING/10-fase-3-avancadas.md`](../../PLANNING/10-fase-3-avancadas.md) §WF-001..WF-009
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §Workflow
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3
  - [ADR-017](../../PLANNING/03-adrs.md) — Authorization UX-only no client
- Externos: [`spatie/laravel-model-states`](https://spatie.be/docs/laravel-model-states) (suggest — integração canônica, opt-in)

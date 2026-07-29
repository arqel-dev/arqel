# `arqel-dev/workflow` — Referencia de API

Namespace `Arqel\Workflow\`. Máquinas de estados para modelos de Eloquent: los estados que un registro puede ocupar, sus metadatos de UI (label / color / icon), las transiciones legales entre ellos, autorización central, eventos de auditoría y una tabla de historial append-only.

La integración canónica es con [`spatie/laravel-model-states`](https://spatie.be/docs/laravel-model-states), pero el paquete es enteramente duck-typed — enums de PHP, slugs de cadena simples o tokens personalizados también funcionan. `spatie/laravel-model-states` está en `suggest:`, nunca en `require`.

## `Arqel\Workflow\WorkflowDefinition` (final)

Builder fluido que describe un workflow. Factory `WorkflowDefinition::make(string $field): self` — lanza excepción cuando `$field` está vacío.

| Método | Tipo | Descripción |
|---|---|---|
| `states(array $states)` | `self` | `array<string, {label?, color?, icon?}>`, típicamente indexado por `class-string<State>` (o un valor de enum / un slug arbitrario). Reemplaza en lugar de fusionar |
| `transitions(array $transitions)` | `self` | `list<class-string>` de clases de transición |
| `getField()` | `string` | La columna de Eloquent que persiste el estado |
| `getStates()` | `array` | |
| `getTransitions()` | `array` | |
| `getStateMetadata(string $stateClass)` | `?array` | `{label, color, icon}` de un estado |
| `toArray()` | `array` | `{field, states, transitions}` — listo para props de Inertia |

Los labels ausentes se derivan del último segmento PascalCase del FQCN (`OrderState\PendingPayment` → `Pending Payment`); un `color`/`icon` ausente recae en `'secondary'` / `'circle'`.

## `Arqel\Workflow\Concerns\HasWorkflow` (trait)

Consumido por los modelos de Eloquent del usuario, que deben declarar `arqelWorkflow(): WorkflowDefinition`.

| Método | Tipo | Descripción |
|---|---|---|
| `getCurrentStateMetadata()` | `?array` | `{label, color, icon}` del estado actual del registro |
| `getAvailableTransitions()` | `array` | Class-strings de transiciones cuyo `from()` incluye el estado actual, más todas las transiciones sin `from()` (el patrón "any-to-X") |
| `transitionTo(string $newState, array $context = [])` | `void` | Ejecuta la transición y despacha `StateTransitioned` |
| `stateTransitions()` | `MorphMany` | Filas del historial, ordenadas `created_at desc, id desc` |

La resolución de la clave de estado es polimórfica: un objeto resuelve a `::class`, un `BackedEnum` a su `value`, una cadena no vacía a sí misma, y cualquier otra cosa a `null`.

`transitionTo()` delega en la API de spatie cuando el objeto de estado actual expone su propio `transitionTo()` (dejando intactos los guards de spatie); en caso contrario toma la vía de fallback — asignación simple del atributo más `save()`. **En la vía de fallback la validación se ejecuta antes de cualquier mutación o persistencia:** cuando el modelo declara transiciones, `$newState` debe ser alcanzable (alguna transición declarada cuyo `from()` incluya el estado actual — o que no tenga `from()` — y cuyo destino coincida con `$newState`), de lo contrario se lanza `Exceptions\IllegalTransitionException`; entre las transiciones coincidentes al menos una debe estar autorizada por `TransitionAuthorizer`, de lo contrario se lanza `Exceptions\UnauthorizedTransitionException`. Los modelos que declaran `transitions([])` permanecen libres — no se impone ni elegibilidad ni autorización.

El evento se suprime cuando `arqel-workflow.audit.enabled === false` o `audit.log_via !== 'event'` — útil para migraciones y seeders.

## `Arqel\Workflow\Concerns\RecordsStateTransition` (trait)

Helper opcional que permite a una clase `Transition` de spatie despachar el evento canónico `StateTransitioned` desde su propio `handle()`. No asume ninguna API propia de spatie.

## `Arqel\Workflow\Events\StateTransitioned` (final)

Despachado tras una transición exitosa. `Dispatchable` + `SerializesModels`; deliberadamente **no** es `ShouldBroadcast` — el broadcasting es opcional mediante un listener dedicado.

```php
public function __construct(
    public readonly Model $record,
    public readonly string $from,
    public readonly string $to,
    public readonly ?int $userId = null,
    public readonly array $context = [],
) {}
```

## `Arqel\Workflow\Authorization\TransitionAuthorizer` (final readonly)

| Método | Tipo | Descripción |
|---|---|---|
| `authorize(string $transitionClass, ?Authenticatable $user, mixed $record)` | `static bool` | Acepta cualquier cadena — una clase que no existe devuelve `false` |
| `authorizeStates(string $from, string $to, ?Authenticatable $user, mixed $record)` | `static bool` | Decisión indexada por el par de estados en lugar de por la clase de transición |
| `slugifyState(string $stateClassOrKey)` | `static string` | kebab-case del último segmento del FQCN, descartando un sufijo `State` (`'PendingPayment'` → `'pending-payment'`, `'PaidState'` → `'paid'`, `''`/`'*'` → `'*'`) |

Tres capas, en orden: (1) `authorizeFor(?Authenticatable, mixed): bool` declarado en la transición (estático o de instancia — preferido); (2) un Gate registrado con el nombre `transition-{fromSlug}-to-{toSlug}`; (3) **denegar por defecto**. El flag de opt-out es `arqel-workflow.authorization.deny_when_undefined => false`. Las excepciones lanzadas dentro de `authorizeFor` degradan a `false` (fail closed).

## `Arqel\Workflow\Support\TransitionTargetResolver` (final)

`TransitionTargetResolver::resolve(string $transition): string` devuelve el token de destino de una clase de transición: el `to()` estático declarado cuando existe, en su defecto la convención `XxxToYyy` (`PendingToPaid` → `Paid`), y en su defecto el nombre corto de la clase. El mismo resolver alimenta la vía de escritura, el authorizer y el payload serializado del field, de modo que el token que anuncia un botón de la UI hace round-trip a través de las verificaciones del lado servidor.

## `Arqel\Workflow\Models\StateTransition` (final)

Fila de historial append-only en `arqel_state_transitions`. `$timestamps = false`; `metadata` casteado a `array`, `created_at` a `datetime`.

| Método | Tipo | Descripción |
|---|---|---|
| `model()` | `MorphTo` | El registro que transicionó |
| `user()` | `?BelongsTo` | Defensivo — lee `arqel-workflow.user_model` (por defecto `App\Models\User`) y devuelve `null` cuando la clase no está disponible |

La migración `2026_05_01_000000_create_arqel_state_transitions_table` crea `morphs('model')`, `from_state` nulable, `to_state`, `transitioned_by_user_id` indexado, `metadata` JSON y `created_at` con `useCurrent()` (sin `updated_at`). `model_type` se escribe vía `getMorphClass()`, así que bajo `Relation::enforceMorphMap()` la fila almacena el alias.

`Listeners\PersistStateTransitionToHistory` se registra automáticamente en el service provider, se omite silenciosamente cuando `arqel-workflow.history.enabled === false`, y captura `Throwable` para que un fallo de auditoría nunca bloquee la transición de dominio.

## `Arqel\Workflow\Fields\StateTransitionField` (final, extiende `Arqel\Fields\Field`)

Field vinculado a React que renderiza el estado actual junto con sus botones de transición. Factory `StateTransitionField::make(string $name): static`.

| Método | Tipo | Descripción |
|---|---|---|
| `showDescription(bool $show = true)` | `static` | |
| `showHistory(bool $show = true)` | `static` | |
| `transitionsAttribute(string $name = 'state')` | `static` | |
| `record(?Model $record)` | `static` | Vincula el registro cuyo estado se renderiza |
| `getRecord()` / `getTransitionsAttribute()` / `isShowingDescription()` / `isShowingHistory()` | getters | |
| `getTypeSpecificProps()` | `array` | Payload serializado que se fusiona en el esquema del field |
| `resolveCurrentState()` | `?array` | `{name, label, color, icon}` |
| `resolveAvailableTransitions()` | `array` | `list<{from, to, label, authorized}>` — `authorized` delega en `TransitionAuthorizer` |
| `resolveHistory()` | `array` | Filas reales filtradas por `(model_type, model_id)`, con `model_type` indexado por `getMorphClass()`; el límite viene de `arqel-workflow.history.limit` (por defecto 50), best-effort con `Throwable` capturado |

Se emite exactamente una entrada por transición aplicable, reflejando `HasWorkflow::getAvailableTransitions()`: una transición cuyo `from()` lista varios estados produce un **único** botón que lleva el estado actual del registro como `from`. Las transiciones cuyo `from()` excluye el estado actual quedan filtradas; las transiciones sin `from()` siempre aparecen.

## Filtros

### `Arqel\Workflow\Filters\StateFilter` (final readonly)

Filtro autónomo que deriva sus opciones del `WorkflowDefinition` del modelo. El constructor `(string $field, string $modelClass)` valida que el field no esté vacío, que la clase exista y que la clase use `HasWorkflow` — lanzando `InvalidArgumentException` en caso contrario. Factory `StateFilter::make(string $field, string $modelClass): self`.

| Método | Tipo | Descripción |
|---|---|---|
| `toArray()` | `array` | `{field, type: 'state', label, options}` |
| `optionsArray()` | `array` | `array<string, {value, label, color, icon}>` |
| `apply(Builder $query, mixed $value)` | `void` | Cadena → `where`; array → `whereIn` (se descartan los valores no-string / vacíos); `null` o vacío → no hace nada |

### `Arqel\Workflow\Filters\StateFilterFactory` (final)

`StateFilterFactory::forResource(string $modelClass, ?string $field = null): StateFilter` — resuelve el field desde `arqelWorkflow()->getField()` cuando se omite.

No hay dependencia dura de `arqel-dev/table`; el usuario conecta `StateFilter` en `Table::filters([...])`.

## Excepciones

| Clase | Factory |
|---|---|
| `Exceptions\IllegalTransitionException` (final, extiende `RuntimeException`) | `IllegalTransitionException::for(string $from, string $to): self` |
| `Exceptions\UnauthorizedTransitionException` (final, extiende `RuntimeException`) | `UnauthorizedTransitionException::for(string $to): self` |

## Integración con realtime

`arqel-dev/workflow` permanece autónomo — nunca importa nada de `arqel-dev/realtime`. Cuando `arqel-dev/realtime` **sí** está instalado, su service provider registra `Arqel\Realtime\Workflow\BroadcastStateTransitionListener` para `Events\StateTransitioned`, convirtiendo cada transición en un broadcast `Arqel\Realtime\Events\ResourceUpdated` sobre los canales `arqel.{slug}` y `arqel.{slug}.{id}` del registro. Desactívalo globalmente con `ARQEL_REALTIME_WORKFLOW_BROADCAST=false` o `config()->set('arqel-realtime.workflow.broadcast_state_transitions', false)`.

## HTTP

El paquete **no registra rutas** — todavía no hay un controlador de transiciones. Las apps proveen su propia ruta que valida la entrada y llama a `HasWorkflow::transitionTo()`. Un endpoint canónico `POST /admin/{resource}/{record}/transition/{transition}` es trabajo futuro.

## Ejemplo

```php
use Arqel\Workflow\Concerns\HasWorkflow;
use Arqel\Workflow\WorkflowDefinition;

final class Order extends Model
{
    use HasWorkflow;

    public function arqelWorkflow(): WorkflowDefinition
    {
        return WorkflowDefinition::make('order_state')
            ->states([
                OrderState\Pending::class   => ['label' => 'Pending',   'color' => 'warning',     'icon' => 'clock'],
                OrderState\Paid::class      => ['label' => 'Paid',      'color' => 'info',        'icon' => 'credit-card'],
                OrderState\Shipped::class   => ['label' => 'Shipped',   'color' => 'primary',     'icon' => 'truck'],
                OrderState\Cancelled::class => ['label' => 'Cancelled', 'color' => 'destructive', 'icon' => 'x-circle'],
            ])
            ->transitions([
                Transitions\PendingToPaid::class,
                Transitions\PaidToShipped::class,
                Transitions\AnyToCancelled::class,
            ]);
    }
}
```

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

```php
use Arqel\Workflow\Filters\StateFilter;

Table::make()->filters([
    StateFilter::make('order_state', Order::class),
]);
```

## Relacionado

- SKILL: [`packages/workflow/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/workflow/SKILL.md)
- Código fuente: [`packages/workflow/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/workflow/src/)

# `arqel-dev/workflow` — API Reference

Namespace `Arqel\Workflow\`. State machines for Eloquent models: the states a record can occupy, their UI metadata (label / color / icon), the legal transitions between them, central authorization, audit events and an append-only history table.

The canonical integration is with [`spatie/laravel-model-states`](https://spatie.be/docs/laravel-model-states), but the package is entirely duck-typed — PHP enums, plain string slugs or custom tokens work too. `spatie/laravel-model-states` sits in `suggest:`, never in `require`.

## `Arqel\Workflow\WorkflowDefinition` (final)

Fluent builder describing one workflow. Factory `WorkflowDefinition::make(string $field): self` — throws when `$field` is empty.

| Method | Type | Description |
|---|---|---|
| `states(array $states)` | `self` | `array<string, {label?, color?, icon?}>`, typically keyed by `class-string<State>` (or an enum value / arbitrary slug). Replaces rather than merges |
| `transitions(array $transitions)` | `self` | `list<class-string>` of transition classes |
| `getField()` | `string` | The Eloquent column persisting the state |
| `getStates()` | `array` | |
| `getTransitions()` | `array` | |
| `getStateMetadata(string $stateClass)` | `?array` | `{label, color, icon}` for one state |
| `toArray()` | `array` | `{field, states, transitions}` — ready for Inertia props |

Missing labels are derived from the last PascalCase segment of the FQCN (`OrderState\PendingPayment` → `Pending Payment`); a missing `color`/`icon` falls back to `'secondary'` / `'circle'`.

## `Arqel\Workflow\Concerns\HasWorkflow` (trait)

Consumed by user-land Eloquent models, which must declare `arqelWorkflow(): WorkflowDefinition`.

| Method | Type | Description |
|---|---|---|
| `getCurrentStateMetadata()` | `?array` | `{label, color, icon}` for the record's current state |
| `getAvailableTransitions()` | `array` | Transition class-strings whose `from()` includes the current state, plus every transition with no `from()` (the "any-to-X" pattern) |
| `transitionTo(string $newState, array $context = [])` | `void` | Performs the transition and dispatches `StateTransitioned` |
| `stateTransitions()` | `MorphMany` | History rows, ordered `created_at desc, id desc` |

State-key resolution is polymorphic: an object resolves to `::class`, a `BackedEnum` to its `value`, a non-empty string to itself, anything else to `null`.

`transitionTo()` delegates to the spatie API when the current state object exposes its own `transitionTo()` (leaving spatie's guards intact); otherwise it takes the fallback path — plain attribute assignment plus `save()`. **On the fallback path the enforcement runs before any mutation or persistence:** when the model declares transitions, `$newState` must be reachable (some declared transition whose `from()` includes the current state — or which has no `from()` — and whose destination matches `$newState`), otherwise `Exceptions\IllegalTransitionException` is thrown; among the matching transitions at least one must be authorized by `TransitionAuthorizer`, otherwise `Exceptions\UnauthorizedTransitionException` is thrown. Models declaring `transitions([])` stay free-form — no eligibility or authorization is imposed.

The event is suppressed when `arqel-workflow.audit.enabled === false` or `audit.log_via !== 'event'` — useful for migrations and seeders.

## `Arqel\Workflow\Concerns\RecordsStateTransition` (trait)

Optional helper letting a spatie `Transition` class dispatch the canonical `StateTransitioned` event from its own `handle()`. It assumes no spatie API of its own.

## `Arqel\Workflow\Events\StateTransitioned` (final)

Dispatched after a successful transition. `Dispatchable` + `SerializesModels`; deliberately **not** `ShouldBroadcast` — broadcasting is opt-in via a dedicated listener.

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

| Method | Type | Description |
|---|---|---|
| `authorize(string $transitionClass, ?Authenticatable $user, mixed $record)` | `static bool` | Accepts any string — a class that doesn't exist returns `false` |
| `authorizeStates(string $from, string $to, ?Authenticatable $user, mixed $record)` | `static bool` | Decision keyed by the state pair rather than the transition class |
| `slugifyState(string $stateClassOrKey)` | `static string` | kebab-case of the final FQCN segment, dropping a `State` suffix (`'PendingPayment'` → `'pending-payment'`, `'PaidState'` → `'paid'`, `''`/`'*'` → `'*'`) |

Three layers, in order: (1) `authorizeFor(?Authenticatable, mixed): bool` declared on the transition (static or instance — preferred); (2) a registered Gate named `transition-{fromSlug}-to-{toSlug}`; (3) **deny by default**. The opt-out flag is `arqel-workflow.authorization.deny_when_undefined => false`. Exceptions thrown inside `authorizeFor` degrade to `false` (fail closed).

## `Arqel\Workflow\Support\TransitionTargetResolver` (final)

`TransitionTargetResolver::resolve(string $transition): string` returns the destination token for a transition class: the declared static `to()` when present, otherwise the `XxxToYyy` convention (`PendingToPaid` → `Paid`), otherwise the class short name. The same resolver feeds the write path, the authorizer and the field's serialized payload, so the token a UI button announces round-trips through the server-side checks.

## `Arqel\Workflow\Models\StateTransition` (final)

Append-only history row on `arqel_state_transitions`. `$timestamps = false`; `metadata` cast to `array`, `created_at` to `datetime`.

| Method | Type | Description |
|---|---|---|
| `model()` | `MorphTo` | The record that transitioned |
| `user()` | `?BelongsTo` | Defensive — reads `arqel-workflow.user_model` (default `App\Models\User`) and returns `null` when the class is unavailable |

The migration `2026_05_01_000000_create_arqel_state_transitions_table` creates `morphs('model')`, nullable `from_state`, `to_state`, indexed `transitioned_by_user_id`, JSON `metadata` and `created_at` with `useCurrent()` (no `updated_at`). `model_type` is written via `getMorphClass()`, so under `Relation::enforceMorphMap()` the row stores the alias.

`Listeners\PersistStateTransitionToHistory` is auto-registered by the service provider, skips silently when `arqel-workflow.history.enabled === false`, and catches `Throwable` so an audit failure never blocks the domain transition.

## `Arqel\Workflow\Fields\StateTransitionField` (final, extends `Arqel\Fields\Field`)

React-bound field rendering the current state plus its transition buttons. Factory `StateTransitionField::make(string $name): static`.

| Method | Type | Description |
|---|---|---|
| `showDescription(bool $show = true)` | `static` | |
| `showHistory(bool $show = true)` | `static` | |
| `transitionsAttribute(string $name = 'state')` | `static` | |
| `record(?Model $record)` | `static` | Binds the record whose state is rendered |
| `getRecord()` / `getTransitionsAttribute()` / `isShowingDescription()` / `isShowingHistory()` | getters | |
| `getTypeSpecificProps()` | `array` | Serialized payload merged into the field schema |
| `resolveCurrentState()` | `?array` | `{name, label, color, icon}` |
| `resolveAvailableTransitions()` | `array` | `list<{from, to, label, authorized}>` — `authorized` delegates to `TransitionAuthorizer` |
| `resolveHistory()` | `array` | Real rows filtered by `(model_type, model_id)`, `model_type` keyed by `getMorphClass()`; limit from `arqel-workflow.history.limit` (default 50), best-effort with `Throwable` caught |

Exactly one entry is emitted per applicable transition, mirroring `HasWorkflow::getAvailableTransitions()`: a transition whose `from()` lists several states produces a **single** button carrying the record's current state as `from`. Transitions whose `from()` excludes the current state are filtered out; transitions with no `from()` always appear.

## Filters

### `Arqel\Workflow\Filters\StateFilter` (final readonly)

Standalone filter deriving its options from the model's `WorkflowDefinition`. Constructor `(string $field, string $modelClass)` validates that the field is non-empty, the class exists, and the class uses `HasWorkflow` — throwing `InvalidArgumentException` otherwise. Factory `StateFilter::make(string $field, string $modelClass): self`.

| Method | Type | Description |
|---|---|---|
| `toArray()` | `array` | `{field, type: 'state', label, options}` |
| `optionsArray()` | `array` | `array<string, {value, label, color, icon}>` |
| `apply(Builder $query, mixed $value)` | `void` | String → `where`; array → `whereIn` (non-string / empty values dropped); `null` or empty → no-op |

### `Arqel\Workflow\Filters\StateFilterFactory` (final)

`StateFilterFactory::forResource(string $modelClass, ?string $field = null): StateFilter` — resolves the field from `arqelWorkflow()->getField()` when omitted.

There is no hard dependency on `arqel-dev/table`; user-land plugs `StateFilter` into `Table::filters([...])`.

## Exceptions

| Class | Factory |
|---|---|
| `Exceptions\IllegalTransitionException` (final, extends `RuntimeException`) | `IllegalTransitionException::for(string $from, string $to): self` |
| `Exceptions\UnauthorizedTransitionException` (final, extends `RuntimeException`) | `UnauthorizedTransitionException::for(string $to): self` |

## Realtime integration

`arqel-dev/workflow` stays standalone — it never imports anything from `arqel-dev/realtime`. When `arqel-dev/realtime` **is** installed, its service provider registers `Arqel\Realtime\Workflow\BroadcastStateTransitionListener` for `Events\StateTransitioned`, turning each transition into an `Arqel\Realtime\Events\ResourceUpdated` broadcast on the record's `arqel.{slug}` and `arqel.{slug}.{id}` channels. Opt out globally with `ARQEL_REALTIME_WORKFLOW_BROADCAST=false` or `config()->set('arqel-realtime.workflow.broadcast_state_transitions', false)`.

## HTTP

The package registers **no routes** — there is no transition controller yet. Apps provide their own route that validates the input and calls `HasWorkflow::transitionTo()`. A canonical `POST /admin/{resource}/{record}/transition/{transition}` endpoint is future work.

## Example

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

## Related

- SKILL: [`packages/workflow/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/workflow/SKILL.md)
- Source: [`packages/workflow/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/workflow/src/)

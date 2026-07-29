# `arqel-dev/realtime` — API Reference

Namespace `Arqel\Realtime\`. The broadcasting layer: private events carrying a minimal payload, an opt-in trait auto-dispatching from the `Resource` lifecycle, presence channel helpers, a central channel authorizer, and a Yjs-based collaborative editing scaffold.

The package is broadcaster-agnostic. The target stack is [Laravel Reverb](https://laravel.com/docs/reverb), but any driver implementing `Illuminate\Contracts\Broadcasting\Broadcaster` (Pusher, Ably, log, null) works — Reverb lives in `suggest:`, never in `require`. `arqel-dev/core` **is** a hard dependency: the package exists to hook into the Resource lifecycle.

## Channel naming

| Pattern | Purpose |
|---|---|
| `arqel.{slug}` | Resource list |
| `arqel.{slug}.{id}` | Single record |
| `arqel.action.{jobId}` | Action progress |
| `arqel.presence.{slug}.{id}` | Presence roster (pattern configurable) |
| `arqel.collab.{modelType}.{modelId}.{field}` | Yjs collaborative editing |

Every channel is a `PrivateChannel`; the gating lives in `routes/channels.php`, delegating to the authorizers below.

## Events

### `Arqel\Realtime\Events\ResourceUpdated` (final, `ShouldBroadcast`)

```php
public function __construct(
    public readonly string $resourceClass,
    public readonly Model $record,
    public readonly ?int $updatedByUserId = null,
) {}
```

`broadcastOn(): array` returns `[PrivateChannel("arqel.{slug}"), PrivateChannel("arqel.{slug}.{id}")]`; the second channel is omitted when `$record->getKey()` is `null`, which keeps the event safe on unpersisted records. `broadcastWith(): array` returns `{id, updatedByUserId, updatedAt}` via `getKey()` + `getAttribute('updated_at')`, so a model without timestamps still serializes.

Slug resolution is defensive: it tries `$resourceClass::getSlug()` behind `method_exists` + `try/catch (Throwable)` and, on failure or an empty/non-string return, falls back to `Str::of(class_basename(...))->beforeLast('Resource')->snake('-')->plural()` — mirroring `Arqel\Core\Resources\Resource::getSlug()` so the fallback matches the real slug even when the Resource subclass is only partially bootstrapped.

### `Arqel\Realtime\Events\YjsUpdateReceived` (final, `ShouldBroadcast`)

```php
public function __construct(
    public readonly string $modelType,
    public readonly mixed $modelId,
    public readonly string $field,
    public readonly string $stateBase64,
    public readonly int $version,
    public readonly ?int $userId,
) {}
```

`broadcastOn()` returns `[PrivateChannel("arqel.collab.{modelType}.{modelId}.{field}")]`, `broadcastAs()` returns `'collab.update'`, and `broadcastWith()` returns `{state, version, by_user_id}` with the state base64-encoded. Dispatched by `CollabDocumentController::store` after the snapshot is persisted.

## `Arqel\Realtime\Concerns\BroadcastsResourceUpdates` (trait)

The single entry point for auto-dispatch. It overrides `protected afterUpdate(Model $record): void` on the core `Resource` and dispatches `ResourceUpdated::dispatch(static::class, $record, $userId)`. It honors the `arqel-realtime.auto_dispatch.resource_updated` kill switch and resolves the user id via `auth()->id()` defensively, passing it through only when it is an `int` (UUID / string ids become `null`).

The wiring is a trait rather than a patch on `Resource` because `arqel-dev/core` and `arqel-dev/realtime` are independent packages — cross-package mutation would push every core consumer into the broadcasting stack. Subclasses that need extra logic in `afterUpdate` must call `parent::afterUpdate($record)` so the broadcast is not silenced.

## `Arqel\Realtime\Channels\ResourceChannelAuthorizer` (final readonly)

Static methods concentrating the Gate checks:

| Method | Signature | Check |
|---|---|---|
| `authorizeResource` | `(Authenticatable $user, string $resourceSlug): bool` | `viewAny` on the model class |
| `authorizeRecord` | `(Authenticatable $user, string $resourceSlug, int\|string $recordId): bool` | `view` on the resolved record |
| `authorizeActionJob` | `(Authenticatable $user, string $jobId): bool` | `Cache::get("arqel.action.{jobId}.user") === $user->getAuthIdentifier()` (strict comparison) |
| `resolveRecord` | `(string $resourceSlug, int\|string $recordId): ?Model` | Shared resolution helper |
| `registryBound` | `(): bool` | Whether `Arqel\Core\Resources\ResourceRegistry` is bound |
| `presenceMemberInfo` | `(Authenticatable $user): array` | `{id, name, avatar}` for the presence roster |

Each method wraps its logic in `try/catch \Throwable` and logs via `Log::warning()` — **deny by default**. Without `arqel-dev/core` in the container, a registry lacking `findBySlug`, or a `null` lookup, the authorizer denies rather than throwing, preserving the optional coupling.

## Presence

### `Arqel\Realtime\Presence\PresenceChannelResolver` (final readonly)

| Method | Type | Description |
|---|---|---|
| `pattern()` | `static string` | Single source of the pattern, read from `arqel-realtime.presence.channel_pattern`, falling back to `arqel.presence.{resource}.{recordId}` when non-string or empty |
| `forResource(string $slug, int\|string $recordId)` | `static string` | The concrete channel name |

`routes/channels.php` registers the presence channel using the very same `pattern()`, so registration and subscription stay in sync even with a custom pattern. A custom pattern **must** preserve the `{resource}` and `{recordId}` tokens (Laravel binds callback arguments positionally by name); extra placeholders stay literal on both sides. Calling the resolver while `presence.enabled === false` raises `Exceptions\RealtimeException`.

The presence callback is Policy-protected by default, not fail-open. The ladder is: (1) an app-defined named Gate `view-resource-presence` wins outright; (2) without it, the record's `view` ability decides — when a `view` Gate **or** a registered Policy exists, `Gate::check('view', $record)` is authoritative; (3) it only opens for genuine scaffolds — the `ResourceRegistry` unbound (realtime standalone), or a resolved record with neither Gate nor Policy. If the registry **is** bound but the record does not resolve, it denies.

## Collaborative editing

### `Arqel\Realtime\Collab\YjsDocument` (final)

Persists the `Y.Doc` snapshot on `arqel_yjs_documents` (unique on `(model_type, model_id, field)`). `$timestamps = false`; casts `version => integer`, `last_user_id => integer`, `updated_at => datetime`. `morphedModel(): MorphTo` points at the source model.

### `Arqel\Realtime\Collab\AwarenessChannelAuthorizer` (final readonly)

`authorize(Authenticatable $user, string $modelType, int|string $modelId, string $field): bool` authorizes `arqel.collab.{modelType}.{modelId}.{field}`. It resolves the model by direct FQCN, via `ResourceRegistry::all()` matching on `getModel()`, or via `ResourceRegistry::findBySlug()` — mirroring `CollabDocumentController`, so the accepted `{modelType}` is exactly the key the REST endpoint persists and `YjsUpdateReceived` broadcasts on. It denies when the registry is unbound and neither FQCN nor slug resolves. The `view` ability is applied when the app defines a `view` Gate **or** registers a Policy (via `Gate::getPolicyFor()`, since `Gate::has()` never sees Policies); it only allows through with neither present (scaffold mode).

### `Arqel\Realtime\Http\Controllers\CollabDocumentController` (final)

| Verb | Route | Name | Method |
|---|---|---|---|
| GET | `/admin/{resource}/{id}/collab/{field}` | `arqel.realtime.collab.show` | `show(Request, string $resource, string $id, string $field): JsonResponse` |
| POST | `/admin/{resource}/{id}/collab/{field}` | `arqel.realtime.collab.store` | `store(Request, string $resource, string $id, string $field): JsonResponse` |

Both are registered with `web` + `auth`. Each request resolves the owning model from `{resource}` (slug via `ResourceRegistry::findBySlug()`, or a direct FQCN) plus `{id}` and authorizes `view` (show) / `update` (store) before reading or writing — the same Policy-aware ladder as the awareness authorizer. `abort(404)` when the model or record does not resolve; `403` when the ability is denied. Optimistic concurrency is enforced through the `version` column: a request whose `version` is lower than the current one receives `409 {message, serverVersion}`. State is always transported base64-encoded.

## Workflow integration

When `arqel-dev/workflow` is autoloadable, `RealtimeServiceProvider` registers `Workflow\BroadcastStateTransitionListener` for `Arqel\Workflow\Events\StateTransitioned`. Each successful transition dispatches a `ResourceUpdated` carrying the same `record` and `userId`. The listener resolves the Resource class via `ResourceRegistry::findByModel($record::class)` and passes it as `resourceClass`, so the channels match the ones the UI already subscribes to; with the registry unbound or no Resource for the model it falls back to the model FQCN.

The dependency direction is `realtime → workflow` only. The wiring is defensive: the listener is registered only when `class_exists('Arqel\Workflow\Events\StateTransitioned')`, and `handle(mixed $event): void` accepts `mixed` so it never breaks when the class is absent. Disable with `ARQEL_REALTIME_WORKFLOW_BROADCAST=false` or `config()->set('arqel-realtime.workflow.broadcast_state_transitions', false)` — this short-circuits `handle()` rather than unregistering the listener.

## Exceptions

`Arqel\Realtime\Exceptions\RealtimeException` (final, extends `RuntimeException`) is the package's base runtime exception.

## Configuration

`config/arqel-realtime.php` exposes five blocks: `connection` (broadcaster alias, falling back to `broadcasting.default`), `channel_prefix` (default `arqel`), `auto_dispatch.resource_updated` (default `true` — the global kill switch), `presence` with `enabled` (`ARQEL_REALTIME_PRESENCE_ENABLED`) and `channel_pattern`, and `workflow.broadcast_state_transitions` (`ARQEL_REALTIME_WORKFLOW_BROADCAST`).

## Example

```php
use Arqel\Core\Resources\Resource;
use Arqel\Realtime\Concerns\BroadcastsResourceUpdates;

final class PostResource extends Resource
{
    use BroadcastsResourceUpdates;

    protected static string $model = \App\Models\Post::class;

    // ... fields, table, form. Nothing else to wire.
}
```

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=local
REVERB_APP_KEY=local
REVERB_APP_SECRET=local
REVERB_HOST=localhost
REVERB_PORT=8080
```

```php
use Arqel\Realtime\Presence\PresenceChannelResolver;

PresenceChannelResolver::forResource('posts', 42);
// → "arqel.presence.posts.42"

PresenceChannelResolver::pattern();
// → "arqel.presence.{resource}.{recordId}"

// A custom pattern MUST keep the {resource} and {recordId} placeholders.
config(['arqel-realtime.presence.channel_pattern' => 'tenant.{resource}.{recordId}']);
PresenceChannelResolver::forResource('orders', 7);
// → "tenant.orders.7"
```

```php
// Gates the authorizer consults:
Gate::define('viewAny', fn ($user, string $modelClass) => $user->can('view-list', $modelClass));
Gate::define('view', fn ($user, $record) => $user->id === $record->owner_id);

// Action progress: the job records the owner before dispatch.
Cache::put("arqel.action.{$jobId}.user", auth()->id(), now()->addMinutes(30));

// Presence: the named Gate is OPTIONAL — without it the channel falls back
// to the record's `view` Policy. Define it only to override that ladder.
Gate::define('view-resource-presence', fn ($user, string $resource, string|int $id) =>
    $user->can('view', Post::find($id)));
```

## Related

- SKILL: [`packages/realtime/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/realtime/SKILL.md)
- Source: [`packages/realtime/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/realtime/src/)

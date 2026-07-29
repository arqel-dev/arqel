# `arqel-dev/realtime` — Referencia de API

Namespace `Arqel\Realtime\`. La capa de broadcasting: eventos privados que llevan un payload mínimo, un trait opcional que auto-despacha desde el ciclo de vida del `Resource`, helpers de canales de presencia, un autorizador central de canales, y un scaffold de edición colaborativa basado en Yjs.

El paquete es agnóstico del broadcaster. El stack objetivo es [Laravel Reverb](https://laravel.com/docs/reverb), pero funciona cualquier driver que implemente `Illuminate\Contracts\Broadcasting\Broadcaster` (Pusher, Ably, log, null) — Reverb vive en `suggest:`, nunca en `require`. `arqel-dev/core` **sí** es una dependencia dura: el paquete existe para engancharse al ciclo de vida del Resource.

## Nomenclatura de canales

| Patrón | Propósito |
|---|---|
| `arqel.{slug}` | Listado del Resource |
| `arqel.{slug}.{id}` | Registro individual |
| `arqel.action.{jobId}` | Progreso de una Action |
| `arqel.presence.{slug}.{id}` | Roster de presencia (patrón configurable) |
| `arqel.collab.{modelType}.{modelId}.{field}` | Edición colaborativa con Yjs |

Todos los canales son `PrivateChannel`; el control de acceso vive en `routes/channels.php`, delegando en los autorizadores de abajo.

## Eventos

### `Arqel\Realtime\Events\ResourceUpdated` (final, `ShouldBroadcast`)

```php
public function __construct(
    public readonly string $resourceClass,
    public readonly Model $record,
    public readonly ?int $updatedByUserId = null,
) {}
```

`broadcastOn(): array` devuelve `[PrivateChannel("arqel.{slug}"), PrivateChannel("arqel.{slug}.{id}")]`; el segundo canal se omite cuando `$record->getKey()` es `null`, lo que mantiene el evento seguro sobre registros no persistidos. `broadcastWith(): array` devuelve `{id, updatedByUserId, updatedAt}` vía `getKey()` + `getAttribute('updated_at')`, de modo que un modelo sin timestamps aún serializa.

La resolución del slug es defensiva: intenta `$resourceClass::getSlug()` detrás de `method_exists` + `try/catch (Throwable)` y, ante un fallo o un retorno vacío/no-string, recae en `Str::of(class_basename(...))->beforeLast('Resource')->snake('-')->plural()` — reflejando `Arqel\Core\Resources\Resource::getSlug()` para que el fallback coincida con el slug real incluso cuando la subclase de Resource solo está parcialmente inicializada.

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

`broadcastOn()` devuelve `[PrivateChannel("arqel.collab.{modelType}.{modelId}.{field}")]`, `broadcastAs()` devuelve `'collab.update'`, y `broadcastWith()` devuelve `{state, version, by_user_id}` con el estado codificado en base64. Despachado por `CollabDocumentController::store` después de persistir el snapshot.

## `Arqel\Realtime\Concerns\BroadcastsResourceUpdates` (trait)

El único punto de entrada para el auto-despacho. Sobrescribe `protected afterUpdate(Model $record): void` en el `Resource` de core y despacha `ResourceUpdated::dispatch(static::class, $record, $userId)`. Respeta el kill switch `arqel-realtime.auto_dispatch.resource_updated` y resuelve el id del usuario vía `auth()->id()` de forma defensiva, pasándolo solo cuando es un `int` (los ids UUID / string se convierten en `null`).

El cableado es un trait y no un parche sobre `Resource` porque `arqel-dev/core` y `arqel-dev/realtime` son paquetes independientes — una mutación cross-package empujaría a cada consumidor de core hacia el stack de broadcasting. Las subclases que necesiten lógica extra en `afterUpdate` deben llamar a `parent::afterUpdate($record)` para que el broadcast no quede silenciado.

## `Arqel\Realtime\Channels\ResourceChannelAuthorizer` (final readonly)

Métodos estáticos que concentran las verificaciones de Gate:

| Método | Firma | Verificación |
|---|---|---|
| `authorizeResource` | `(Authenticatable $user, string $resourceSlug): bool` | `viewAny` sobre la clase del modelo |
| `authorizeRecord` | `(Authenticatable $user, string $resourceSlug, int\|string $recordId): bool` | `view` sobre el registro resuelto |
| `authorizeActionJob` | `(Authenticatable $user, string $jobId): bool` | `Cache::get("arqel.action.{jobId}.user") === $user->getAuthIdentifier()` (comparación estricta) |
| `resolveRecord` | `(string $resourceSlug, int\|string $recordId): ?Model` | Helper de resolución compartido |
| `registryBound` | `(): bool` | Si `Arqel\Core\Resources\ResourceRegistry` está vinculado |
| `presenceMemberInfo` | `(Authenticatable $user): array` | `{id, name, avatar}` para el roster de presencia |

Cada método envuelve su lógica en `try/catch \Throwable` y registra vía `Log::warning()` — **denegar por defecto**. Sin `arqel-dev/core` en el contenedor, con un registry que carece de `findBySlug`, o ante una búsqueda `null`, el autorizador deniega en lugar de lanzar excepción, preservando el acoplamiento opcional.

## Presencia

### `Arqel\Realtime\Presence\PresenceChannelResolver` (final readonly)

| Método | Tipo | Descripción |
|---|---|---|
| `pattern()` | `static string` | Fuente única del patrón, leída de `arqel-realtime.presence.channel_pattern`, recayendo en `arqel.presence.{resource}.{recordId}` cuando no es string o está vacío |
| `forResource(string $slug, int\|string $recordId)` | `static string` | El nombre concreto del canal |

`routes/channels.php` registra el canal de presencia usando exactamente el mismo `pattern()`, de modo que registro y suscripción permanecen sincronizados incluso con un patrón personalizado. Un patrón personalizado **debe** preservar los tokens `{resource}` y `{recordId}` (Laravel vincula los argumentos del callback posicionalmente por nombre); los placeholders adicionales quedan literales en ambos lados. Llamar al resolver mientras `presence.enabled === false` lanza `Exceptions\RealtimeException`.

El callback de presencia está protegido por Policy por defecto, no es fail-open. La escalera es: (1) un Gate con nombre `view-resource-presence` definido por la app gana de forma absoluta; (2) sin él, decide la ability `view` del registro — cuando existe un Gate `view` **o** una Policy registrada, `Gate::check('view', $record)` es autoritativo; (3) solo se abre ante scaffolds genuinos — el `ResourceRegistry` sin vincular (realtime autónomo), o un registro resuelto sin Gate ni Policy. Si el registry **sí** está vinculado pero el registro no resuelve, deniega.

## Edición colaborativa

### `Arqel\Realtime\Collab\YjsDocument` (final)

Persiste el snapshot del `Y.Doc` en `arqel_yjs_documents` (único en `(model_type, model_id, field)`). `$timestamps = false`; castea `version => integer`, `last_user_id => integer`, `updated_at => datetime`. `morphedModel(): MorphTo` apunta al modelo de origen.

### `Arqel\Realtime\Collab\AwarenessChannelAuthorizer` (final readonly)

`authorize(Authenticatable $user, string $modelType, int|string $modelId, string $field): bool` autoriza `arqel.collab.{modelType}.{modelId}.{field}`. Resuelve el modelo por FQCN directo, vía `ResourceRegistry::all()` haciendo match sobre `getModel()`, o vía `ResourceRegistry::findBySlug()` — reflejando `CollabDocumentController`, de modo que el `{modelType}` aceptado es exactamente la clave que el endpoint REST persiste y sobre la que `YjsUpdateReceived` hace broadcast. Deniega cuando el registry no está vinculado y no resuelve ni el FQCN ni el slug. La ability `view` se aplica cuando la app define un Gate `view` **o** registra una Policy (vía `Gate::getPolicyFor()`, ya que `Gate::has()` nunca ve las Policies); solo permite el paso cuando no existe ninguno de los dos (modo scaffold).

### `Arqel\Realtime\Http\Controllers\CollabDocumentController` (final)

| Verbo | Ruta | Nombre | Método |
|---|---|---|---|
| GET | `/admin/{resource}/{id}/collab/{field}` | `arqel.realtime.collab.show` | `show(Request, string $resource, string $id, string $field): JsonResponse` |
| POST | `/admin/{resource}/{id}/collab/{field}` | `arqel.realtime.collab.store` | `store(Request, string $resource, string $id, string $field): JsonResponse` |

Ambas se registran con `web` + `auth`. Cada petición resuelve el modelo propietario a partir de `{resource}` (slug vía `ResourceRegistry::findBySlug()`, o un FQCN directo) más `{id}` y autoriza `view` (show) / `update` (store) antes de leer o escribir — la misma escalera consciente de Policies que el autorizador de awareness. `abort(404)` cuando el modelo o el registro no resuelven; `403` cuando la ability es denegada. La concurrencia optimista se aplica mediante la columna `version`: una petición cuya `version` sea menor que la actual recibe `409 {message, serverVersion}`. El estado siempre se transporta codificado en base64.

## Integración con workflow

Cuando `arqel-dev/workflow` es autocargable, `RealtimeServiceProvider` registra `Workflow\BroadcastStateTransitionListener` para `Arqel\Workflow\Events\StateTransitioned`. Cada transición exitosa despacha un `ResourceUpdated` que lleva el mismo `record` y `userId`. El listener resuelve la clase del Resource vía `ResourceRegistry::findByModel($record::class)` y la pasa como `resourceClass`, de modo que los canales coinciden con los que la UI ya tiene suscritos; con el registry sin vincular o sin Resource para el modelo, recae en el FQCN del modelo.

La dirección de la dependencia es únicamente `realtime → workflow`. El cableado es defensivo: el listener se registra solo cuando `class_exists('Arqel\Workflow\Events\StateTransitioned')`, y `handle(mixed $event): void` acepta `mixed` para que nunca se rompa si la clase está ausente. Desactívalo con `ARQEL_REALTIME_WORKFLOW_BROADCAST=false` o `config()->set('arqel-realtime.workflow.broadcast_state_transitions', false)` — esto cortocircuita `handle()` en lugar de desregistrar el listener.

## Excepciones

`Arqel\Realtime\Exceptions\RealtimeException` (final, extiende `RuntimeException`) es la excepción de runtime base del paquete.

## Configuración

`config/arqel-realtime.php` expone cinco bloques: `connection` (alias del broadcaster, recayendo en `broadcasting.default`), `channel_prefix` (por defecto `arqel`), `auto_dispatch.resource_updated` (por defecto `true` — el kill switch global), `presence` con `enabled` (`ARQEL_REALTIME_PRESENCE_ENABLED`) y `channel_pattern`, y `workflow.broadcast_state_transitions` (`ARQEL_REALTIME_WORKFLOW_BROADCAST`).

## Ejemplo

```php
use Arqel\Core\Resources\Resource;
use Arqel\Realtime\Concerns\BroadcastsResourceUpdates;

final class PostResource extends Resource
{
    use BroadcastsResourceUpdates;

    protected static string $model = \App\Models\Post::class;

    // ... fields, table, form. No hay nada más que cablear.
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

// Un patrón personalizado DEBE conservar los placeholders {resource} y {recordId}.
config(['arqel-realtime.presence.channel_pattern' => 'tenant.{resource}.{recordId}']);
PresenceChannelResolver::forResource('orders', 7);
// → "tenant.orders.7"
```

```php
// Gates que consulta el autorizador:
Gate::define('viewAny', fn ($user, string $modelClass) => $user->can('view-list', $modelClass));
Gate::define('view', fn ($user, $record) => $user->id === $record->owner_id);

// Progreso de Action: el job registra al propietario antes del dispatch.
Cache::put("arqel.action.{$jobId}.user", auth()->id(), now()->addMinutes(30));

// Presencia: el Gate con nombre es OPCIONAL — sin él, el canal recae
// en la Policy `view` del registro. Defínelo solo para sobrescribir esa escalera.
Gate::define('view-resource-presence', fn ($user, string $resource, string|int $id) =>
    $user->can('view', Post::find($id)));
```

## Relacionado

- SKILL: [`packages/realtime/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/realtime/SKILL.md)
- Código fuente: [`packages/realtime/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/realtime/src/)

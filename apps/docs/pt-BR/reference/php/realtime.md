# `arqel-dev/realtime` — Referência de API

Namespace `Arqel\Realtime\`. A camada de broadcasting: eventos privados carregando um payload mínimo, uma trait opt-in que faz auto-dispatch a partir do lifecycle do `Resource`, helpers de canais de presença, um autorizador central de canais e um scaffold de edição colaborativa baseado em Yjs.

O pacote é agnóstico ao broadcaster. A stack alvo é o [Laravel Reverb](https://laravel.com/docs/reverb), mas qualquer driver que implemente `Illuminate\Contracts\Broadcasting\Broadcaster` (Pusher, Ably, log, null) funciona — o Reverb fica em `suggest:`, nunca em `require`. `arqel-dev/core` **é** dependência rígida: o pacote existe para se acoplar ao lifecycle do Resource.

## Nomenclatura de canais

| Padrão | Finalidade |
|---|---|
| `arqel.{slug}` | Listagem do Resource |
| `arqel.{slug}.{id}` | Registro individual |
| `arqel.action.{jobId}` | Progresso de Action |
| `arqel.presence.{slug}.{id}` | Roster de presença (padrão configurável) |
| `arqel.collab.{modelType}.{modelId}.{field}` | Edição colaborativa com Yjs |

Todo canal é um `PrivateChannel`; o gating vive em `routes/channels.php`, delegando aos autorizadores abaixo.

## Eventos

### `Arqel\Realtime\Events\ResourceUpdated` (final, `ShouldBroadcast`)

```php
public function __construct(
    public readonly string $resourceClass,
    public readonly Model $record,
    public readonly ?int $updatedByUserId = null,
) {}
```

`broadcastOn(): array` retorna `[PrivateChannel("arqel.{slug}"), PrivateChannel("arqel.{slug}.{id}")]`; o segundo canal é omitido quando `$record->getKey()` é `null`, o que mantém o evento seguro sobre registros não persistidos. `broadcastWith(): array` retorna `{id, updatedByUserId, updatedAt}` via `getKey()` + `getAttribute('updated_at')`, de modo que um model sem timestamps ainda serializa.

A resolução do slug é defensiva: tenta `$resourceClass::getSlug()` protegido por `method_exists` + `try/catch (Throwable)` e, em caso de falha ou de um retorno vazio/não-string, cai em `Str::of(class_basename(...))->beforeLast('Resource')->snake('-')->plural()` — espelhando `Arqel\Core\Resources\Resource::getSlug()`, para que o fallback corresponda ao slug real mesmo quando a subclasse de Resource está apenas parcialmente inicializada.

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

`broadcastOn()` retorna `[PrivateChannel("arqel.collab.{modelType}.{modelId}.{field}")]`, `broadcastAs()` retorna `'collab.update'`, e `broadcastWith()` retorna `{state, version, by_user_id}` com o state codificado em base64. Disparado por `CollabDocumentController::store` depois que o snapshot é persistido.

## `Arqel\Realtime\Concerns\BroadcastsResourceUpdates` (trait)

O único ponto de entrada para o auto-dispatch. Ela sobrescreve `protected afterUpdate(Model $record): void` no `Resource` do core e dispara `ResourceUpdated::dispatch(static::class, $record, $userId)`. Respeita o kill switch `arqel-realtime.auto_dispatch.resource_updated` e resolve o id do usuário via `auth()->id()` de forma defensiva, repassando-o apenas quando é um `int` (ids UUID / string viram `null`).

O wiring é uma trait em vez de um patch no `Resource` porque `arqel-dev/core` e `arqel-dev/realtime` são pacotes independentes — uma mutação cross-package empurraria todo consumidor do core para dentro da stack de broadcasting. Subclasses que precisam de lógica extra em `afterUpdate` precisam chamar `parent::afterUpdate($record)` para que o broadcast não seja silenciado.

## `Arqel\Realtime\Channels\ResourceChannelAuthorizer` (final readonly)

Métodos estáticos que concentram as verificações de Gate:

| Método | Assinatura | Verificação |
|---|---|---|
| `authorizeResource` | `(Authenticatable $user, string $resourceSlug): bool` | `viewAny` sobre a classe do model |
| `authorizeRecord` | `(Authenticatable $user, string $resourceSlug, int\|string $recordId): bool` | `view` sobre o registro resolvido |
| `authorizeActionJob` | `(Authenticatable $user, string $jobId): bool` | `Cache::get("arqel.action.{jobId}.user") === $user->getAuthIdentifier()` (comparação estrita) |
| `resolveRecord` | `(string $resourceSlug, int\|string $recordId): ?Model` | Helper de resolução compartilhado |
| `registryBound` | `(): bool` | Se `Arqel\Core\Resources\ResourceRegistry` está bound |
| `presenceMemberInfo` | `(Authenticatable $user): array` | `{id, name, avatar}` para o roster de presença |

Cada método envolve sua lógica em `try/catch \Throwable` e loga via `Log::warning()` — **negar por padrão**. Sem `arqel-dev/core` no container, com um registry que não tenha `findBySlug`, ou com um lookup `null`, o autorizador nega em vez de lançar exceção, preservando o acoplamento opcional.

## Presença

### `Arqel\Realtime\Presence\PresenceChannelResolver` (final readonly)

| Método | Tipo | Descrição |
|---|---|---|
| `pattern()` | `static string` | Fonte única do padrão, lida de `arqel-realtime.presence.channel_pattern`, caindo em `arqel.presence.{resource}.{recordId}` quando não é string ou está vazia |
| `forResource(string $slug, int\|string $recordId)` | `static string` | O nome concreto do canal |

`routes/channels.php` registra o canal de presença usando exatamente o mesmo `pattern()`, então registro e assinatura permanecem em sincronia mesmo com um padrão customizado. Um padrão customizado **precisa** preservar os tokens `{resource}` e `{recordId}` (o Laravel associa os argumentos do callback posicionalmente por nome); placeholders extras permanecem literais em ambos os lados. Chamar o resolver enquanto `presence.enabled === false` levanta `Exceptions\RealtimeException`.

O callback de presença é protegido por Policy por padrão, e não fail-open. A escada é: (1) um Gate nomeado `view-resource-presence` definido pela aplicação vence de imediato; (2) sem ele, a ability `view` do registro decide — quando existe um Gate `view` **ou** uma Policy registrada, `Gate::check('view', $record)` é autoritativo; (3) só abre para scaffolds genuínos — o `ResourceRegistry` não bound (realtime autônomo), ou um registro resolvido sem Gate nem Policy. Se o registry **estiver** bound mas o registro não resolver, ele nega.

## Edição colaborativa

### `Arqel\Realtime\Collab\YjsDocument` (final)

Persiste o snapshot do `Y.Doc` em `arqel_yjs_documents` (único em `(model_type, model_id, field)`). `$timestamps = false`; casts `version => integer`, `last_user_id => integer`, `updated_at => datetime`. `morphedModel(): MorphTo` aponta para o model de origem.

### `Arqel\Realtime\Collab\AwarenessChannelAuthorizer` (final readonly)

`authorize(Authenticatable $user, string $modelType, int|string $modelId, string $field): bool` autoriza `arqel.collab.{modelType}.{modelId}.{field}`. Ele resolve o model por FQCN direto, via `ResourceRegistry::all()` casando por `getModel()`, ou via `ResourceRegistry::findBySlug()` — espelhando o `CollabDocumentController`, de modo que o `{modelType}` aceito é exatamente a chave que o endpoint REST persiste e sobre a qual `YjsUpdateReceived` faz broadcast. Ele nega quando o registry não está bound e nem FQCN nem slug resolvem. A ability `view` é aplicada quando a aplicação define um Gate `view` **ou** registra uma Policy (via `Gate::getPolicyFor()`, já que `Gate::has()` nunca enxerga Policies); só libera na ausência de ambos (modo scaffold).

### `Arqel\Realtime\Http\Controllers\CollabDocumentController` (final)

| Verbo | Route | Nome | Método |
|---|---|---|---|
| GET | `/admin/{resource}/{id}/collab/{field}` | `arqel.realtime.collab.show` | `show(Request, string $resource, string $id, string $field): JsonResponse` |
| POST | `/admin/{resource}/{id}/collab/{field}` | `arqel.realtime.collab.store` | `store(Request, string $resource, string $id, string $field): JsonResponse` |

Ambas são registradas com `web` + `auth`. Cada requisição resolve o model dono a partir de `{resource}` (slug via `ResourceRegistry::findBySlug()`, ou um FQCN direto) mais `{id}` e autoriza `view` (show) / `update` (store) antes de ler ou escrever — a mesma escada ciente de Policies do autorizador de awareness. `abort(404)` quando o model ou o registro não resolve; `403` quando a ability é negada. A concorrência otimista é garantida pela coluna `version`: uma requisição cujo `version` é menor que o atual recebe `409 {message, serverVersion}`. O state é sempre transportado codificado em base64.

## Integração com workflow

Quando `arqel-dev/workflow` é autoloadable, o `RealtimeServiceProvider` registra `Workflow\BroadcastStateTransitionListener` para `Arqel\Workflow\Events\StateTransitioned`. Cada transição bem-sucedida dispara um `ResourceUpdated` carregando o mesmo `record` e `userId`. O listener resolve a classe de Resource via `ResourceRegistry::findByModel($record::class)` e a repassa como `resourceClass`, para que os canais coincidam com aqueles que a UI já assina; com o registry não bound ou sem Resource para o model, ele cai no FQCN do model.

A direção da dependência é apenas `realtime → workflow`. O wiring é defensivo: o listener só é registrado quando `class_exists('Arqel\Workflow\Events\StateTransitioned')`, e `handle(mixed $event): void` aceita `mixed`, de modo que nunca quebra quando a classe está ausente. Desative com `ARQEL_REALTIME_WORKFLOW_BROADCAST=false` ou `config()->set('arqel-realtime.workflow.broadcast_state_transitions', false)` — isso curto-circuita o `handle()` em vez de desregistrar o listener.

## Exceções

`Arqel\Realtime\Exceptions\RealtimeException` (final, estende `RuntimeException`) é a exceção de runtime base do pacote.

## Configuração

`config/arqel-realtime.php` expõe cinco blocos: `connection` (alias do broadcaster, caindo em `broadcasting.default`), `channel_prefix` (default `arqel`), `auto_dispatch.resource_updated` (default `true` — o kill switch global), `presence` com `enabled` (`ARQEL_REALTIME_PRESENCE_ENABLED`) e `channel_pattern`, e `workflow.broadcast_state_transitions` (`ARQEL_REALTIME_WORKFLOW_BROADCAST`).

## Exemplo

```php
use Arqel\Core\Resources\Resource;
use Arqel\Realtime\Concerns\BroadcastsResourceUpdates;

final class PostResource extends Resource
{
    use BroadcastsResourceUpdates;

    protected static string $model = \App\Models\Post::class;

    // ... fields, table, form. Nada mais a configurar.
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

// Um padrão customizado PRECISA manter os placeholders {resource} e {recordId}.
config(['arqel-realtime.presence.channel_pattern' => 'tenant.{resource}.{recordId}']);
PresenceChannelResolver::forResource('orders', 7);
// → "tenant.orders.7"
```

```php
// Gates que o autorizador consulta:
Gate::define('viewAny', fn ($user, string $modelClass) => $user->can('view-list', $modelClass));
Gate::define('view', fn ($user, $record) => $user->id === $record->owner_id);

// Progresso de Action: o job registra o dono antes do dispatch.
Cache::put("arqel.action.{$jobId}.user", auth()->id(), now()->addMinutes(30));

// Presença: o Gate nomeado é OPCIONAL — sem ele o canal cai na
// Policy `view` do registro. Defina-o apenas para sobrescrever essa escada.
Gate::define('view-resource-presence', fn ($user, string $resource, string|int $id) =>
    $user->can('view', Post::find($id)));
```

## Relacionados

- SKILL: [`packages/realtime/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/realtime/SKILL.md)
- Código-fonte: [`packages/realtime/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/realtime/src/)

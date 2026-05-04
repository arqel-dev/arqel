# SKILL.md — arqel-dev/realtime

> Contexto canônico para AI agents trabalhando no pacote `arqel-dev/realtime`.

## Purpose

`arqel-dev/realtime` entrega a camada de **broadcasting** do Arqel: eventos privados com payload mínimo, trait opt-in para auto-dispatch a partir do lifecycle do `Resource`, helpers de presence channel e authorizer central para todos os patterns de canal expostos pelo pacote. Cobre RF-IN-03 (atualizações em tempo real entre usuários vendo o mesmo Resource).

A escolha é **broadcaster-agnóstico**: a stack-alvo é [Laravel Reverb](https://laravel.com/docs/reverb) (oficial Laravel, default em 2026), mas qualquer driver compatível com `Illuminate\Contracts\Broadcasting\Broadcaster` (Pusher, Ably, log, null) funciona. Reverb fica em `suggest:` no composer.json, nunca em `require`. O cliente JS (Echo) virá num pacote dedicado (`@arqel-dev/realtime`) a partir de RT-003.

A integração foca em três blocos:

1. **Events broadcastáveis** — payloads stable que o lado React/Inertia subscreve via Echo.
2. **Trait de auto-dispatch** — wiring opt-in para Resource subclasses emitirem eventos sem patching cruzado de pacotes.
3. **Authorizer central** — Gate-checking concentrado e testável para todos os patterns de canal (`arqel.{slug}`, `arqel.{slug}.{id}`, `arqel.action.{jobId}`, `arqel.presence.{slug}.{id}`).

## Status

### Entregue (RT-001 .. RT-009 + RT-011/012)

**Esqueleto + Service Provider (RT-001).** `Arqel\Realtime\RealtimeServiceProvider` é auto-discovered via `extra.laravel.providers`, boota via `Spatie\LaravelPackageTools\PackageServiceProvider` (`name('arqel-realtime')`, `hasConfigFile('arqel-realtime')` + `hasRoute('channels')`) e chama `Broadcast::routes()` em `packageBooted()` (idempotente). PSR-4 `Arqel\Realtime\` → `src/`, autoload-dev `Arqel\Realtime\Tests\` → `tests/`. `composer.json` declara hard-dep em `arqel-dev/core: @dev` + traits do framework de broadcasting do Laravel; `laravel/reverb: ^1.0` listado como `suggest`. Pacote registrado em `composer.json` raiz como `arqel-dev/realtime: @dev` (alphabetical entre `arqel-dev/nav` e `arqel-dev/table`).

**Config publicável.** `config/arqel-realtime.php` expõe três blocos: `connection` (alias do broadcaster, fallback para `broadcasting.default`), `channel_prefix` (default `arqel`), `auto_dispatch.resource_updated` (default `true`, kill switch global), e `presence` com `enabled` (env `ARQEL_REALTIME_PRESENCE_ENABLED`, default `true`) + `channel_pattern` (placeholders `{resource}` e `{recordId}`).

**Events (RT-002).** `Events\ResourceUpdated` (final, implements `ShouldBroadcast`, traits `Dispatchable` + `InteractsWithSockets` + `SerializesModels`) tem construtor `string $resourceClass, Model $record, ?int $updatedByUserId = null` (todos `public readonly`). `broadcastOn()` retorna `[PrivateChannel("arqel.{slug}"), PrivateChannel("arqel.{slug}.{id}")]`; o segundo canal é omitido quando `$record->getKey()` é `null` (record não persistido — torna o evento safe em `beforeSave`-style hooks). `broadcastWith()` retorna `{id, updatedByUserId, updatedAt}` via `getKey()` + `getAttribute('updated_at')` — resilient a records sem timestamps. **Slug resolution defensiva**: tenta `$resourceClass::getSlug()` via `method_exists` + try/catch (`Throwable`); em falha (ou retorno vazio/non-string), faz fallback para `Str::of(class_basename(...))->beforeLast('Resource')->snake('-')->plural()` — espelha o algoritmo do `Arqel\Core\Resources\Resource::getSlug()` para que o slug de fallback bata com o slug "real" mesmo quando a Resource subclass está parcialmente bootstrapped.

**Trait de auto-dispatch (RT-002).** `Concerns\BroadcastsResourceUpdates` é o entry point único — sobrescreve o `protected afterUpdate(Model $record): void` do core `Resource` e dispara `ResourceUpdated::dispatch(static::class, $record, $userId)`. Honra `arqel-realtime.auto_dispatch.resource_updated` (kill switch). Resolve user id via `auth()->id()` defensivamente (só passa adiante se for `int` — UUIDs/string ids viram `null`). **Decisão arquitetural**: trait em vez de patching no `Resource` base — `arqel-dev/core` e `arqel-dev/realtime` são pacotes independentes; mutação cross-package empurraria todos os consumidores de `arqel-dev/core` para a stack de broadcasting. Trait é opt-in explícito por Resource. Subclasses que precisam de lógica adicional em `afterUpdate` chamam `parent::afterUpdate($record)` para não silenciar o broadcast.

**Presence channels (RT-004).** `routes/channels.php` registra `arqel.presence.{resource}.{recordId}` via `Broadcast::channel()`; o callback retorna `{id, name, avatar}` para o user autenticado, ou `false` quando o Gate opcional `view-resource-presence` é registrado pela app e nega. `Presence\PresenceChannelResolver` (final readonly) expõe o helper estático `forResource(string $slug, int|string $recordId): string` — lê o pattern (placeholders `{resource}` + `{recordId}`); placeholders extras (`{tenant}`, etc.) ficam literais; pattern não-string ou vazio cai no default `arqel.presence.{resource}.{recordId}`. Levanta `Exceptions\RealtimeException` quando `presence.enabled === false` (programmer error). `Exceptions\RealtimeException` (final, extends `RuntimeException`) é a base runtime exception do pacote.

**Channel authorization (RT-009).** `routes/channels.php` registra os 4 patterns canónicos (list, per-record, action progress, presence). `Channels\ResourceChannelAuthorizer` (final readonly) concentra a lógica de Gate-checking em 3 métodos estáticos:

| Pattern | Método do authorizer | Gate verificado |
| --- | --- | --- |
| `arqel.{resource}` | `authorizeResource` | `viewAny` no model class |
| `arqel.{resource}.{recordId}` | `authorizeRecord` | `view` no model record |
| `arqel.action.{jobId}` | `authorizeActionJob` | `Cache::get("arqel.action.{jobId}.user") === $user->getAuthIdentifier()` (comparação estrita) |
| `arqel.presence.{resource}.{recordId}` | callback inline (RT-004) | Gate opcional `view-resource-presence` |

Cada método encapsula a lógica em `try/catch \Throwable` e regista via `Log::warning()` em caso de falha — **defensive by default**. Sem `arqel-dev/core` no container (`app()->bound('Arqel\\Core\\Resources\\ResourceRegistry')` retorna `false`), o authorizer denega por defeito ao invés de explodir, preservando o desacoplamento opcional com o core. Registry sem método `findBySlug` ou retorno `null` também denega.

**Cobertura de testes (RT-011).** 46 testes Pest 3 (Orchestra Testbench + sqlite in-memory + broadcaster `null`):

- `Feature/RealtimeServiceProviderTest` (3): boot + config defaults + tag publishable.
- `Unit/ResourceUpdatedTest` (5): canais list+detail, omissão sem id, fallback de slug em throw, payload completo, payload com nulls.
- `Feature/BroadcastsResourceUpdatesTraitTest` (3): dispatch via `afterUpdate`, kill switch, captura `auth()->id()`.
- `Unit/Presence/PresenceChannelResolverTest` (5) + `Feature/PresenceChannelTest` (4): pattern default + custom + fallback + Gate opcional.
- `Unit/Channels/ResourceChannelAuthorizerTest` (10) + `Feature/ChannelAuthorizationTest` (5): cada método × allow/deny/registry-unbound/cache-miss.
- `Unit/Coverage/RealtimeCoverageGapsTest` (11): chave string no `broadcastWith`, slug vazio, pluralização (`Category` → `categories`), dispatch sem dirty, auth UUID coerce, placeholders extras preservados, pattern array (fallback), registry sem `findBySlug`, resource sem `getModel()`, `authorizeRecord` com registry unbound, `authorizeActionJob` com type mismatch (string vs int).

PHPStan level max clean. Pint clean.

### Collaborative editing (RT-005 + follow-up)

`arqel-dev/realtime` entrega edição colaborativa baseada em Yjs (`Y.Doc`) com sync via Reverb. A integração tem dois lados:

- **Server-side** (`arqel-dev/realtime`): persistência de snapshot via REST + canal privado `arqel.collab.{modelType}.{modelId}.{field}` autorizado pelo `Collab\AwarenessChannelAuthorizer` + evento `Events\YjsUpdateReceived` (broadcastAs `collab.update`) disparado a cada POST de snapshot.
- **Client-side** (`@arqel-dev/realtime-collab`): hook `useYjsCollab()` cria um `Y.Doc` local e subscreve ao canal Echo; componente `<CollabRichTextField>` renderiza textarea controlled com debounce (default 2s) para POST de snapshots. Decoder/encoder base64 SSR-safe em `encoders.ts`.

**Setup** (consumer Laravel app):

```bash
composer require laravel/reverb
pnpm add @arqel-dev/realtime-collab yjs
```

Configure Reverb (`php artisan reverb:install`) + `BROADCAST_CONNECTION=reverb` no `.env`. O `RealtimeServiceProvider` já registra o canal Yjs em `routes/channels.php`. Setup global de Echo via `@arqel-dev/realtime` (`setupEcho`).

O que está entregue:

| Componente | Descrição |
| --- | --- |
| Migration `2026_05_06_000000_create_yjs_documents` | Tabela `arqel_yjs_documents` com unique `(model_type, model_id, field)` + colunas `state` (longBlob), `version` (uint), `last_user_id` (nullable), `updated_at`. |
| `Collab\YjsDocument` (final Eloquent) | Model que persiste o snapshot do Y.Doc. `morphedModel()` devolve `MorphTo` para o source model. Casts `version => int`, `updated_at => datetime`. |
| `Http\Controllers\CollabDocumentController` (final) | Métodos `show()` / `store()` para `GET|POST /admin/{resource}/{id}/collab/{field}`. Optimistic concurrency via `version`: requests com `version < current` recebem `409 {message, serverVersion}`. State sempre transitado em base64. |
| `routes/api.php` | Registra os endpoints com middleware `web,auth`. |
| `Collab\AwarenessChannelAuthorizer` (final readonly) | Autoriza o canal `arqel.collab.{modelType}.{modelId}.{field}`. Resolve o model via FQCN direto ou via `ResourceRegistry::all()` (defensive — denega quando registry unbound + FQCN não resolve). Honra Gate `view` quando definida; allow caso contrário. |
| `Events\YjsUpdateReceived` (final, ShouldBroadcast) | Disparado em `CollabDocumentController::store` após persistir. `broadcastOn` = `[PrivateChannel("arqel.collab.{modelType}.{modelId}.{field}")]`, `broadcastAs` = `collab.update`, `broadcastWith` = `{state, version, by_user_id}` (state base64). |
| `routes/channels.php` (atualizado) | Registra o canal `arqel.collab.{modelType}.{modelId}.{field}` delegando ao authorizer. |
| `@arqel-dev/realtime-collab` (npm) | Cliente JS: hook `useYjsCollab`, componente `<CollabRichTextField>`, helpers `encodeUpdate`/`decodeUpdate`. Subscreve ao canal Echo + persiste snapshots com debounce. SSR-safe. |

**Fluxo de integração esperado** (user-land, futuro `<CollabRichTextField />`):

```tsx
// Pseudo-API — virá com o cliente @arqel-dev/realtime + Yjs.
import * as Y from 'yjs';
import { WebsocketProvider } from 'y-websocket';

export function CollabRichTextField({ resource, recordId, field }: Props) {
    const doc = new Y.Doc();
    // Snapshot inicial via REST scaffold:
    fetch(`/admin/${resource}/${recordId}/collab/${field}`)
        .then((r) => r.json())
        .then(({ state, version }) => {
            if (state) Y.applyUpdate(doc, base64ToBytes(state));
            // Live sync por WebSocket (Reverb) fica fora deste scaffold.
            new WebsocketProvider(`wss://reverb.example.com`, `arqel.collab.${resource}.${recordId}.${field}`, doc);
        });
    // ... bind doc.getText('content') ao editor (TipTap/ProseMirror).
}
```

O endpoint REST cobre o caso *snapshot persistence*: o cliente envia `{state, version}` ao consolidar (debounced, ~ a cada 5s ou no `beforeunload`); o servidor rejeita escritas obsoletas. **Sync delta-by-delta** (cada keystroke) fica por conta do WebSocket layer — Yjs trata merge de updates concorrentes via CRDT, então o snapshot persistido é seguro mesmo com múltiplos editores ativos.

**Optimistic concurrency**: cliente armazena última `version` recebida; ao gravar, envia `{state: base64(Y.encodeStateAsUpdate(doc)), version: lastSeenVersion}`. Server retorna `{version: newVersion}` ou `409`. Em conflito, cliente faz GET fresco, aplica sua merge local via `Y.mergeUpdates`, refaz o POST.

### Workflow integration (RT-cross)

Quando `arqel-dev/workflow` está presente no autoload, o `RealtimeServiceProvider` registra automaticamente o listener `Workflow\BroadcastStateTransitionListener` para o evento `Arqel\Workflow\Events\StateTransitioned`. A cada transição de state bem-sucedida, o listener despacha um `Events\ResourceUpdated` carregando o mesmo `record` + `userId` do evento de origem. Os canais resultantes são derivados pelo `ResourceUpdated::broadcastOn()` a partir do FQCN do model (`arqel.{slug}` + `arqel.{slug}.{id}`), de modo que toda UI já assinante de um Resource cuja Eloquent model use `HasWorkflow` recebe a notificação sem código adicional.

A direção da dependência é `realtime → workflow` (apenas `realtime` "sabe" da existência opcional de `workflow`); o pacote `arqel-dev/workflow` permanece standalone e ignora `realtime`. A wiring é defensiva: o listener é registrado somente se `class_exists('Arqel\\Workflow\\Events\\StateTransitioned')` retornar `true`, e o `handle()` aceita `mixed $event` para nunca quebrar quando a class não está carregada.

**Como desativar.** Defina `ARQEL_REALTIME_WORKFLOW_BROADCAST=false` no `.env`, ou em runtime:

```php
config()->set('arqel-realtime.workflow.broadcast_state_transitions', false);
```

Útil para suprimir broadcasts em jobs de migração ou seeders que disparam muitas transições em cascata. A configuração não desregistra o listener — apenas o curto-circuita no início do `handle()`.

### Entregue (RT-001 → RT-012)

- **RT-003** — React hook `useResourceUpdates` + Inertia `router.reload` em `@arqel-dev/realtime`.
- **RT-004** — hook `useResourcePresence` bindando Echo presence channels via `PresenceChannelResolver`.
- **RT-005** — `<CollabRichTextField />` + Yjs sync sobre canal Reverb dedicado; integração Yjs-aware no `<RichTextField />`.
- **RT-007** — progress streaming (`arqel.action.{jobId}` payload + React subscriber).
- **RT-008** — connection resilience banner (`<RealtimeStatus />` no Panel chrome com states `connecting`/`reconnecting`).

### Pendente

- **E2E real com Reverb** — requer infra (Redis + Reverb worker no CI). Hoje todos os testes rodam com `BROADCAST_CONNECTION=null` + `Event::fake()`.
- **`BroadcastAuthController`** — para apps que não usam o boilerplate `routes/channels.php` padrão.

## Conventions

- `declare(strict_types=1)` obrigatório em todos os arquivos PHP.
- `ResourceUpdated`, `RealtimeServiceProvider`, `PresenceChannelResolver`, `ResourceChannelAuthorizer`, `RealtimeException` são `final`. Traits (`BroadcastsResourceUpdates`) são consumidas por user-land Resource subclasses, portanto não-final.
- **Channel naming**: `arqel.{slug}` (lista) + `arqel.{slug}.{id}` (detalhe) + `arqel.action.{jobId}` (progresso) + `arqel.presence.{slug}.{id}` (presence). Sempre `PrivateChannel` — gating de autorização vive em `routes/channels.php` delegando ao `ResourceChannelAuthorizer`.
- **Slug resolution defensiva** — qualquer evento que aceite `string $resourceClass` deve tentar `getSlug()` via `method_exists` + try/catch e ter fallback por basename. Isto isola o evento de cenários de inicialização parcial (testes, seeders, jobs queued antes do app boot completo).
- **Authorization deny-by-default** — `ResourceChannelAuthorizer` retorna `false` em qualquer caminho de erro (registry unbound, slug não-resolvido, model não encontrado, `Throwable` na resolução). Failures são logged via `Log::warning()` para auditoria.
- **Hard dep em `arqel-dev/core: @dev`** — o pacote existe para integrar com o lifecycle do Resource. Sem `arqel-dev/core` o trait não tem `afterUpdate` para sobrescrever; o authorizer trata `app()->bound(ResourceRegistry::class)` como `false` (deny).
- **Reverb é `suggest`, não `require`** — manter o pacote leve para apps que usam Pusher.com ou Ably (broadcasters managed). Os testes rodam sem Reverb instalado via `Event::fake()` + `BROADCAST_CONNECTION=null`.

## Anti-patterns

- **Patchar `Arqel\Core\Resources\Resource` para dispatch automático** — mutação cross-package quebra a separação de concerns; use a trait `BroadcastsResourceUpdates` opt-in.
- **Broadcastar payload completo do model** — `broadcastWith()` deve devolver apenas o necessário para o consumer decidir se recarrega (`router.reload()`). Detalhes vão pelo Inertia normal. Evita vazar atributos sensíveis pelo WebSocket.
- **`Channel` público** — Resources são autenticados; sempre `PrivateChannel`. Gating real fica em `routes/channels.php` consumindo `ResourceChannelAuthorizer`. Nunca devolva `true` cego de um callback de canal — sempre delegue ao authorizer (ou Gate explícito).
- **Disparar `ResourceUpdated` em `beforeSave` ou em jobs sem record persistido** — o segundo canal precisa de `id`; sem ele a UI não consegue rotear a notificação. Se o caso de uso exige antes do save, considere um evento dedicado (ex: `ResourceCreating`).
- **Disparar `ResourceUpdated` em hot loops de import/seed** — o cost-per-event é real (broadcast + serialização + WebSocket round-trip). Em batch imports, desativar via `config(['arqel-realtime.auto_dispatch.resource_updated' => false])` ou usar `Resource::withoutEvents(...)` (custom helper).
- **Confiar no `connection` config para sobrescrever broadcasters por evento** — Laravel resolve broadcaster globalmente via `broadcasting.default`. A chave `connection` no `arqel-realtime.php` é placeholder para futura integração; use `BROADCAST_CONNECTION` no `.env` por enquanto.
- **Esquecer cleanup de listeners no React** — o hook `useResourceUpdates` (RT-003) deve sempre desinscrever em `useEffect` cleanup, sob pena de memory leaks e subscrições zombie quando o componente desmonta.

## Examples

### Setup mínimo + Echo

```php
use Arqel\Core\Resources\Resource;
use Arqel\Realtime\Concerns\BroadcastsResourceUpdates;

final class PostResource extends Resource
{
    use BroadcastsResourceUpdates;

    public static string $model = \App\Models\Post::class;

    // ... fields, table, form. Nada mais a fazer.
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

### Resource updates broadcasting

Lado React (RT-003, futuro):

```ts
// pseudo-API — virá com RT-003
import { useResourceUpdates } from '@arqel-dev/realtime';
import { router } from '@inertiajs/react';

useResourceUpdates('posts', { onUpdate: () => router.reload({ only: ['posts'] }) });
```

### Presence channel custom

```php
use Arqel\Realtime\Presence\PresenceChannelResolver;

// Resolve o nome do canal (idêntico ao registrado em routes/channels.php):
$channel = PresenceChannelResolver::forResource('posts', 42);
// → "arqel.presence.posts.42"

// Pattern custom via config (placeholders extras ficam literais):
config(['arqel-realtime.presence.channel_pattern' => 'tenant.{tenant}.{resource}-{recordId}']);
PresenceChannelResolver::forResource('orders', 7);
// → "tenant.{tenant}.orders-7"
```

### Channel authorization custom

```php
// Gates registrados pela app (em AuthServiceProvider ou similar):
Gate::define('viewAny', fn ($user, string $modelClass) => $user->can('view-list', $modelClass));
Gate::define('view', fn ($user, $record) => $user->id === $record->owner_id);

// Action progress: o job grava o owner antes de dispatch.
Cache::put("arqel.action.{$jobId}.user", auth()->id(), now()->addMinutes(30));

// Presence opcional (somente quando definido — undefined = allow):
Gate::define('view-resource-presence', fn ($user, string $resource, string|int $id) =>
    $user->can('view', app(\App\Models\Post::class)->find($id))
);
```

### Connection resilience banner (RT-008, futuro)

```tsx
// Pseudo-API — virá com RT-008. Echo emite 'connecting'/'reconnecting'/'connected'.
<RealtimeStatus
    onDisconnected={() => toast('Real-time updates paused — reconnecting…')}
/>
```

## Related

- Tickets: [`PLANNING/10-fase-3-avancadas.md`](../../PLANNING/10-fase-3-avancadas.md) §RT-001..012
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §Realtime (futuro)
- Source: [`packages/realtime/src/`](./src/)
- Tests: [`packages/realtime/tests/`](./tests/)
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only (broadcasting é o canal alternativo permitido apenas para invalidação de cache).
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3.
- Externos:
  - [`laravel/reverb`](https://laravel.com/docs/reverb) (suggest — broadcaster recomendado).
  - [`laravel/echo`](https://laravel.com/docs/broadcasting#client-side-installation) (consumer JS, virá com RT-003).

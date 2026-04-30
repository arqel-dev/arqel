# SKILL.md — arqel/realtime

> Contexto canónico para AI agents.

## Purpose

`arqel/realtime` é a camada de broadcasting do Arqel. Cobre RF-IN-03 (atualizações em tempo real entre usuários vendo o mesmo Resource). Stack-alvo: **Laravel Reverb** como WebSocket server (oficial Laravel, default em 2026), mas qualquer broadcaster compatível com `Illuminate\Contracts\Broadcasting\Broadcaster` (Pusher, Ably, log, null) funciona — Reverb é `suggest`, não `require`.

A integração foca em três blocos:

1. **Events broadcastáveis** — payloads stable que o lado React/Inertia subscreve via Echo.
2. **Trait de auto-dispatch** — wiring opt-in para Resource subclasses emitirem eventos sem patching cruzado de pacotes.
3. **Config publicável** — `config/arqel-realtime.php` define prefixo de canal, conexão de broadcasting e flags globais de auto-dispatch.

## Status

**Entregue (RT-001 — esqueleto):**

- Esqueleto `arqel/realtime` com PSR-4 `Arqel\Realtime\` → `src/`, autoload-dev `Arqel\Realtime\Tests\` → `tests/`
- **`Arqel\Realtime\RealtimeServiceProvider`** auto-discovered via `extra.laravel.providers`. Boota via `Spatie\LaravelPackageTools\PackageServiceProvider` (`name('arqel-realtime')`, `hasConfigFile('arqel-realtime')`)
- **`config/arqel-realtime.php`** publicável com chaves: `connection` (alias do broadcaster, fallback para `broadcasting.default`), `channel_prefix` (default `arqel`), `auto_dispatch.resource_updated` (default `true`)
- `composer.json`: depende de `arqel/core: @dev` + traits do framework de broadcasting do Laravel (`illuminate/broadcasting`, `illuminate/contracts`, `illuminate/database`, `illuminate/queue`, `illuminate/support`); `laravel/reverb: ^1.0` listado como `suggest`
- Pacote registrado em `composer.json` raiz como `arqel/realtime: @dev` (alphabetical entre `arqel/nav` e `arqel/table`)

**Entregue (RT-002 — `ResourceUpdated` event + trait de auto-dispatch):**

- **`Arqel\Realtime\Events\ResourceUpdated`** (final, implements `ShouldBroadcast`, traits `Dispatchable`, `InteractsWithSockets`, `SerializesModels`):
  - Construtor exatamente como o spec: `string $resourceClass`, `Model $record`, `?int $updatedByUserId = null` (todos `public readonly`)
  - `broadcastOn()` → `[PrivateChannel("arqel.{slug}"), PrivateChannel("arqel.{slug}.{id}")]`
  - **Slug resolution defensiva**: tenta `$resourceClass::getSlug()` via `method_exists` + try/catch (`Throwable`). Em falha, faz fallback para `Str::of(class_basename(...))->beforeLast('Resource')->snake('-')->plural()` — espelha o algoritmo do `Arqel\Core\Resources\Resource::getSlug()` para que o slug de fallback bata com o slug "real" mesmo quando a Resource subclass está parcialmente bootstrapped
  - **Defesa contra record sem id**: `$record->getKey()` null → segundo canal omitido (só `arqel.{slug}` é broadcastado)
  - `broadcastWith()` → `{id, updatedByUserId, updatedAt}` usando `getKey()` + `getAttribute('updated_at')` (resilient para records sem timestamps)
- **`Arqel\Realtime\Concerns\BroadcastsResourceUpdates`** trait:
  - Override de `protected afterUpdate(Model $record): void` — disparado pelo `Arqel\Core\Resources\Resource::runUpdate()`
  - Honra `arqel-realtime.auto_dispatch.resource_updated` (kill switch global)
  - Resolve user id via `auth()->id()` defensivamente (só passa adiante se for `int`)
  - **Decisão arquitetural**: trait em vez de patching no `Resource` base — `arqel/core` e `arqel/realtime` são pacotes independentes; mutação cross-package empurraria todos os consumidores de `arqel/core` para a stack de broadcasting. Trait é opt-in explícito por Resource
- Testes Pest 3 + Orchestra Testbench:
  - `Feature/RealtimeServiceProviderTest` (3): provider boota + config carrega defaults + tag publishable registrada
  - `Unit/ResourceUpdatedTest` (5): canais list+detail + omissão quando id null + fallback de slug em throw + payload `broadcastWith` completo + payload com nulls
  - `Feature/BroadcastsResourceUpdatesTraitTest` (3): dispatch via `afterUpdate` + kill switch via config + captura de `auth()->id()`
  - **Total: 11 testes** (assumindo `composer install` no pacote)
- `TestCase` registra `RealtimeServiceProvider`, sqlite `:memory:`, broadcaster `null`, e cria a tabela `fake_resource_records` para os fixtures

**Entregue (RT-004 — Presence channels, PHP slice):**

- **`routes/channels.php`** registra o canal `arqel.presence.{resource}.{recordId}` via `Broadcast::channel()`. O callback retorna `{id, name, avatar}` para o user autenticado, ou `false` quando o Gate opcional `view-resource-presence` é registrado pela app e nega
- **`Arqel\Realtime\Presence\PresenceChannelResolver`** (final readonly) — helper estático `forResource(string $slug, int|string $recordId): string`. Lê `arqel-realtime.presence.channel_pattern` (placeholders `{resource}` + `{recordId}`) e levanta `Arqel\Realtime\Exceptions\RealtimeException` se `presence.enabled` estiver `false`
- **`Arqel\Realtime\Exceptions\RealtimeException`** (final, extends `RuntimeException`) — base runtime exception do pacote
- **`config/arqel-realtime.php`** ganha bloco `presence` com `enabled` (env `ARQEL_REALTIME_PRESENCE_ENABLED`, default `true`) e `channel_pattern`
- **`RealtimeServiceProvider`** agora registra o file de canais via `->hasRoute('channels')` e chama `Broadcast::routes()` em `packageBooted()` (idempotente)
- **9 testes adicionais** (Pest 3): `Unit\Presence\PresenceChannelResolverTest` (5) + `Feature\PresenceChannelTest` (4). Total do pacote: **20 testes**

### Presence channels (RT-004) — exemplo

```php
use Arqel\Realtime\Presence\PresenceChannelResolver;

// Resolve o nome do canal (idêntico ao registrado em routes/channels.php):
$channel = PresenceChannelResolver::forResource('posts', 42);
// → "arqel.presence.posts.42"

// Autorização opcional via Gate na app consumidora:
Gate::define('view-resource-presence', fn ($user, string $resource, string|int $id) =>
    $user->can('view', app(\App\Models\Post::class)->find($id))
);
```

**Entregue (RT-009 — Broadcasting authorization middleware):**

- **`routes/channels.php`** registra os 4 patterns canónicos do Arqel: list, per-record, action progress e presence
- **`Arqel\Realtime\Channels\ResourceChannelAuthorizer`** (final readonly) — helper estático com 3 métodos (`authorizeResource`, `authorizeRecord`, `authorizeActionJob`) que concentra a lógica de Gate-checking. Os callbacks em `routes/channels.php` apenas delegam, mantendo o auth testável sem broadcast machinery
- **Defensive by default**: cada método encapsula a lógica em `try/catch \Throwable` e regista via `Log::warning()` em caso de falha. Sem `arqel/core` no container (`app()->bound(...)` retorna `false`), o authorizer denega por defeito ao invés de explodir — preservando o desacoplamento opcional com o core
- **13 testes adicionais** (10 unit + 3 feature). Total do pacote: **35 testes**

### Channel authorization (RT-009) — exemplo

| Pattern | Método do authorizer | Gate verificado |
| --- | --- | --- |
| `arqel.{resource}` | `authorizeResource` | `viewAny` no model class |
| `arqel.{resource}.{recordId}` | `authorizeRecord` | `view` no model record |
| `arqel.action.{jobId}` | `authorizeActionJob` | `Cache::get("arqel.action.{jobId}.user") === $user->getAuthIdentifier()` |
| `arqel.presence.{resource}.{recordId}` | callback inline (RT-004) | Gate opcional `view-resource-presence` |

```php
// App-level Gate definitions (em AuthServiceProvider ou similar):
Gate::define('viewAny', fn ($user, string $modelClass) => $user->can('view-list', $modelClass));
Gate::define('view', fn ($user, $record) => $user->id === $record->owner_id);

// Action progress: o job grava o owner antes de dispatch.
Cache::put("arqel.action.{$jobId}.user", auth()->id(), now()->addMinutes(30));
```

**Diferido (RT-003+, fora do escopo deste batch):**

- React hook `useResourceUpdates` + Inertia `router.reload` (RT-003) — Camada `react`, fica para o próximo ticket JS
- React hook `useResourcePresence` (RT-004 React slice) — bind a Echo presence channels usando o nome derivado de `PresenceChannelResolver`
- Yjs / collaborative editing integration (RT-005+)
- `BroadcastAuthController` para autorizar canais privados quando o app não usa o boilerplate `routes/channels.php` padrão
- `Channels/ResourceChannel` e `Channels/ActionProgressChannel` — gates com `Resource::can('view', $record)` + tenancy awareness

## Conventions

- `declare(strict_types=1)` obrigatório
- Classes `final` por padrão (eventos e service provider). Traits são consumidas por user-land Resource subclasses
- **Trait `BroadcastsResourceUpdates` é o entry point único** para auto-dispatch — aplique no Resource (`use BroadcastsResourceUpdates;`) e o `afterUpdate` lifecycle hook do core dispara o evento sozinho. Subclasses que precisam de lógica adicional em `afterUpdate` devem chamar `parent::afterUpdate($record)` para não silenciar o broadcast
- **Channel naming**: `arqel.{slug}` (lista) + `arqel.{slug}.{id}` (detalhe). Sempre `PrivateChannel` — gating de autorização vive em `routes/channels.php` do app consumidor (ou em `BroadcastAuthController` futuro)
- **Slug resolution defensiva** — qualquer evento que aceite `string $resourceClass` deve tentar `getSlug()` via `method_exists` + try/catch e ter fallback por basename. Isto isola o evento de cenários de inicialização parcial (testes, seeders, jobs queued antes do app boot completo)
- **Hard dep em `arqel/core: @dev`** — o pacote existe para integrar com o lifecycle do Resource. Sem `arqel/core` o trait não tem `afterUpdate` para sobrescrever
- **Reverb é `suggest`, não `require`** — manter o pacote leve para apps que usam Pusher.com ou Ably (broadcasters managed). Os testes rodam sem Reverb instalado via `Event::fake()` + `BROADCAST_CONNECTION=null`

## Examples

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

Lado React (RT-003, futuro):

```ts
// pseudo-API — virá com RT-003
useResourceUpdates('posts', { onUpdate: () => router.reload() });
```

## Anti-patterns

- ❌ **Patchar `Arqel\Core\Resources\Resource` para dispatch automático** — mutação cross-package quebra a separação de concerns; use a trait `BroadcastsResourceUpdates` opt-in
- ❌ **Broadcastar payload completo do model** — `broadcastWith()` deve devolver apenas o necessário para o consumer decidir se recarrega (`router.reload()`). Detalhes vão pelo Inertia normal. Evita vazar atributos sensíveis pelo WebSocket
- ❌ **`Channel` público** — Resources são autenticados; sempre `PrivateChannel`. Gating real fica em `routes/channels.php` consumindo `Resource::can('view', $record)`
- ❌ **Disparar `ResourceUpdated` em `beforeSave` ou em jobs sem record persistido** — o segundo canal precisa de `id`; sem ele a UI não consegue rotear a notificação. Se o caso de uso exige antes do save, considere um evento dedicado (ex: `ResourceCreating`)
- ❌ **Confiar no `connection` config para sobrescrever broadcasters por evento** — Laravel resolve broadcaster globalmente via `broadcasting.default`. A chave `connection` no `arqel-realtime.php` é placeholder para futura integração; use `BROADCAST_CONNECTION` no `.env` por enquanto

## Related

- Tickets: [`PLANNING/10-fase-3-avancadas.md`](../../PLANNING/10-fase-3-avancadas.md) §RT-001..010
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §Realtime (futuro)
- Source: [`packages/realtime/src/`](./src/)
- Tests: [`packages/realtime/tests/`](./tests/)
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only (broadcasting é o canal alternativo permitido apenas para invalidação de cache)
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3
- Externos:
  - [`laravel/reverb`](https://laravel.com/docs/reverb) (suggest — broadcaster recomendado)
  - [`laravel/echo`](https://laravel.com/docs/broadcasting#client-side-installation) (consumer JS, virá com RT-003)

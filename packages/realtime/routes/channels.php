<?php

declare(strict_types=1);

use Arqel\Realtime\Channels\ResourceChannelAuthorizer;
use Arqel\Realtime\Collab\AwarenessChannelAuthorizer;
use Arqel\Realtime\Presence\PresenceChannelResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------
| Arqel Realtime broadcast channels
|--------------------------------------------------------------------------
|
| Quatro patterns de canal são registrados aqui:
|
|   1. `arqel.{resource}`              — list-level (RT-009)
|   2. `arqel.{resource}.{recordId}`   — per-record (RT-009)
|   3. `arqel.action.{jobId}`          — action progress (RT-009)
|   4. `arqel.presence.{resource}.{recordId}` — presence (RT-004)
|
| Os 3 primeiros delegam ao `ResourceChannelAuthorizer` (testável de
| forma isolada). O presence channel mantém o callback inline porque
| precisa devolver o payload `{id, name, avatar}` (não bool).
|
| O pattern do presence channel é derivado de
| `PresenceChannelResolver::pattern()` (config
| `arqel-realtime.presence.channel_pattern`), o MESMO valor que o
| resolver usa para construir o canal que o cliente assina. Assim um
| pattern customizado mantém registro e assinatura em sincronia
| (issue #130). Um pattern custom DEVE preservar os placeholders
| `{resource}`/`{recordId}` (Laravel faz binding posicional por nome).
|
*/

Broadcast::channel(
    'arqel.{resource}',
    static function (Authenticatable $user, string $resource): bool {
        return ResourceChannelAuthorizer::authorizeResource($user, $resource);
    },
);

Broadcast::channel(
    'arqel.{resource}.{recordId}',
    static function (Authenticatable $user, string $resource, int|string $recordId): bool {
        return ResourceChannelAuthorizer::authorizeRecord($user, $resource, $recordId);
    },
);

Broadcast::channel(
    'arqel.action.{jobId}',
    static function (Authenticatable $user, string $jobId): bool {
        return ResourceChannelAuthorizer::authorizeActionJob($user, $jobId);
    },
);

Broadcast::channel(
    'arqel.collab.{modelType}.{modelId}.{field}',
    static function (Authenticatable $user, string $modelType, int|string $modelId, string $field): bool {
        return app(AwarenessChannelAuthorizer::class)
            ->authorize($user, $modelType, $modelId, $field);
    },
);

/*
| Presence channel authorization (issue #239 — SECURITY).
|
| O callback antigo só consultava a Gate nomeada `view-resource-presence`.
| Quando a app NÃO define essa Gate (o default — nada a regista), o
| `Gate::has(...)` é false, o `&&` curto-circuita e o callback devolvia o
| array de member-info (autorizado). Resultado: qualquer user autenticado
| entrava no presence channel de qualquer record → roster (id/name/avatar)
| vazava cross-user/tenant. Mesmo fail-open que o `AwarenessChannelAuthorizer`
| foi endurecido a evitar.
|
| Fix (Option B — a mais segura):
|   1. Gate nomeada `view-resource-presence` definida → preserva o
|      comportamento atual exatamente (apps que a usam ficam inalteradas).
|   2. Sem a Gate nomeada → endurece via a Policy `view` do record (espelha
|      `AwarenessChannelAuthorizer`): com Gate `view` OU Policy registada,
|      decide pelo `check('view', $record)`.
|   3. Se o ResourceRegistry ESTÁ bound (app Arqel real) mas o record não
|      resolveu (slug/id suspeito) → nega. Só abre em scaffold genuíno:
|      registry unbound (realtime standalone, sem core/Policy possível) OU
|      record resolvido sem Gate `view` nem Policy.
*/
Broadcast::channel(
    PresenceChannelResolver::pattern(),
    /**
     * @return array{id: int|string|null, name: string|null, avatar: string|null}|false
     */
    static function (Authenticatable $user, string $resource, string $recordId): array|false {
        // 1) Explicit named gate: preserve the current behavior for apps that use it.
        if (Gate::has('view-resource-presence')) {
            return Gate::forUser($user)->allows('view-resource-presence', [$resource, $recordId])
                ? ResourceChannelAuthorizer::presenceMemberInfo($user)
                : false;
        }

        // 2) No named gate: harden via the record's `view` Policy (mirrors AwarenessChannelAuthorizer).
        $record = ResourceChannelAuthorizer::resolveRecord($resource, $recordId);
        if ($record !== null && (Gate::has('view') || Gate::getPolicyFor($record) !== null)) {
            return Gate::forUser($user)->check('view', $record)
                ? ResourceChannelAuthorizer::presenceMemberInfo($user)
                : false;
        }

        // 3) Option B: registry bound (real Arqel app) but record didn't resolve → deny.
        if (ResourceChannelAuthorizer::registryBound() && $record === null) {
            return false;
        }

        return ResourceChannelAuthorizer::presenceMemberInfo($user);
    },
);

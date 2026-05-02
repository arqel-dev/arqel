<?php

declare(strict_types=1);

use Arqel\Realtime\Channels\ResourceChannelAuthorizer;
use Arqel\Realtime\Collab\AwarenessChannelAuthorizer;
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

Broadcast::channel(
    'arqel.presence.{resource}.{recordId}',
    /**
     * @return array{id: int|string|null, name: string|null, avatar: string|null}|false
     */
    static function (Authenticatable $user, string $resource, string $recordId): array|false {
        if (
            Gate::has('view-resource-presence')
            && ! Gate::forUser($user)->allows('view-resource-presence', [$resource, $recordId])
        ) {
            return false;
        }

        return [
            'id' => $user->getAuthIdentifier(),
            'name' => $user->name ?? null, // @phpstan-ignore-line property.notFound
            'avatar' => $user->avatar_url ?? null, // @phpstan-ignore-line property.notFound
        ];
    },
);

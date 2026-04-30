<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------
| Arqel Realtime presence channel
|--------------------------------------------------------------------------
|
| Registers `arqel.presence.{resource}.{recordId}` so consumers can track
| which authenticated users are currently viewing a given Resource record
| (RT-004). Authorization defers to the optional `view-resource-presence`
| Gate when the host application has registered it; otherwise any
| authenticated user is allowed (returning a non-null payload).
|
*/

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

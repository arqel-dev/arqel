<?php

declare(strict_types=1);

use Arqel\Realtime\Tests\Fixtures\FakePresenceUser;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

/**
 * Invoke the presence channel callback that the package registers via
 * `routes/channels.php`. Returns the raw payload (array | false | null)
 * the channel closure produced for the given user + parameters.
 *
 * @return array{id: int|string|null, name: string|null, avatar: string|null}|false|null
 */
function invokePresenceChannel(
    string $pattern,
    FakePresenceUser $user,
    string $resource,
    string $recordId,
): array|false|null {
    /** @var Broadcaster $broadcaster */
    $broadcaster = Broadcast::driver();

    /** @var array<string, callable> $channels */
    $channels = $broadcaster->getChannels();

    expect($channels)->toHaveKey($pattern);

    $callback = $channels[$pattern];
    expect(is_callable($callback))->toBeTrue();

    /** @var array{id: int|string|null, name: string|null, avatar: string|null}|false|null $result */
    $result = $callback($user, $resource, $recordId);

    return $result;
}

it('registers the arqel.presence.{resource}.{recordId} channel', function (): void {
    /** @var Broadcaster $broadcaster */
    $broadcaster = Broadcast::driver();

    expect($broadcaster->getChannels())
        ->toHaveKey('arqel.presence.{resource}.{recordId}');
});

it('returns the user payload for an authenticated user without a Gate', function (): void {
    $user = new FakePresenceUser(id: 99, name: 'Grace Hopper', avatar_url: 'https://x.test/g.png');

    $payload = invokePresenceChannel(
        'arqel.presence.{resource}.{recordId}',
        $user,
        'posts',
        '42',
    );

    expect($payload)->toBe([
        'id' => 99,
        'name' => 'Grace Hopper',
        'avatar' => 'https://x.test/g.png',
    ]);
});

it('returns false when the view-resource-presence Gate denies', function (): void {
    Gate::define('view-resource-presence', static fn (): bool => false);

    $payload = invokePresenceChannel(
        'arqel.presence.{resource}.{recordId}',
        new FakePresenceUser,
        'posts',
        '42',
    );

    expect($payload)->toBeFalse();
});

it('returns the payload when the view-resource-presence Gate allows', function (): void {
    Gate::define(
        'view-resource-presence',
        static fn (FakePresenceUser $u, string $resource, string $recordId): bool => $resource === 'posts' && $recordId === '42',
    );

    $payload = invokePresenceChannel(
        'arqel.presence.{resource}.{recordId}',
        new FakePresenceUser(id: 7, name: 'Ada', avatar_url: null),
        'posts',
        '42',
    );

    expect($payload)->toMatchArray([
        'id' => 7,
        'name' => 'Ada',
        'avatar' => null,
    ]);
});

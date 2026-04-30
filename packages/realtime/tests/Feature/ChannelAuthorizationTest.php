<?php

declare(strict_types=1);

use Arqel\Realtime\Tests\Fixtures\FakePresenceUser;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Broadcast;

/**
 * Feature-level coverage for the channels registered by RT-009 in
 * `routes/channels.php`. Asserts that the registrations exist and
 * that the callbacks delegate to `ResourceChannelAuthorizer` (we
 * confirm by exercising the unbound-registry path which deny-by-default).
 */
/**
 * @return array<string, callable>
 */
function realtimeChannels(): array
{
    /** @var Broadcaster $broadcaster */
    $broadcaster = Broadcast::driver();

    $channels = $broadcaster->getChannels();

    if ($channels instanceof Collection) {
        /** @var array<string, callable> $arr */
        $arr = $channels->all();

        return $arr;
    }

    /** @var array<string, callable> $channels */
    return $channels;
}

it('registers the arqel.{resource} channel', function (): void {
    expect(realtimeChannels())->toHaveKey('arqel.{resource}');
});

it('registers the arqel.{resource}.{recordId} channel', function (): void {
    expect(realtimeChannels())->toHaveKey('arqel.{resource}.{recordId}');
});

it('registers the arqel.action.{jobId} channel', function (): void {
    expect(realtimeChannels())->toHaveKey('arqel.action.{jobId}');
});

it('arqel.{resource} callback denies when ResourceRegistry is not bound', function (): void {
    $channels = realtimeChannels();
    $callback = $channels['arqel.{resource}'];

    $user = new FakePresenceUser;

    expect($callback($user, 'posts'))->toBeFalse();
});

it('arqel.action.{jobId} callback denies on cache miss', function (): void {
    $channels = realtimeChannels();
    $callback = $channels['arqel.action.{jobId}'];

    $user = new FakePresenceUser(id: 1);

    expect($callback($user, 'unknown-job'))->toBeFalse();
});

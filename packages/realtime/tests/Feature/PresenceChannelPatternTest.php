<?php

declare(strict_types=1);

use Arqel\Realtime\Presence\PresenceChannelResolver;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Broadcast;

/**
 * Regression coverage for issue #130: the presence channel REGISTRATION in
 * `routes/channels.php` must derive from the same
 * `arqel-realtime.presence.channel_pattern` config key the
 * {@see PresenceChannelResolver} reads, otherwise a custom pattern makes the
 * resolver emit a channel the broadcast auth never registered (presence
 * fails closed).
 */

/**
 * Re-evaluate `routes/channels.php` so the presence registration reflects the
 * currently configured pattern. Re-requiring is safe: `Broadcast::channel()`
 * overwrites prior registrations keyed by the same name.
 *
 * @return array<string, callable>
 */
function reloadRealtimeChannels(): array
{
    require __DIR__.'/../../routes/channels.php';

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

it('exposes the configured presence pattern through a shared helper', function (): void {
    expect(PresenceChannelResolver::pattern())
        ->toBe('arqel.presence.{resource}.{recordId}');

    config()->set('arqel-realtime.presence.channel_pattern', 'tenant.{resource}.{recordId}');

    expect(PresenceChannelResolver::pattern())
        ->toBe('tenant.{resource}.{recordId}');
});

it('registers the presence channel under the configured custom pattern', function (): void {
    config()->set('arqel-realtime.presence.channel_pattern', 'tenant.{resource}.{recordId}');

    $channels = reloadRealtimeChannels();

    // The channel the broadcast auth registers must match the helper-derived
    // pattern (placeholder form), not the hardcoded default literal.
    expect($channels)->toHaveKey('tenant.{resource}.{recordId}');
});

it('registers exactly the channel template the resolver emits under a custom pattern', function (): void {
    config()->set('arqel-realtime.presence.channel_pattern', 'tenant.{resource}.{recordId}');

    $channels = reloadRealtimeChannels();

    // Resolver output for a concrete record must be authorizable: its
    // placeholder template (= the helper pattern) must be a registered key.
    $resolved = PresenceChannelResolver::forResource('posts', 5);
    expect($resolved)->toBe('tenant.posts.5');

    expect($channels)->toHaveKey(PresenceChannelResolver::pattern());
});

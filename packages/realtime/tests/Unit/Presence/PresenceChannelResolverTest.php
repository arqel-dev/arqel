<?php

declare(strict_types=1);

use Arqel\Realtime\Exceptions\RealtimeException;
use Arqel\Realtime\Presence\PresenceChannelResolver;

it('resolves the default presence channel name', function (): void {
    expect(PresenceChannelResolver::forResource('posts', 42))
        ->toBe('arqel.presence.posts.42');
});

it('coerces string record ids into the channel name', function (): void {
    expect(PresenceChannelResolver::forResource('posts', 'abc-123'))
        ->toBe('arqel.presence.posts.abc-123');
});

it('honours a custom channel pattern from config', function (): void {
    config()->set('arqel-realtime.presence.channel_pattern', 'tenant.{resource}:{recordId}');

    expect(PresenceChannelResolver::forResource('orders', 7))
        ->toBe('tenant.orders:7');
});

it('falls back to the default pattern when config is invalid', function (): void {
    config()->set('arqel-realtime.presence.channel_pattern', '');

    expect(PresenceChannelResolver::forResource('posts', 1))
        ->toBe('arqel.presence.posts.1');
});

it('throws RealtimeException when presence is disabled', function (): void {
    config()->set('arqel-realtime.presence.enabled', false);

    PresenceChannelResolver::forResource('posts', 42);
})->throws(RealtimeException::class, 'presence is disabled');

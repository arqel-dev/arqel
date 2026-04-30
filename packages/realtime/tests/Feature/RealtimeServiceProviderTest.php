<?php

declare(strict_types=1);

use Arqel\Realtime\RealtimeServiceProvider;
use Illuminate\Support\ServiceProvider;

it('boots the RealtimeServiceProvider', function (): void {
    $providers = app()->getLoadedProviders();

    expect($providers)->toHaveKey(RealtimeServiceProvider::class);
});

it('publishes the arqel-realtime config with default values', function (): void {
    expect(config('arqel-realtime'))->toBeArray()
        ->and(config('arqel-realtime.channel_prefix'))->toBe('arqel')
        ->and(config('arqel-realtime.auto_dispatch.resource_updated'))->toBeTrue();
});

it('registers the publishable config tag', function (): void {
    $groups = ServiceProvider::publishableGroups();

    $matched = array_filter(
        $groups,
        static fn (mixed $group): bool => is_string($group) && str_contains($group, 'arqel-realtime'),
    );

    expect($matched)->not->toBeEmpty();
});

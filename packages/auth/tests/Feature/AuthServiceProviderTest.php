<?php

declare(strict_types=1);

use Arqel\Auth\AbilityRegistry;
use Arqel\Auth\AuthServiceProvider;
use Arqel\Auth\PolicyDiscovery;
use Illuminate\Foundation\Application;

it('boots the auth service provider in a Testbench app', function (): void {
    expect(app())->toBeInstanceOf(Application::class)
        ->and(app()->getProviders(AuthServiceProvider::class))->not->toBeEmpty();
});

it('registers AbilityRegistry and PolicyDiscovery as singletons', function (): void {
    expect(app(AbilityRegistry::class))->toBe(app(AbilityRegistry::class))
        ->and(app(PolicyDiscovery::class))->toBe(app(PolicyDiscovery::class));
});

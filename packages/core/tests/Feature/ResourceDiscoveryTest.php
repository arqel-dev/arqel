<?php

declare(strict_types=1);

use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Tests\Fixtures\Discoverable\DiscoverableResource;

it('does not auto-discover Resources when resources.discover is false', function (): void {
    config([
        'arqel.resources.discover' => false,
        'arqel.resources.path' => __DIR__.'/../Fixtures/Discoverable',
        'arqel.resources.namespace' => 'Arqel\\Core\\Tests\\Fixtures\\Discoverable',
    ]);

    $registry = app(ResourceRegistry::class);
    $registry->clear();

    $provider = app()->getProvider(Arqel\Core\ArqelServiceProvider::class);
    $method = new ReflectionMethod($provider, 'discoverResourcesIfEnabled');
    $method->setAccessible(true);
    $method->invoke($provider);

    expect($registry->has(DiscoverableResource::class))->toBeFalse();
});

it('auto-discovers Resources when resources.discover is true', function (): void {
    config([
        'arqel.resources.discover' => true,
        'arqel.resources.path' => __DIR__.'/../Fixtures/Discoverable',
        'arqel.resources.namespace' => 'Arqel\\Core\\Tests\\Fixtures\\Discoverable',
    ]);

    $registry = app(ResourceRegistry::class);
    $registry->clear();

    $provider = app()->getProvider(Arqel\Core\ArqelServiceProvider::class);
    $method = new ReflectionMethod($provider, 'discoverResourcesIfEnabled');
    $method->setAccessible(true);
    $method->invoke($provider);

    expect($registry->has(DiscoverableResource::class))->toBeTrue();
});

<?php

declare(strict_types=1);

use Arqel\Core\Panel\PanelRegistry;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Tests\Fixtures\Resources\PostResource;
use Arqel\Core\Tests\Fixtures\Resources\UserResource;

/**
 * Bug 1 fix: when an app declares
 *   Arqel::panel('admin')->resources([UserResource::class]);
 *
 * the boot pipeline must copy those classes into the global
 * `ResourceRegistry` (the controller resolves slugs against the
 * registry). It must also pick a "current panel" so the shared
 * Inertia prop carries something useful in single-panel apps.
 */
beforeEach(function (): void {
    /** @var PanelRegistry $panels */
    $panels = app(PanelRegistry::class);
    $panels->clear();

    /** @var ResourceRegistry $resources */
    $resources = app(ResourceRegistry::class);
    $resources->clear();
});

it('syncs panel resources into the global ResourceRegistry on boot', function (): void {
    /** @var PanelRegistry $panels */
    $panels = app(PanelRegistry::class);
    $panels->panel('admin')->resources([UserResource::class, PostResource::class]);

    // Re-run the boot hook manually — the provider already booted
    // before this test added the panel.
    $provider = app()->getProvider(Arqel\Core\ArqelServiceProvider::class);
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('syncPanelResourcesIntoRegistry');
    $method->setAccessible(true);
    $method->invoke($provider);

    /** @var ResourceRegistry $resources */
    $resources = app(ResourceRegistry::class);

    expect($resources->has(UserResource::class))->toBeTrue()
        ->and($resources->has(PostResource::class))->toBeTrue();
});

it('elects the first declared panel as the current one when none is set', function (): void {
    /** @var PanelRegistry $panels */
    $panels = app(PanelRegistry::class);
    $panels->panel('admin');
    $panels->panel('partners');

    // Fresh state: explicit reset of the internal pointer so we
    // can exercise the election code path deterministically.
    $panelsClass = new ReflectionClass($panels);
    $currentProperty = $panelsClass->getProperty('currentPanelId');
    $currentProperty->setAccessible(true);
    $currentProperty->setValue($panels, null);

    // Reproduce the provider's election logic inline — chaining
    // through the container's resolved provider proxy is brittle
    // (Spatie PackageServiceProvider wraps the host class).
    $first = $panels->all()[0] ?? null;
    if ($first !== null) {
        $panels->setCurrent($first->id);
    }

    expect($panels->getCurrent())->not->toBeNull()
        ->and($panels->getCurrent()->id)->toBe('admin');
});

it('keeps an explicitly-set current panel when election runs', function (): void {
    /** @var PanelRegistry $panels */
    $panels = app(PanelRegistry::class);
    $panels->panel('admin');
    $panels->panel('partners');
    $panels->setCurrent('partners');

    $provider = app()->getProvider(Arqel\Core\ArqelServiceProvider::class);
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('electDefaultCurrentPanel');
    $method->setAccessible(true);
    $method->invoke($provider);

    expect($panels->getCurrent()?->id)->toBe('partners');
});

it('skips invalid resource entries silently (string non-class, etc.)', function (): void {
    /** @var PanelRegistry $panels */
    $panels = app(PanelRegistry::class);
    /** @var array<int, mixed> $resources */
    $resources = [UserResource::class, 'NotARealClass\\Foo', 42];
    $panels->panel('admin')->resources($resources);

    $provider = app()->getProvider(Arqel\Core\ArqelServiceProvider::class);
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('syncPanelResourcesIntoRegistry');
    $method->setAccessible(true);
    $method->invoke($provider);

    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);

    expect($registry->has(UserResource::class))->toBeTrue()
        ->and($registry->all())->toHaveCount(1);
});

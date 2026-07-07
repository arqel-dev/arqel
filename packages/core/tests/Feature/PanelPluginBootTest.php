<?php

declare(strict_types=1);

use Arqel\Core\ArqelServiceProvider;
use Arqel\Core\Panel\PanelRegistry;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Tests\Fixtures\Plugins\AdditiveFixturePlugin;
use Arqel\Core\Tests\Fixtures\Plugins\BootRegisteringPlugin;
use Arqel\Core\Tests\Fixtures\Plugins\FixturePlugin;
use Arqel\Core\Tests\Fixtures\Resources\PostResource;
use Arqel\Core\Tests\Fixtures\Resources\UserResource;

beforeEach(function (): void {
    /** @var PanelRegistry $panels */
    $panels = app(PanelRegistry::class);
    $panels->clear();

    /** @var ResourceRegistry $resources */
    $resources = app(ResourceRegistry::class);
    $resources->clear();
});

/** Helper: invoca um método (protected) do provider por reflexão. */
function invokeProviderMethod(string $method): void
{
    $provider = app()->getProvider(ArqelServiceProvider::class);
    $reflection = new ReflectionClass($provider);
    $target = $reflection->getMethod($method);
    $target->setAccessible(true);
    $target->invoke($provider);
}

it('boots each plugin registered on a panel', function (): void {
    $plugin = FixturePlugin::make();

    /** @var PanelRegistry $panels */
    $panels = app(PanelRegistry::class);
    $panels->panel('admin')->plugin($plugin);

    invokeProviderMethod('bootPanelPlugins');

    expect($plugin->booted)->toBeTrue();
});

it('lets a plugin register a resource in boot() that still becomes a route', function (): void {
    /** @var PanelRegistry $panels */
    $panels = app(PanelRegistry::class);
    $panels->panel('admin')->plugin(BootRegisteringPlugin::make());

    // Ordem real do booted(): bootPanelPlugins ANTES de syncPanelResources.
    invokeProviderMethod('bootPanelPlugins');
    invokeProviderMethod('syncPanelResourcesIntoRegistry');

    /** @var ResourceRegistry $resources */
    $resources = app(ResourceRegistry::class);

    expect($resources->has(UserResource::class))->toBeTrue();
});

it('registers a plugin resource end-to-end from register() into the registry', function (): void {
    /** @var PanelRegistry $panels */
    $panels = app(PanelRegistry::class);
    $panels->panel('admin')->plugin(FixturePlugin::make());

    invokeProviderMethod('bootPanelPlugins');
    invokeProviderMethod('syncPanelResourcesIntoRegistry');

    /** @var ResourceRegistry $resources */
    $resources = app(ResourceRegistry::class);

    expect($resources->has(PostResource::class))->toBeTrue();
});

it('composes two distinct plugins on the same panel without one clobbering the other', function (): void {
    /** @var PanelRegistry $panels */
    $panels = app(PanelRegistry::class);
    $panel = $panels->panel('admin');

    // FixturePlugin::register() é o primeiro a rodar contra um Panel
    // "vazio" — mesmo sendo substitutivo (`resources([PostResource::class])`),
    // não há nada a perder ainda. AdditiveFixturePlugin::register() usa a
    // forma aditiva (`[...$panel->getResources(), X]`), preservando o que
    // FixturePlugin já tinha adicionado. Isto prova composição real de N
    // plugins de ids distintos, cada um contribuindo o seu próprio resource.
    $panel->plugin(FixturePlugin::make());
    $panel->plugin(AdditiveFixturePlugin::make());

    invokeProviderMethod('bootPanelPlugins');
    invokeProviderMethod('syncPanelResourcesIntoRegistry');

    /** @var ResourceRegistry $resources */
    $resources = app(ResourceRegistry::class);

    expect($resources->has(PostResource::class))->toBeTrue()
        ->and($resources->has(UserResource::class))->toBeTrue();
});

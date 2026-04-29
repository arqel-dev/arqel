<?php

declare(strict_types=1);

use Arqel\Core\ArqelServiceProvider;
use Arqel\Core\Facades\Arqel;
use Arqel\Core\Panel\PanelRegistry;
use Arqel\Core\Resources\ResourceRegistry;
use Illuminate\Contracts\Console\Kernel;

it('registers ResourceRegistry as a singleton', function (): void {
    $first = app(ResourceRegistry::class);
    $second = app(ResourceRegistry::class);

    expect($first)->toBeInstanceOf(ResourceRegistry::class)
        ->and($second)->toBe($first);
});

it('registers PanelRegistry as a singleton', function (): void {
    $first = app(PanelRegistry::class);
    $second = app(PanelRegistry::class);

    expect($first)->toBeInstanceOf(PanelRegistry::class)
        ->and($second)->toBe($first);
});

it('aliases the PanelRegistry under the "arqel" container key', function (): void {
    expect(app(ArqelServiceProvider::FACADE_ACCESSOR))
        ->toBeInstanceOf(PanelRegistry::class)
        ->toBe(app(PanelRegistry::class));
});

it('resolves the Arqel facade to the PanelRegistry singleton', function (): void {
    expect(Arqel::getFacadeRoot())
        ->toBeInstanceOf(PanelRegistry::class)
        ->toBe(app(PanelRegistry::class));
});

it('merges the package config under the "arqel" key', function (): void {
    expect(config('arqel.path'))->toBe('/admin')
        ->and(config('arqel.auth.guard'))->toBe('web')
        ->and(config('arqel.resources.namespace'))->toBe('App\\Arqel\\Resources');
});

it('registers the arqel:install command via Spatie hasCommands', function (): void {
    expect(array_keys(app(Kernel::class)->all()))
        ->toContain('arqel:install');
});

it('registers the arqel:resource generator command', function (): void {
    expect(array_keys(app(Kernel::class)->all()))
        ->toContain('arqel:resource');
});

it('registers InertiaDataBuilder as a singleton', function (): void {
    $first = app(\Arqel\Core\Support\InertiaDataBuilder::class);
    $second = app(\Arqel\Core\Support\InertiaDataBuilder::class);

    expect($second)->toBe($first);
});

it('exposes the arqel view namespace', function (): void {
    /** @var Illuminate\View\Factory $view */
    $view = app('view');

    expect($view->exists('arqel::app'))->toBeTrue();
});

it('exposes the arqel translation namespace', function (): void {
    expect(trans('arqel::actions.view', [], 'en'))
        ->toBe('View')
        ->and(trans('arqel::actions.view', [], 'pt_BR'))
        ->toBe('Visualizar');
});

it('mounts the polymorphic resource routes under the panel path', function (): void {
    /** @var Illuminate\Routing\RouteCollection $routes */
    $routes = app('router')->getRoutes();

    $names = collect($routes)->map(fn ($route) => $route->getName())->filter()->values();

    expect($names)
        ->toContain('arqel.resources.index')
        ->toContain('arqel.resources.create')
        ->toContain('arqel.resources.store')
        ->toContain('arqel.resources.show')
        ->toContain('arqel.resources.edit')
        ->toContain('arqel.resources.update')
        ->toContain('arqel.resources.destroy');
});

<?php

declare(strict_types=1);

use Arqel\Core\ArqelServiceProvider;
use Arqel\Core\Facades\Arqel;
use Arqel\Core\Registries\PanelRegistry;
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

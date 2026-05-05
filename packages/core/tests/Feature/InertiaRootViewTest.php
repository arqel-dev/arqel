<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;

it('registers the arqel:: view namespace and resolves the root template', function (): void {
    expect(View::exists('arqel::app'))->toBeTrue();
});

it('ships the expected directives in the blade source', function (): void {
    $source = (string) file_get_contents(__DIR__.'/../../resources/views/app.blade.php');

    expect($source)
        ->toContain('<!DOCTYPE html>')
        ->toContain('<title inertia>')
        ->toContain("config('app.name'")
        ->toContain('name="csrf-token"')
        ->toContain("localStorage.getItem('arqel-theme')")
        ->toContain('@inertiaHead')
        ->toContain('@inertia');
});

it('exposes the configured root view to Inertia via config', function (): void {
    // Default points at the user-owned `arqel.layout` view scaffolded by
    // `arqel:install`, so the framework doesn't depend on Ziggy's @routes.
    // Apps can override to `arqel::app` (package-shipped) when Ziggy is
    // installed, or to any custom view name.
    expect(config('arqel.inertia.root_view'))->toBe('arqel.layout');
});

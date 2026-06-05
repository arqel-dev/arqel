<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('applies config(arqel.middleware) to the resource route group', function (): void {
    config(['arqel.middleware' => ['web', 'auth', 'arqel-probe-mw']]);

    // Re-run route registration the way the provider does.
    $provider = app()->getProvider(Arqel\Core\ArqelServiceProvider::class);
    $method = new ReflectionMethod($provider, 'registerResourceRoutes');
    $method->setAccessible(true);
    $method->invoke($provider);

    $route = collect(Route::getRoutes())->first(
        fn ($r) => $r->uri() === 'admin/{resource}' && in_array('GET', $r->methods(), true),
    );

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('arqel-probe-mw');
});

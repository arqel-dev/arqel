<?php

declare(strict_types=1);

use Arqel\Auth\Routes;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Routes::reset();
});

it('registers login + logout routes when called', function (): void {
    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login();

    Routes::register($panel);
    Route::getRoutes()->refreshNameLookups();

    expect(Route::has('login'))->toBeTrue()
        ->and(Route::has('logout'))->toBeTrue()
        ->and(Route::has('arqel.auth.login.attempt'))->toBeTrue();
});

it('is idempotent — calling register twice does not duplicate routes', function (): void {
    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login();

    Routes::register($panel);
    $countAfterFirst = count(Route::getRoutes()->getRoutes());

    Routes::register($panel);
    $countAfterSecond = count(Route::getRoutes()->getRoutes());

    expect($countAfterSecond)->toBe($countAfterFirst);
});

it('skips registration when host already has a login route', function (): void {
    Route::get('/host-login', fn () => 'host')->name('login');

    Routes::register();

    // arqel routes should NOT be registered
    expect(Route::has('arqel.auth.login.attempt'))->toBeFalse();
});

it('applies throttle middleware to POST login', function (): void {
    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login();

    Routes::register($panel);
    Route::getRoutes()->refreshNameLookups();

    $route = Route::getRoutes()->getByName('arqel.auth.login.attempt');
    expect($route)->not->toBeNull();
    $middleware = $route?->gatherMiddleware() ?? [];
    expect(collect($middleware)->contains(fn ($m) => str_contains((string) $m, 'throttle')))->toBeTrue();
});

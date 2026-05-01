<?php

declare(strict_types=1);

use Arqel\Auth\Routes;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Routes::reset();
});

it('does not register registration routes when registrationEnabled is false', function (): void {
    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login();

    Routes::register($panel);
    Route::getRoutes()->refreshNameLookups();

    expect(Route::has('register'))->toBeFalse()
        ->and(Route::has('arqel.auth.register.attempt'))->toBeFalse();
});

it('registers registration routes only when registrationEnabled is true', function (): void {
    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login()->registration();

    Routes::register($panel);
    Route::getRoutes()->refreshNameLookups();

    expect(Route::has('register'))->toBeTrue()
        ->and(Route::has('arqel.auth.register.attempt'))->toBeTrue();
});

it('is idempotent — calling registerRegistration twice does not duplicate routes', function (): void {
    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login()->registration();

    Routes::register($panel);
    $countAfterFirst = count(Route::getRoutes()->getRoutes());

    Routes::register($panel);
    $countAfterSecond = count(Route::getRoutes()->getRoutes());

    expect($countAfterSecond)->toBe($countAfterFirst);
});

it('registers email verification routes only when emailVerificationEnabled is true', function (): void {
    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login()->emailVerification();

    Routes::register($panel);
    Route::getRoutes()->refreshNameLookups();

    expect(Route::has('verification.notice'))->toBeTrue()
        ->and(Route::has('verification.verify'))->toBeTrue()
        ->and(Route::has('verification.send'))->toBeTrue();
});

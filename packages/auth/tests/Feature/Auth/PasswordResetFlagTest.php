<?php

declare(strict_types=1);

use Arqel\Auth\Routes;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Routes::reset();
});

it('does not register password-reset routes when passwordReset() is off', function (): void {
    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login();

    Routes::register($panel);
    Route::getRoutes()->refreshNameLookups();

    expect(Route::has('password.request'))->toBeFalse();
    expect(Route::has('password.reset'))->toBeFalse();
});

it('registers password-reset routes when passwordReset() is enabled', function (): void {
    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login()->passwordReset();

    Routes::register($panel);
    Route::getRoutes()->refreshNameLookups();

    expect(Route::has('password.request'))->toBeTrue();
    expect(Route::has('password.reset'))->toBeTrue();
    expect(Route::has('password.email'))->toBeTrue();
    expect(Route::has('password.update'))->toBeTrue();
});

it('is idempotent — calling registerPasswordReset twice does not duplicate routes', function (): void {
    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login()->passwordReset();

    Routes::register($panel);
    $count = count(Route::getRoutes()->getRoutes());

    Routes::registerPasswordReset($panel);
    expect(count(Route::getRoutes()->getRoutes()))->toBe($count);
});

it('updates auth.passwords.users.expire when passwordResetExpirationMinutes is set', function (): void {
    $registry = app(PanelRegistry::class);
    $registry->clear();
    $registry->panel('admin')->passwordResetExpirationMinutes(120);

    expect(config('auth.passwords.users.expire'))->toBe(120);
});

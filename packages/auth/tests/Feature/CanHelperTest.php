<?php

declare(strict_types=1);

use Arqel\Auth\AbilityRegistry;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

it('arqel_can returns false for guests', function (): void {
    expect(arqel_can('viewAdminPanel'))->toBeFalse();
});

it('arqel_can prefers AbilityRegistry snapshot over Gate', function (): void {
    Auth::setUser(new GenericUser(['id' => 1]));

    Gate::define('exportData', fn () => false);
    app(AbilityRegistry::class)->registerComputed('exportData', fn () => true);

    expect(arqel_can('exportData'))->toBeTrue();
});

it('arqel_can falls back to Gate when ability is not registered', function (): void {
    Auth::setUser(new GenericUser(['id' => 1]));

    Gate::define('manageSettings', fn () => true);

    expect(arqel_can('manageSettings'))->toBeTrue();
});

it('arqel_can passes arguments to Gate when ability is unregistered', function (): void {
    Auth::setUser(new GenericUser(['id' => 1]));

    Gate::define('updateThing', fn ($user, $thing) => $thing === 'allow');

    expect(arqel_can('updateThing', 'allow'))->toBeTrue()
        ->and(arqel_can('updateThing', 'deny'))->toBeFalse();
});

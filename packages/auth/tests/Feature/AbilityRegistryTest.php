<?php

declare(strict_types=1);

use Arqel\Auth\AbilityRegistry;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->registry = new AbilityRegistry;
});

it('registers global abilities and dedupes duplicates', function (): void {
    $this->registry
        ->registerGlobal('viewAdminPanel')
        ->registerGlobal('viewAdminPanel')
        ->registerGlobals(['manageSettings', 'exportData']);

    expect($this->registry->getGlobalAbilities())
        ->toBe(['viewAdminPanel', 'manageSettings', 'exportData']);
});

it('registers computed abilities', function (): void {
    $this->registry->registerComputed('isPremium', fn ($user) => true);

    expect($this->registry->getComputedAbilities())->toBe(['isPremium']);
});

it('resolves global abilities through the Gate for an authenticated user', function (): void {
    Gate::define('viewAdminPanel', fn ($user) => $user?->getAuthIdentifier() === 1);
    Gate::define('exportData', fn () => false);

    $this->registry->registerGlobals(['viewAdminPanel', 'exportData']);

    $user = new GenericUser(['id' => 1]);

    expect($this->registry->resolveForUser($user))->toBe([
        'viewAdminPanel' => true,
        'exportData' => false,
    ]);
});

it('resolves all global abilities to false for guests', function (): void {
    Gate::define('viewAdminPanel', fn () => true);
    $this->registry->registerGlobal('viewAdminPanel');

    expect($this->registry->resolveForUser(null))->toBe(['viewAdminPanel' => false]);
});

it('runs computed callbacks with the user', function (): void {
    $this->registry->registerComputed('isPremium', fn ($user) => $user?->getAuthIdentifier() === 42);

    $premiumUser = new GenericUser(['id' => 42]);
    $regularUser = new GenericUser(['id' => 7]);

    expect($this->registry->resolveForUser($premiumUser))->toBe(['isPremium' => true])
        ->and($this->registry->resolveForUser($regularUser))->toBe(['isPremium' => false]);
});

it('caches resolution per user identifier within the request', function (): void {
    $calls = 0;
    $this->registry->registerComputed('expensive', function () use (&$calls) {
        $calls++;

        return true;
    });

    $user = new GenericUser(['id' => 1]);
    $this->registry->resolveForUser($user);
    $this->registry->resolveForUser($user);

    expect($calls)->toBe(1);
});

it('clear() empties everything', function (): void {
    $this->registry
        ->registerGlobal('a')
        ->registerComputed('b', fn () => true)
        ->clear();

    expect($this->registry->getGlobalAbilities())->toBe([])
        ->and($this->registry->getComputedAbilities())->toBe([]);
});

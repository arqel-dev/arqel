<?php

declare(strict_types=1);

use Arqel\Tenant\Integrations\SpatieAdapter;
use Arqel\Tenant\Tests\Fixtures\Tenant;
use Illuminate\Http\Request;

/**
 * Test-only model that mimics Spatie's `current()` static accessor.
 * Each test seeds the static slot via `setCurrent()` so we can
 * exercise the adapter without pulling spatie/laravel-multitenancy.
 */
class SpatieLikeTenant extends Tenant
{
    private static ?self $currentTenant = null;

    public static function current(): ?self
    {
        return self::$currentTenant;
    }

    public static function setCurrent(?self $tenant): void
    {
        self::$currentTenant = $tenant;
    }
}

beforeEach(function (): void {
    SpatieLikeTenant::setCurrent(null);
});

it('returns the tenant from current() when set', function (): void {
    $tenant = new SpatieLikeTenant(['id' => 9]);
    SpatieLikeTenant::setCurrent($tenant);

    $adapter = new SpatieAdapter(modelClass: SpatieLikeTenant::class);

    expect($adapter->resolve(Request::create('/')))->toBe($tenant);
});

it('returns null when no tenant is current', function (): void {
    $adapter = new SpatieAdapter(modelClass: SpatieLikeTenant::class);

    expect($adapter->resolve(Request::create('/')))->toBeNull();
});

it('throws LogicException when the configured class lacks current()', function (): void {
    $adapter = new SpatieAdapter(modelClass: Tenant::class);

    expect(fn () => $adapter->resolve(Request::create('/')))
        ->toThrow(LogicException::class, 'static `current()` method');
});

it('throws LogicException when neither configured class nor canonical Spatie class exist', function (): void {
    $adapter = new SpatieAdapter(modelClass: '');

    expect(fn () => $adapter->resolve(Request::create('/')))
        ->toThrow(LogicException::class, 'requires the spatie/laravel-multitenancy package');
});

it('falls back to the canonical Spatie class when modelClass is empty and class is loaded', function (): void {
    if (! class_exists(SpatieAdapter::SPATIE_TENANT_CLASS)) {
        class_alias(SpatieLikeTenant::class, SpatieAdapter::SPATIE_TENANT_CLASS);
    }

    $tenant = new SpatieLikeTenant(['id' => 1]);
    SpatieLikeTenant::setCurrent($tenant);

    $adapter = new SpatieAdapter(modelClass: '');

    expect($adapter->resolve(Request::create('/')))->toBe($tenant);
});

it('identifierFor returns the model key as string', function (): void {
    $adapter = new SpatieAdapter(modelClass: SpatieLikeTenant::class);
    $tenant = new SpatieLikeTenant(['id' => 42]);

    expect($adapter->identifierFor($tenant))->toBe('42');
});

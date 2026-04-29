<?php

declare(strict_types=1);

use Arqel\Tenant\Integrations\StanclAdapter;
use Arqel\Tenant\Tests\Fixtures\Tenant;
use Illuminate\Http\Request;

/**
 * Stub Stancl tenancy class. We bind it under
 * `StanclAdapter::TENANCY_BINDING` (a string class name that
 * stancl/tenancy isn't installed under in this monorepo) so the
 * adapter resolves it through the duck-typed container path.
 */
final class FakeStanclTenancy
{
    public ?Illuminate\Database\Eloquent\Model $tenant = null;
}

/**
 * Tenant fixture exposing `getTenantKey()` (Stancl convention).
 */
class StanclLikeTenant extends Tenant
{
    public function getTenantKey(): string
    {
        return 'tenant-'.$this->getAttribute('id');
    }
}

beforeEach(function (): void {
    app()->forgetInstance(StanclAdapter::TENANCY_BINDING);
});

it('throws LogicException when the Stancl class is not installed', function (): void {
    // Default state: stancl/tenancy is NOT installed in this repo.
    $adapter = new StanclAdapter(modelClass: Tenant::class);

    expect(fn () => $adapter->resolve(Request::create('/')))
        ->toThrow(LogicException::class, 'requires the stancl/tenancy package');
});

it('throws LogicException when the class exists but is not bound to the container', function (): void {
    // Alias the fake under the canonical Stancl name so class_exists
    // reports true for this test only.
    if (! class_exists(StanclAdapter::TENANCY_BINDING)) {
        class_alias(FakeStanclTenancy::class, StanclAdapter::TENANCY_BINDING);
    }

    $adapter = new StanclAdapter(modelClass: Tenant::class);

    expect(fn () => $adapter->resolve(Request::create('/')))
        ->toThrow(LogicException::class, 'not bound to the container');
});

it('returns the tenant from Stancl when initialised', function (): void {
    if (! class_exists(StanclAdapter::TENANCY_BINDING)) {
        class_alias(FakeStanclTenancy::class, StanclAdapter::TENANCY_BINDING);
    }

    $tenant = new StanclLikeTenant(['id' => 7]);
    $tenancy = new FakeStanclTenancy;
    $tenancy->tenant = $tenant;

    app()->instance(StanclAdapter::TENANCY_BINDING, $tenancy);

    $adapter = new StanclAdapter(modelClass: StanclLikeTenant::class);

    expect($adapter->resolve(Request::create('/')))->toBe($tenant);
});

it('returns null when Stancl is bound but no tenant initialised', function (): void {
    if (! class_exists(StanclAdapter::TENANCY_BINDING)) {
        class_alias(FakeStanclTenancy::class, StanclAdapter::TENANCY_BINDING);
    }

    app()->instance(StanclAdapter::TENANCY_BINDING, new FakeStanclTenancy);

    $adapter = new StanclAdapter(modelClass: Tenant::class);

    expect($adapter->resolve(Request::create('/')))->toBeNull();
});

it('identifierFor honours getTenantKey() when the model exposes it', function (): void {
    $adapter = new StanclAdapter(modelClass: StanclLikeTenant::class);
    $tenant = new StanclLikeTenant(['id' => 7]);

    expect($adapter->identifierFor($tenant))->toBe('tenant-7');
});

it('identifierFor falls back to getKey() when getTenantKey is absent', function (): void {
    $adapter = new StanclAdapter(modelClass: Tenant::class);
    $tenant = new Tenant(['id' => 42]);

    expect($adapter->identifierFor($tenant))->toBe('42');
});

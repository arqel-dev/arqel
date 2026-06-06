<?php

declare(strict_types=1);

use Arqel\Tenant\Resolvers\SessionResolver;
use Arqel\Tenant\Tests\Fixtures\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User as Authenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Regression coverage for #131 (completing #81): when the resolver is
 * configured with a non-PK `identifier_column` (e.g. `slug`, the
 * showcase config), `SessionResolver::switchTo()` must persist the
 * IDENTIFIER-COLUMN value — not the primary key — because
 * `resolve()` reads the value back through
 * `AbstractTenantResolver::findByIdentifier()`, which queries
 * `where(identifier_column, value)`.
 *
 * Before the fix `switchTo()` stored `$tenant->getKey()` (the PK), so
 * with `identifier_column = 'slug'` the next request ran
 * `where('slug', <numeric-pk>)` -> no row -> the tenant was silently
 * lost. These tests exercise the REAL `findByIdentifier()` against an
 * in-memory `tenants` table, so they fail before the fix and pass after.
 */
function identifierColumnUser(): Authenticatable
{
    $user = new Authenticated;
    $user->id = 1;

    return $user;
}

beforeEach(function (): void {
    Schema::create('tenants', function ($table): void {
        $table->increments('id');
        $table->string('slug')->unique();
        $table->string('name');
    });

    Tenant::query()->create(['slug' => 'acme', 'name' => 'Acme Inc']);
});

afterEach(function (): void {
    Schema::dropIfExists('tenants');
});

it('round-trips a non-PK identifier column through the session on switch', function (): void {
    // PK = 1, slug = 'acme'. The masking concern: storing the PK (1)
    // and querying by slug would yield where('slug', 1) -> null.
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    expect($tenant->getKey())->toBe(1);

    $resolver = new SessionResolver(Tenant::class, 'slug', 'current_tenant_id');

    $resolver->switchTo(identifierColumnUser(), $tenant);

    // Next request: a fresh Request bound to the same (already-started)
    // session store the resolver just wrote to.
    $next = Request::create('https://x.test/');
    $next->setLaravelSession(app('session')->driver());

    $resolved = $resolver->resolve($next);

    expect($resolved)->not->toBeNull()
        ->and($resolved?->getKey())->toBe(1)
        ->and($resolved?->getAttribute('slug'))->toBe('acme');
});

it('stores the identifier-column value (slug), not the primary key, in the session', function (): void {
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    $resolver = new SessionResolver(Tenant::class, 'slug', 'current_tenant_id');

    $resolver->switchTo(identifierColumnUser(), $tenant);

    expect(app('session')->driver()->get('current_tenant_id'))->toBe('acme');
});

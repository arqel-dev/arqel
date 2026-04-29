<?php

declare(strict_types=1);

use Arqel\Tenant\Scopes\TenantScope;
use Arqel\Tenant\TenantManager;
use Arqel\Tenant\Tests\Fixtures\Tenant;
use Arqel\Tenant\Tests\Fixtures\TenantedPost;

beforeEach(function (): void {
    config([
        'arqel.tenancy.foreign_key' => 'tenant_id',
        'arqel.tenancy.model' => Tenant::class,
    ]);

    /** @var TenantManager $manager */
    $manager = app(TenantManager::class);
    $manager->forget();
});

it('exposes the configured foreign key via getTenantKeyName', function (): void {
    config(['arqel.tenancy.foreign_key' => 'workspace_id']);
    $post = new TenantedPost;

    expect($post->getTenantKeyName())->toBe('workspace_id');
});

it('falls back to tenant_id when no foreign key is configured', function (): void {
    config(['arqel.tenancy.foreign_key' => null]);
    $post = new TenantedPost;

    expect($post->getTenantKeyName())->toBe('tenant_id');
});

it('honours a model-level $tenantForeignKey override', function (): void {
    $post = new class extends TenantedPost
    {
        protected string $tenantForeignKey = 'org_id';
    };

    expect($post->getTenantKeyName())->toBe('org_id');
});

it('returns the qualified key name (table.col)', function (): void {
    $post = new TenantedPost;

    expect($post->getQualifiedTenantKeyName())->toBe('posts.tenant_id');
});

it('tenant() throws LogicException when arqel.tenancy.model is not configured', function (): void {
    config(['arqel.tenancy.model' => null]);
    $post = new TenantedPost;

    expect(fn () => $post->tenant())->toThrow(LogicException::class);
});

/**
 * Dispatches the `eloquent.creating: Model` event manually so we
 * exercise the trait's auto-fill listener without needing a DB
 * (the real `creating` flow goes through `Model::performInsert`,
 * which talks to a connection we don't have without `pdo_sqlite`).
 */
function fireCreating(Illuminate\Database\Eloquent\Model $model): void
{
    $event = 'eloquent.creating: '.get_class($model);
    app('events')->dispatch($event, [$model]);
}

it('auto-fills the tenant_id on creating when a tenant is current', function (): void {
    /** @var TenantManager $manager */
    $manager = app(TenantManager::class);
    $tenant = new Tenant(['id' => 7]);
    $manager->set($tenant);

    $post = new TenantedPost(['title' => 'hello']);
    fireCreating($post);

    expect($post->getAttribute('tenant_id'))->toBe(7);
});

it('does not overwrite the tenant_id when one is already set', function (): void {
    /** @var TenantManager $manager */
    $manager = app(TenantManager::class);
    $manager->set(new Tenant(['id' => 7]));

    $post = new TenantedPost(['title' => 'hello', 'tenant_id' => 99]);
    fireCreating($post);

    expect($post->getAttribute('tenant_id'))->toBe(99);
});

it('leaves the tenant_id alone when no tenant is current', function (): void {
    $post = new TenantedPost(['title' => 'hello']);
    fireCreating($post);

    expect($post->getAttribute('tenant_id'))->toBeNull();
});

it('registers TenantScope as a global scope on the model', function (): void {
    $scopes = (new TenantedPost)->getGlobalScopes();

    expect(array_keys($scopes))->toContain(TenantScope::class);
});

it('TenantScope::apply skips when no tenant is current', function (): void {
    /** @var TenantManager $manager */
    $manager = app(TenantManager::class);
    $manager->forget();

    $query = TenantedPost::query()->getQuery();
    $beforeWheres = count($query->wheres);

    (new TenantScope)->apply(TenantedPost::query(), new TenantedPost);

    expect(count($query->wheres))->toBe($beforeWheres);
});

it('TenantScope::apply adds a where on the qualified tenant key when current', function (): void {
    /** @var TenantManager $manager */
    $manager = app(TenantManager::class);
    $manager->set(new Tenant(['id' => 7]));

    $builder = TenantedPost::query();
    (new TenantScope)->apply($builder, new TenantedPost);

    $wheres = $builder->getQuery()->wheres;
    $tenantWhere = collect($wheres)->firstWhere('column', 'posts.tenant_id');

    expect($tenantWhere)->not->toBeNull()
        ->and($tenantWhere['value'])->toBe(7);
});

it('forTenant scope filters by an explicit id', function (): void {
    $builder = TenantedPost::forTenant(42);

    $wheres = $builder->getQuery()->wheres;
    $tenantWhere = collect($wheres)->firstWhere('column', 'tenant_id');

    expect($tenantWhere['value'])->toBe(42);
});

it('forTenant scope accepts a Model and reads its key', function (): void {
    $builder = TenantedPost::forTenant(new Tenant(['id' => 99]));

    $wheres = $builder->getQuery()->wheres;
    $tenantWhere = collect($wheres)->firstWhere('column', 'tenant_id');

    expect($tenantWhere['value'])->toBe(99);
});

it('withoutTenant removes the global scope from the query', function (): void {
    /** @var TenantManager $manager */
    $manager = app(TenantManager::class);
    $manager->set(new Tenant(['id' => 7]));

    $builder = TenantedPost::withoutTenant();
    $tenantWheres = collect($builder->getQuery()->wheres)
        ->filter(fn ($w) => ($w['column'] ?? null) === 'posts.tenant_id')
        ->values();

    expect($tenantWheres)->toBeEmpty();
});

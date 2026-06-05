<?php

declare(strict_types=1);

use Arqel\Tenant\Contracts\TenantResolver;
use Arqel\Tenant\Resolvers\AuthUserResolver;
use Arqel\Tenant\Resolvers\HeaderResolver;
use Arqel\Tenant\TenantManager;
use Arqel\Tenant\Tests\Fixtures\Tenant;
use ReflectionClass;

it('boots the tenant service provider in a Testbench app', function (): void {
    expect(true)->toBeTrue();
});

it('autoloads the Arqel\\Tenant namespace', function (): void {
    expect(class_exists(TenantManager::class))->toBeTrue();
});

it('binds TenantManager as a singleton in the container', function (): void {
    $first = app(TenantManager::class);
    $second = app(TenantManager::class);

    expect($first)->toBeInstanceOf(TenantManager::class)
        ->and($second)->toBe($first);
});

it('TenantManager has no resolver bound when no arqel.tenancy.* config is set', function (): void {
    $manager = app(TenantManager::class);

    expect($manager->hasCurrent())->toBeFalse()
        ->and($manager->current())->toBeNull()
        ->and($manager->resolved())->toBeFalse();
});

it('binds the configured TenantResolver from arqel.tenancy.* config', function (): void {
    config([
        'arqel.tenancy.resolver' => HeaderResolver::class,
        'arqel.tenancy.model' => Tenant::class,
        'arqel.tenancy.identifier_column' => 'id',
    ]);

    // Force re-resolution: drop the cached singleton
    app()->forgetInstance(TenantResolver::class);
    app()->forgetInstance(TenantManager::class);

    $resolver = app(TenantResolver::class);

    expect($resolver)->toBeInstanceOf(HeaderResolver::class);
});

it('returns null TenantResolver when arqel.tenancy.resolver is missing', function (): void {
    config(['arqel.tenancy' => null]);
    app()->forgetInstance(TenantResolver::class);

    $resolver = app(TenantResolver::class);

    expect($resolver)->toBeNull();
});

it('returns null TenantResolver when configured class does not implement TenantResolver', function (): void {
    config([
        'arqel.tenancy.resolver' => stdClass::class,
        'arqel.tenancy.model' => Tenant::class,
    ]);
    app()->forgetInstance(TenantResolver::class);

    $resolver = app(TenantResolver::class);

    expect($resolver)->toBeNull();
});

it('forwards arqel.tenancy.relation/available_relation/foreign_key to the resolver', function (): void {
    config([
        'arqel.tenancy.resolver' => AuthUserResolver::class,
        'arqel.tenancy.model' => Tenant::class,
        'arqel.tenancy.identifier_column' => 'slug',
        'arqel.tenancy.relation' => 'currentTenant',
        'arqel.tenancy.available_relation' => 'workspaces',
        'arqel.tenancy.foreign_key' => 'active_tenant_id',
    ]);

    app()->forgetInstance(TenantResolver::class);
    app()->forgetInstance(TenantManager::class);

    $resolver = app(TenantResolver::class);
    expect($resolver)->toBeInstanceOf(AuthUserResolver::class);

    $ref = new ReflectionClass($resolver);
    $relation = $ref->getProperty('relation');
    $relation->setAccessible(true);
    $available = $ref->getProperty('availableRelation');
    $available->setAccessible(true);
    $fk = $ref->getProperty('foreignKeyColumn');
    $fk->setAccessible(true);

    expect($relation->getValue($resolver))->toBe('currentTenant')
        ->and($available->getValue($resolver))->toBe('workspaces')
        ->and($fk->getValue($resolver))->toBe('active_tenant_id');
});

it('keeps resolver constructor defaults when the optional config keys are absent', function (): void {
    config([
        'arqel.tenancy.resolver' => AuthUserResolver::class,
        'arqel.tenancy.model' => Tenant::class,
        'arqel.tenancy.identifier_column' => 'id',
    ]);

    app()->forgetInstance(TenantResolver::class);
    app()->forgetInstance(TenantManager::class);

    $resolver = app(TenantResolver::class);
    $ref = new ReflectionClass($resolver);
    $relation = $ref->getProperty('relation');
    $relation->setAccessible(true);

    expect($relation->getValue($resolver))->toBe('currentTeam');
});

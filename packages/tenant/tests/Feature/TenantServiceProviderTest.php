<?php

declare(strict_types=1);

use Arqel\Tenant\TenantManager;

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

it('TenantManager stub reports no current tenant before TENANT-003 lands', function (): void {
    $manager = app(TenantManager::class);

    expect($manager->hasCurrent())->toBeFalse()
        ->and($manager->current())->toBeNull();
});

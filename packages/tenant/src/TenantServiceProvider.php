<?php

declare(strict_types=1);

namespace Arqel\Tenant;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Auto-discovered provider for `arqel/tenant`. The package is
 * intentionally lightweight on boot — concrete bindings (resolver,
 * manager) land in TENANT-002 / TENANT-003. Right now it just
 * registers the singleton container slot for `TenantManager` so
 * downstream consumers can type-hint it without nullability.
 */
final class TenantServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('arqel-tenant');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(TenantManager::class);
    }
}

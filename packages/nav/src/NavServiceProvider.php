<?php

declare(strict_types=1);

namespace Arqel\Nav;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Service provider for `arqel-dev/nav`.
 *
 * Registers `Navigation` and `BreadcrumbsBuilder` as singletons so
 * the Inertia middleware (CORE-007) can pull a per-request snapshot
 * for shared props.
 */
final class NavServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('arqel-nav');
    }

    public function packageBooted(): void
    {
        $this->app->singleton(Navigation::class);
        $this->app->singleton(BreadcrumbsBuilder::class);
    }
}

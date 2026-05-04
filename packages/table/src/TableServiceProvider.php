<?php

declare(strict_types=1);

namespace Arqel\Table;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Service provider for `arqel-dev/table`.
 *
 * Today the provider is the boot anchor; concrete builders, column
 * types, filters, and the query builder land in TABLE-002 onwards.
 */
final class TableServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('arqel-table');
    }
}

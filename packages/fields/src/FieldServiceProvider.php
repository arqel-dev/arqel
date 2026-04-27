<?php

declare(strict_types=1);

namespace Arqel\Fields;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Service provider for `arqel/fields`.
 *
 * Registers the package with Spatie's package tools so future
 * migrations, views, and field-type registrations have a single
 * boot location. Concrete `Field` types are registered in the
 * `FieldFactory` here once FIELDS-002/003 land.
 */
final class FieldServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('arqel-fields');
    }
}

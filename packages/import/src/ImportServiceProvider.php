<?php

declare(strict_types=1);

namespace Arqel\Import;

use Arqel\Import\Contracts\ImportLogger;
use Arqel\Import\Logging\NullImportLogger;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Auto-discovered provider for `arqel-dev/import`.
 *
 * Bindings:
 *   - `ImportLogger` → `NullImportLogger` (singletonIf — apps may override)
 */
final class ImportServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('arqel-import');
    }

    public function packageRegistered(): void
    {
        $this->app->singletonIf(ImportLogger::class, NullImportLogger::class);
    }
}

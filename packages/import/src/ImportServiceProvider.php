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
 * Translations are namespaced `arqel-import::import.*` (derived from
 * `name('arqel-import')`, mirroring `arqel-audit::messages.*`).
 */
final class ImportServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('arqel-import')
            ->hasTranslations()
            ->hasRoute('admin');
    }

    public function packageRegistered(): void
    {
        $this->app->singletonIf(ImportLogger::class, NullImportLogger::class);
    }
}

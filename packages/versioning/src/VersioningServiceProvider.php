<?php

declare(strict_types=1);

namespace Arqel\Versioning;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Auto-discovered provider para `arqel/versioning`.
 *
 * VERS-001 entrega o esqueleto:
 *
 * - `config/arqel-versioning.php` publishable com flags de retention e
 *   audit.
 * - migration `arqel_versions` carregada automaticamente.
 *
 * O pacote NÃO depende de `spatie/laravel-eventsourcing` — design intent
 * é standalone com snapshots Eloquent puros. UI / restore action /
 * diff viewer chegam em VERS-003+.
 */
final class VersioningServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('arqel-versioning')
            ->hasConfigFile('arqel-versioning')
            ->hasMigration('create_arqel_versions_table')
            ->hasRoute('web');
    }
}

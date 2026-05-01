<?php

declare(strict_types=1);

namespace Arqel\Versioning;

use Arqel\Versioning\Console\PruneVersionsCommand;
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
 * VERS-005 adiciona o endpoint `restore` (rotas em `routes/web.php`).
 * VERS-006 adiciona o comando `arqel:versions:prune`.
 *
 * O pacote NÃO depende de `spatie/laravel-eventsourcing` — design intent
 * é standalone com snapshots Eloquent puros.
 */
final class VersioningServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('arqel-versioning')
            ->hasConfigFile('arqel-versioning')
            ->hasMigration('create_arqel_versions_table')
            ->hasRoute('web')
            ->hasCommand(PruneVersionsCommand::class);
    }
}

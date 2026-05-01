<?php

declare(strict_types=1);

namespace Arqel\Marketplace;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Auto-discovered provider para `arqel/marketplace`.
 *
 * MKTPLC-001 entrega o esqueleto:
 *
 * - `config/arqel-marketplace.php` publishable com flags de habilitação,
 *   prefixo de rotas, paginação e workflow de submission review.
 * - migration `arqel_marketplace_tables` carrega as 4 tabelas
 *   (`arqel_plugins`, `arqel_plugin_versions`, `arqel_plugin_installations`,
 *   `arqel_plugin_reviews`) automaticamente.
 * - rotas REST em `routes/api.php` com prefixo configurável.
 *
 * MKTPLC-002 adiciona o submission workflow.
 * MKTPLC-003 adiciona a plugin metadata convention + comando `arqel:plugin:list`.
 */
final class MarketplaceServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('arqel-marketplace')
            ->hasConfigFile('arqel-marketplace')
            ->hasMigration('create_arqel_marketplace_tables')
            ->hasMigration('add_submission_columns_to_arqel_plugins')
            ->hasRoute('api');
    }

    public function packageBooted(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Arqel\Marketplace\Console\PluginListCommand::class,
            ]);
        }
    }
}

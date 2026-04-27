<?php

declare(strict_types=1);

namespace Arqel\Core;

use Arqel\Core\Registries\PanelRegistry;
use Arqel\Core\Registries\ResourceRegistry;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class ArqelServiceProvider extends PackageServiceProvider
{
    public const string FACADE_ACCESSOR = 'arqel';

    public function configurePackage(Package $package): void
    {
        $package
            ->name('arqel')
            ->hasConfigFile('arqel')
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->askToStarRepoOnGitHub('arqel/arqel');
            });
    }

    public function packageBooted(): void
    {
        $this->registerResourceRegistry();
        $this->registerPanelRegistry();
        $this->registerFacade();
    }

    protected function registerResourceRegistry(): void
    {
        $this->app->singleton(ResourceRegistry::class);
    }

    protected function registerPanelRegistry(): void
    {
        $this->app->singleton(PanelRegistry::class);
    }

    protected function registerFacade(): void
    {
        $this->app->alias(PanelRegistry::class, self::FACADE_ACCESSOR);
    }
}

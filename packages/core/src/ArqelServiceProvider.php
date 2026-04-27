<?php

declare(strict_types=1);

namespace Arqel\Core;

use Arqel\Core\Commands\InstallCommand;
use Arqel\Core\Panel\PanelRegistry;
use Arqel\Core\Resources\ResourceRegistry;
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
            ->hasCommands([
                InstallCommand::class,
            ]);
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

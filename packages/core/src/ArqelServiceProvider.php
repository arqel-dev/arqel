<?php

declare(strict_types=1);

namespace Arqel\Core;

use Arqel\Core\Commands\InstallCommand;
use Arqel\Core\Commands\MakeResourceCommand;
use Arqel\Core\Http\Middleware\HandleArqelInertiaRequests;
use Arqel\Core\Panel\PanelRegistry;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\InertiaDataBuilder;
use Illuminate\Support\Facades\Route;
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
            ->hasViews('arqel')
            ->hasTranslations()
            ->hasCommands([
                InstallCommand::class,
                MakeResourceCommand::class,
            ]);
    }

    public function packageBooted(): void
    {
        $this->registerResourceRegistry();
        $this->registerPanelRegistry();
        $this->registerInertiaDataBuilder();
        $this->registerFacade();
        $this->registerResourceRoutes();
    }

    protected function registerResourceRegistry(): void
    {
        $this->app->singleton(ResourceRegistry::class);
    }

    protected function registerPanelRegistry(): void
    {
        $this->app->singleton(PanelRegistry::class);
    }

    protected function registerInertiaDataBuilder(): void
    {
        $this->app->singleton(InertiaDataBuilder::class);
    }

    protected function registerFacade(): void
    {
        $this->app->alias(PanelRegistry::class, self::FACADE_ACCESSOR);
    }

    /**
     * Register the polymorphic Resource routes under the panel
     * path. We pin them here (not via `hasRoute`) so we can apply
     * the panel's middleware stack and an explicit route prefix.
     */
    protected function registerResourceRoutes(): void
    {
        $registry = $this->app->make(PanelRegistry::class);

        $panel = $registry->getCurrent();
        $configPath = config('arqel.path', 'admin');
        $path = $panel?->getPath() ?? (is_string($configPath) ? $configPath : 'admin');
        $middleware = $panel?->getMiddleware() ?? ['web', HandleArqelInertiaRequests::class];

        if (! in_array(HandleArqelInertiaRequests::class, $middleware, true)) {
            $middleware[] = HandleArqelInertiaRequests::class;
        }

        Route::prefix($path)
            ->middleware($middleware)
            ->group(__DIR__.'/../routes/arqel.php');
    }
}

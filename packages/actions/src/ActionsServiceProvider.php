<?php

declare(strict_types=1);

namespace Arqel\Actions;

use Arqel\Core\Http\Middleware\HandleArqelInertiaRequests;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Service provider for `arqel-dev/actions`.
 *
 * Mounts the `ActionController` routes under the active panel's
 * prefix + middleware (mirrors what `arqel-dev/core` does for the
 * resource CRUD routes).
 */
final class ActionsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('arqel-actions');
    }

    public function packageBooted(): void
    {
        $this->registerActionRoutes();
    }

    protected function registerActionRoutes(): void
    {
        if (! $this->app->bound(PanelRegistry::class)) {
            return;
        }

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
            ->group(__DIR__.'/../routes/arqel-actions.php');
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Core;

use Arqel\Core\CommandPalette\CommandRegistry;
use Arqel\Core\CommandPalette\Providers\NavigationCommandProvider;
use Arqel\Core\CommandPalette\Providers\ThemeCommandProvider;
use Arqel\Core\Commands\InstallCommand;
use Arqel\Core\Commands\MakeResourceCommand;
use Arqel\Core\Console\DoctorCommand;
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
            ->hasRoute('admin')
            ->hasCommands([
                InstallCommand::class,
                MakeResourceCommand::class,
                DoctorCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Bind here so it is available before `packageBooted()` and
        // before any application provider that may register commands
        // or providers eagerly during boot.
        $this->app->singleton(CommandRegistry::class);
    }

    public function packageBooted(): void
    {
        $this->registerResourceRegistry();
        $this->registerPanelRegistry();
        $this->registerInertiaDataBuilder();
        $this->registerFacade();

        // Apps register panels in their own ServiceProvider::boot, which
        // runs before ours by registration order. Defer the panel→registry
        // sync to `app->booted` so we always see the final list of panels
        // and resources, regardless of provider order.
        $this->app->booted(function (): void {
            $this->syncPanelResourcesIntoRegistry();
            $this->electDefaultCurrentPanel();
        });

        $this->registerResourceRoutes();
        $this->registerBuiltInCommandProviders();
    }

    /**
     * Wire the built-in command palette providers (CMDPAL-002).
     *
     * - {@see NavigationCommandProvider} — emits one Command per
     *   registered Resource. Reads the registry on every request via
     *   `provide()`, so it picks up resources synced post-boot.
     * - {@see ThemeCommandProvider} — three static theme-switch
     *   commands.
     *
     * `CreateCommandProvider` and `RecordSearchProvider` are
     * deferred: both need policy authorisation + Resource model
     * traversal that lives in follow-up work.
     */
    protected function registerBuiltInCommandProviders(): void
    {
        $registry = $this->app->make(CommandRegistry::class);
        $registry->registerProvider($this->app->make(NavigationCommandProvider::class));
        $registry->registerProvider($this->app->make(ThemeCommandProvider::class));
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

    /**
     * Walk every registered Panel and copy its `resources([...])`
     * declaration into the global `ResourceRegistry`. The
     * controller resolves slugs against the registry, not against
     * Panel state, so this sync is mandatory for `/admin/{slug}`
     * to ever resolve.
     *
     * Idempotent: `ResourceRegistry::register()` skips duplicates.
     */
    protected function syncPanelResourcesIntoRegistry(): void
    {
        $panelRegistry = $this->app->make(PanelRegistry::class);
        $resourceRegistry = $this->app->make(ResourceRegistry::class);

        foreach ($panelRegistry->all() as $panel) {
            foreach ($panel->getResources() as $resourceClass) {
                if (! is_string($resourceClass) || ! class_exists($resourceClass)) {
                    continue;
                }

                if ($resourceRegistry->has($resourceClass)) {
                    continue;
                }

                $resourceRegistry->register($resourceClass);
            }
        }
    }

    /**
     * Set the first declared panel as the current one when no
     * panel has been picked yet. Single-panel apps (the common
     * case) get this for free; multi-panel apps must override
     * via middleware that calls `PanelRegistry::setCurrent($id)`
     * based on path.
     */
    protected function electDefaultCurrentPanel(): void
    {
        $panelRegistry = $this->app->make(PanelRegistry::class);

        if ($panelRegistry->getCurrent() !== null) {
            return;
        }

        $panels = $panelRegistry->all();
        if ($panels === []) {
            return;
        }

        // First panel by insertion order; PanelRegistry::all()
        // returns a numerically-indexed list, so we read the id
        // from the Panel object itself.
        $first = $panels[0] ?? null;
        if ($first !== null) {
            $panelRegistry->setCurrent($first->id);
        }
    }
}

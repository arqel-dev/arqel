<?php

declare(strict_types=1);

namespace Arqel\Widgets;

use Arqel\Core\Panel\PanelRegistry;
use Arqel\Widgets\Commands\MakeDashboardCommand;
use Arqel\Widgets\Commands\MakeWidgetCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Auto-discovered provider for `arqel-dev/widgets`.
 *
 * Binds:
 *   - `WidgetRegistry` as a singleton (apps register custom widget
 *     types via `app(WidgetRegistry::class)->register('foo', ...)`)
 *   - `DashboardRegistry` as a singleton (multi-dashboard panels
 *     register every `Dashboard` here keyed by id)
 *
 * Concrete widget types (StatWidget, ChartWidget, TableWidget,
 * CustomWidget) and the dashboard/data controllers land in
 * WIDGETS-002..006.
 */
final class WidgetsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('arqel-widgets')
            ->hasRoute('admin')
            ->hasCommand(MakeWidgetCommand::class)
            ->hasCommand(MakeDashboardCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(WidgetRegistry::class);
        $this->app->singleton(DashboardRegistry::class);
    }

    /**
     * Copy widgets declared on any Panel into the dashboard registry.
     *
     * Deferred to `booted` so every panel — including those a plugin
     * mutates in `Plugin::boot()` — is visible. `widgets` is registered
     * after `core`, so this callback runs after core's own `booted`
     * hook, which is where `bootPanelPlugins()` lives.
     */
    public function packageBooted(): void
    {
        $this->app->booted(function (): void {
            $this->syncPanelWidgetsIntoDashboardRegistry();
        });
    }

    /**
     * Panels hold a flat list of `class-string` without dashboard
     * identity; the registry holds `Dashboard` containers keyed by id.
     * The bridge wraps the former into the latter under the id `main`,
     * which is what `DashboardController` falls back to for `/admin`.
     */
    protected function syncPanelWidgetsIntoDashboardRegistry(): void
    {
        $panels = $this->app->make(PanelRegistry::class);
        $dashboards = $this->app->make(DashboardRegistry::class);

        $declared = [];
        foreach ($panels->all() as $panel) {
            foreach ($panel->getWidgets() as $widgetClass) {
                $declared[] = $widgetClass;
            }
        }

        if ($declared === []) {
            return;
        }

        $dashboards->register(
            Dashboard::make('main', 'Dashboard')->widgets($declared),
        );
    }
}

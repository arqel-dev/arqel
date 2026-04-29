<?php

declare(strict_types=1);

namespace Arqel\Widgets;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Auto-discovered provider for `arqel/widgets`.
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
            ->hasRoute('admin');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(WidgetRegistry::class);
        $this->app->singleton(DashboardRegistry::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Providers;

use App\Arqel\Dashboards\MainDashboard;
use App\Arqel\Resources\AuthorResource;
use App\Arqel\Resources\PostResource;
use App\Arqel\Resources\SettingResource;
use App\Arqel\Resources\TicketResource;
use App\Http\Middleware\HandleInertiaRequests;
use Arqel\Core\Panel\PanelRegistry;
use Arqel\Widgets\DashboardRegistry;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

/**
 * Wires the showcase `admin` panel: every published Resource, the
 * landing dashboard and the auth routes.
 *
 * The tenant context is populated natively by the Arqel core (driven by
 * `config('arqel.tenancy')` + `arqel.tenant` middleware), so this
 * provider only declares the panel surface — it knows nothing about
 * tenancy itself.
 */
final class ArqelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Auto-register the app's HandleInertiaRequests middleware in the
        // `web` group so apps don't need to edit `bootstrap/app.php`.
        // Idempotent: pushMiddlewareToGroup is a no-op when already present.
        if (class_exists(HandleInertiaRequests::class)) {
            /** @var Router $router */
            $router = $this->app->make('router');
            $router->pushMiddlewareToGroup('web', HandleInertiaRequests::class);
        }

        // Force the Inertia root view so the auth pages (login/register),
        // which only carry the `web` middleware, render under the same
        // Blade root as the rest of the panel.
        $rootView = config('arqel.inertia.root_view');
        if (is_string($rootView) && $rootView !== '') {
            Inertia::setRootView($rootView);
        }

        /** @var PanelRegistry $registry */
        $registry = $this->app->make(PanelRegistry::class);

        $panel = $registry
            ->panel('admin')
            ->path('admin')
            ->brand(config('app.name', 'Arqel'))
            ->login()
            ->afterLoginRedirectTo('/admin')
            ->resources([
                PostResource::class,
                AuthorResource::class,
                TicketResource::class,
                SettingResource::class,
            ]);

        $registry->setCurrent('admin');

        // Ensure /admin/login etc. exist even if AuthServiceProvider booted
        // before this provider (standalone apps).
        if (class_exists(\Arqel\Auth\Routes::class) && $panel->loginEnabled()) {
            \Arqel\Auth\Routes::register($panel);
        }

        // Register the panel's landing dashboard. `arqel-dev/widgets`
        // exposes `/admin` + `/admin/dashboards/{id}` and resolves each id
        // from the DashboardRegistry.
        if (class_exists(DashboardRegistry::class)) {
            /** @var DashboardRegistry $dashboards */
            $dashboards = $this->app->make(DashboardRegistry::class);
            if (! $dashboards->has('main')) {
                $dashboards->register(MainDashboard::make());
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Providers;

use App\Arqel\Resources\ProjectResource;
use App\Http\Middleware\HandleInertiaRequests;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

/**
 * Configura o painel `admin` desta aplicação de demonstração de
 * multi-tenancy.
 *
 * O painel expõe um único Resource (`ProjectResource`) cujo model usa
 * `BelongsToTenant` — toda a query é automaticamente filtrada pelo
 * tenant activo (resolvido pelo `AuthUserResolver` a partir de
 * `user.current_tenant_id`). Trocar de tenant via `<TenantSwitcher>`
 * muda a lista sem que este provider saiba de tenancy.
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
            ->afterLoginRedirectTo('/admin/projects')
            ->resources([
                ProjectResource::class,
            ]);

        $registry->setCurrent('admin');

        // Ensure /admin/login etc. exist even if AuthServiceProvider booted
        // before this provider (standalone apps).
        if (class_exists(\Arqel\Auth\Routes::class) && $panel->loginEnabled()) {
            \Arqel\Auth\Routes::register($panel);
        }
    }
}

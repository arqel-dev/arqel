<?php

declare(strict_types=1);

namespace App\Providers;

use App\Arqel\Resources\ProjectResource;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Tenant as TenantModel;
use Arqel\Core\Panel\PanelRegistry;
use Arqel\Tenant\Contracts\TenantResolver;
use Arqel\Tenant\Resolvers\AuthUserResolver;
use Arqel\Tenant\TenantManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
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
    /**
     * Override the tenant resolver so it reads the active tenant from
     * the user's `currentTenant` relation. The package default is
     * `currentTeam` (Jetstream convention) and the config wiring only
     * forwards model + identifier_column, so the `relation` argument
     * must be set here via an explicit binding. `availableRelation`
     * (`tenants`) and `foreignKeyColumn` (`current_tenant_id`) keep
     * their defaults, which already match this app.
     */
    public function register(): void
    {
        $this->app->bind(
            TenantResolver::class,
            fn (): AuthUserResolver => new AuthUserResolver(
                modelClass: TenantModel::class,
                identifierColumn: 'slug',
                relation: 'currentTenant',
            ),
        );
    }

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

        // Share the tenant context GLOBALLY so it reaches the panel resource
        // pages. Key is `tenantContext` (NOT `tenant`): the core's
        // `final HandleArqelInertiaRequests` reserves a `tenant` key as a
        // Phase-1 stub that always returns null and would override ours via
        // array_merge. `tenantContext` sidesteps that collision.
        Inertia::share('tenantContext', fn (): array => $this->tenantContext());

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

        // Tenant resolution is wired in bootstrap/app.php (appended to the
        // `web` group), because the core route registration does not
        // reliably apply the Panel's own middleware stack.

        $registry->setCurrent('admin');

        // Ensure /admin/login etc. exist even if AuthServiceProvider booted
        // before this provider (standalone apps).
        if (class_exists(\Arqel\Auth\Routes::class) && $panel->loginEnabled()) {
            \Arqel\Auth\Routes::register($panel);
        }
    }

    /**
     * Tenant context for the shared Inertia prop consumed by
     * `<TenantSwitcher>`: the active tenant plus the set the current
     * user may switch to.
     *
     * @return array{current: array<string, mixed>|null, available: array<int, array<string, mixed>>}
     */
    private function tenantContext(): array
    {
        $manager = $this->app->make(TenantManager::class);
        $user = Auth::user();

        return [
            'current' => $this->serialiseTenant($manager->current()),
            'available' => $user !== null
                ? array_values(array_filter(array_map(
                    fn (Model $tenant): ?array => $this->serialiseTenant($tenant),
                    $manager->availableFor($user),
                )))
                : [],
        ];
    }

    /**
     * @return array{id: int|string, name: string|null, slug: string|null, logo: string|null}|null
     */
    private function serialiseTenant(?Model $tenant): ?array
    {
        if ($tenant === null) {
            return null;
        }

        return [
            'id' => $tenant->getKey(),
            'name' => $tenant->getAttribute('name'),
            'slug' => $tenant->getAttribute('slug'),
            'logo' => $tenant->getAttribute('logo'),
        ];
    }
}

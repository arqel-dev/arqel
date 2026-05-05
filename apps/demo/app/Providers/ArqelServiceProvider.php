<?php

declare(strict_types=1);

namespace App\Providers;

use App\Arqel\Resources\UserResource;
use App\Http\Middleware\HandleInertiaRequests;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

/**
 * Configura o painel `admin` desta aplicação.
 *
 * Tudo o que o Arqel precisa para arrancar (path, branding, login,
 * registration, lista de Resources) é declarado aqui via
 * `PanelRegistry::panel(...)`. O pacote `arqel-dev/core` lê este
 * estado quando regista as rotas polimórficas `/{path}/{resource}`,
 * e o `arqel-dev/auth` (se instalado) regista login/logout/register
 * automaticamente quando `->login()` está activo.
 */
final class ArqelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Auto-register the app's HandleInertiaRequests middleware in the
        // `web` group so apps don't need to edit `bootstrap/app.php`.
        // Idempotent: pushMiddlewareToGroup is a no-op when the middleware
        // is already present.
        if (class_exists(HandleInertiaRequests::class)) {
            /** @var Router $router */
            $router = $this->app->make('router');
            $router->pushMiddlewareToGroup('web', HandleInertiaRequests::class);
        }

        // O middleware base do `inertia-laravel` tem rootView default
        // `app`, mas as rotas bundled de `arqel-dev/auth` (apenas com
        // middleware `web`) não passam pelo `HandleArqelInertiaRequests`
        // do core, ignorando o `arqel.inertia.root_view` config. Forçar
        // globalmente garante que login/register/etc renderizam dentro
        // do mesmo Blade root que o resto do painel.
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
            ->afterLoginRedirectTo('/admin/users')
            ->registration()
            ->resources([
                UserResource::class,
            ]);

        $registry->setCurrent('admin');

        // Workaround: `Arqel\Auth\AuthServiceProvider::packageBooted()`
        // pode correr antes deste boot() em apps standalone, ficando o
        // PanelRegistry vazio quando ele tenta registar as rotas. Forçar
        // aqui garante que `/admin/login` etc. existem se o pacote
        // `arqel-dev/auth` estiver instalado.
        if (class_exists(\Arqel\Auth\Routes::class) && $panel->loginEnabled()) {
            \Arqel\Auth\Routes::register($panel);
        }
    }
}

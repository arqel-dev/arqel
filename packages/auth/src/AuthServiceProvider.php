<?php

declare(strict_types=1);

namespace Arqel\Auth;

use Arqel\Core\Panel\PanelRegistry;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Service provider for `arqel/auth`.
 *
 * Boots the authorization layer: registers `AbilityRegistry` as a
 * singleton so panels can register global abilities and resolve
 * them for the authenticated user. `PolicyDiscovery` is exposed as
 * a stateless service.
 */
final class AuthServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('arqel-auth');
    }

    public function packageBooted(): void
    {
        $this->app->singleton(AbilityRegistry::class);
        $this->app->singleton(PolicyDiscovery::class);

        $this->bootBundledAuthRoutes();
    }

    /**
     * Regista as rotas bundled de login/logout quando algum painel
     * registou `Panel::configure()->login()`.
     */
    private function bootBundledAuthRoutes(): void
    {
        if (! $this->app->bound(PanelRegistry::class)) {
            return;
        }

        /** @var PanelRegistry $registry */
        $registry = $this->app->make(PanelRegistry::class);

        foreach ($registry->all() as $panel) {
            if ($panel->loginEnabled()) {
                Routes::register($panel);
                break;
            }
        }
    }
}

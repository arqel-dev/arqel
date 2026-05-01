<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Service Provider customizado para configurar Arqel nesta aplicação.
 *
 * Registre aqui Resources, Widgets, Themes e overrides do panel.
 */
final class ArqelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Customizações de container — bindings, singletons, etc.
    }

    public function boot(): void
    {
        // Registro de Resources, Pages, Widgets, Navigation.
        // Exemplo:
        //
        //   Panel::default()
        //       ->resources([
        //           UserResource::class,
        //       ]);
    }
}

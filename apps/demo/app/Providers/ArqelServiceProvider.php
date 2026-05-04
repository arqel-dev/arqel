<?php

declare(strict_types=1);

namespace App\Providers;

use App\Arqel\Panel;
use App\Arqel\Resources\CategoryResource;
use App\Arqel\Resources\PostResource;
use App\Arqel\Resources\TagResource;
use Illuminate\Support\ServiceProvider;

/**
 * Provider user-land que registra o painel `admin` com os 3 resources demo.
 *
 * Equivalente em produção:
 *
 *   Panel::configure('admin')
 *       ->path('admin')
 *       ->resources([PostResource::class, TagResource::class, CategoryResource::class])
 *       ->login()->registration()->emailVerification()->passwordReset();
 */
final class ArqelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Panel::configure('admin')
            ->path('admin')
            ->resources([
                PostResource::class,
                TagResource::class,
                CategoryResource::class,
            ])
            ->login()
            ->registration()
            ->emailVerification()
            ->passwordReset();
    }

    public function boot(): void
    {
        // Hook de boot — em produção registra rotas, middleware, gates, etc.
    }
}

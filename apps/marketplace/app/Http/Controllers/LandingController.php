<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginCategory;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Landing page do marketplace público.
 *
 * Renderiza hero + 3 sections (Featured / Trending / New) + grid de categorias raiz.
 * Apenas plugins com `status = published` são expostos.
 */
final class LandingController
{
    public function __invoke(): Response
    {
        return Inertia::render('Marketplace/Landing', [
            'featured' => Plugin::query()->published()->featured()->limit(6)->get(),
            'trending' => Plugin::query()->published()->trending()->limit(10)->get(),
            'newPlugins' => Plugin::query()->published()->newThisWeek()->limit(10)->get(),
            'categories' => PluginCategory::query()->root()->ordered()->get(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SeoData;
use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginCategory;
use Illuminate\Support\Facades\View;
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
        View::share('seo', new SeoData(
            title: 'Arqel Marketplace — Plugins for Laravel admin panels',
            description: 'Descubra plugins community para estender seu admin panel Arqel. Fields, widgets, integrações e themes em um só lugar.',
            ogImage: '/images/og/marketplace-landing.png',
        ));

        return Inertia::render('Marketplace/Landing', [
            'featured' => Plugin::query()->published()->featured()->limit(6)->get(),
            'trending' => Plugin::query()->published()->trending()->limit(10)->get(),
            'newPlugins' => Plugin::query()->published()->newThisWeek()->limit(10)->get(),
            'categories' => PluginCategory::query()->root()->ordered()->get(),
        ]);
    }
}

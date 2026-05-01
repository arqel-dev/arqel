<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SeoData;
use Arqel\Marketplace\Models\Publisher;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página de perfil público de Publisher (MKTPLC-004-publisher).
 *
 * Renderiza header (avatar+bio+social), stats agregados e lista de plugins published.
 * Slug inexistente retorna 404.
 */
final class PublisherProfileController
{
    public function __invoke(string $slug): Response
    {
        $publisher = Publisher::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $plugins = $publisher->plugins()
            ->where('status', 'published')
            ->orderByDesc('created_at')
            ->get();

        $bio = is_string($publisher->bio) && $publisher->bio !== ''
            ? SeoData::truncate($publisher->bio, 160)
            : "Plugins publicados por {$publisher->name} no Arqel Marketplace.";

        View::share('seo', new SeoData(
            title: "{$publisher->name} — Arqel Marketplace publisher",
            description: $bio,
            ogImage: is_string($publisher->avatar_url) && $publisher->avatar_url !== ''
                ? $publisher->avatar_url
                : null,
            ogType: 'profile',
            canonical: url('/publishers/'.$publisher->slug),
        ));

        return Inertia::render('Marketplace/PublisherProfile', [
            'publisher' => $publisher,
            'plugins' => $plugins,
            'stats' => $publisher->aggregateStats(),
        ]);
    }
}

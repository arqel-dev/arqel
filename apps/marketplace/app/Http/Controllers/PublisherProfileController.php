<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Arqel\Marketplace\Models\Publisher;
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

        return Inertia::render('Marketplace/PublisherProfile', [
            'publisher' => $publisher,
            'plugins' => $plugins,
            'stats' => $publisher->aggregateStats(),
        ]);
    }
}

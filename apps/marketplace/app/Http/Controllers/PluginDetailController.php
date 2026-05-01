<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Arqel\Marketplace\Models\Plugin;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plugin detail page — header + tabs (README/Versions/Reviews) + sidebar (install command, github).
 *
 * Apenas slugs com status `published` são acessíveis publicamente; slug inexistente ou
 * em outro status retorna 404.
 */
final class PluginDetailController
{
    public function __invoke(string $slug): Response
    {
        $plugin = Plugin::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $versions = $plugin->versions()
            ->orderByDesc('released_at')
            ->limit(20)
            ->get();

        $reviews = $plugin->reviews()
            ->latest()
            ->paginate(10);

        $related = Plugin::query()
            ->published()
            ->ofType($plugin->type)
            ->where('id', '!=', $plugin->id)
            ->limit(5)
            ->get();

        return Inertia::render('Marketplace/PluginDetail', [
            'plugin' => $plugin,
            'versions' => $versions,
            'reviews' => $reviews,
            'related' => $related,
        ]);
    }
}

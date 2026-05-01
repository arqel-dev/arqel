<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Controllers;

use Arqel\Marketplace\Models\Plugin;
use Illuminate\Http\JsonResponse;

/**
 * Lista plugins featured (editor's picks) published (MKTPLC-007).
 */
final class FeaturedPluginsController
{
    public function __invoke(): JsonResponse
    {
        $plugins = Plugin::query()
            ->published()
            ->featured()
            ->orderByDesc('featured_at')
            ->orderByDesc('id')
            ->get();

        return new JsonResponse([
            'data' => $plugins->map(static fn (Plugin $plugin): array => [
                'id' => $plugin->id,
                'slug' => $plugin->slug,
                'name' => $plugin->name,
                'description' => $plugin->description,
                'type' => $plugin->type,
                'github_url' => $plugin->github_url,
                'featured_at' => $plugin->featured_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}

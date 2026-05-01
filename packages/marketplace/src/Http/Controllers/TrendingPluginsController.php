<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Controllers;

use Arqel\Marketplace\Models\Plugin;
use Illuminate\Http\JsonResponse;

/**
 * Lista plugins por trending score (MKTPLC-007). Limitado a 20 itens.
 */
final class TrendingPluginsController
{
    private const LIMIT = 20;

    public function __invoke(): JsonResponse
    {
        $plugins = Plugin::query()
            ->published()
            ->trending()
            ->limit(self::LIMIT)
            ->get();

        return new JsonResponse([
            'data' => $plugins->map(static fn (Plugin $plugin): array => [
                'id' => $plugin->id,
                'slug' => $plugin->slug,
                'name' => $plugin->name,
                'description' => $plugin->description,
                'type' => $plugin->type,
                'trending_score' => (float) $plugin->trending_score,
            ])->all(),
        ]);
    }
}

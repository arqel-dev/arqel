<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Controllers;

use Arqel\Marketplace\Models\Plugin;
use Illuminate\Http\JsonResponse;

/**
 * Lista plugins published com mais instalações all-time (MKTPLC-007).
 *
 * Limitado a 20 itens.
 */
final class MostPopularPluginsController
{
    private const LIMIT = 20;

    public function __invoke(): JsonResponse
    {
        $plugins = Plugin::query()
            ->published()
            ->mostPopular()
            ->limit(self::LIMIT)
            ->get();

        return new JsonResponse([
            'data' => $plugins->map(static fn (Plugin $plugin): array => [
                'id' => $plugin->id,
                'slug' => $plugin->slug,
                'name' => $plugin->name,
                'description' => $plugin->description,
                'type' => $plugin->type,
                'installations_count' => (int) ($plugin->installations_count ?? 0),
            ])->all(),
        ]);
    }
}

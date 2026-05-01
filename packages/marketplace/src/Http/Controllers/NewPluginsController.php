<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Controllers;

use Arqel\Marketplace\Models\Plugin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lista plugins published criados nos últimos N dias (MKTPLC-007).
 *
 * Default `?days=7`. Clamp em [1, 90].
 */
final class NewPluginsController
{
    private const DEFAULT_DAYS = 7;

    private const MAX_DAYS = 90;

    public function __invoke(Request $request): JsonResponse
    {
        /** @var mixed $raw */
        $raw = $request->query('days', self::DEFAULT_DAYS);
        $days = is_numeric($raw) ? (int) $raw : self::DEFAULT_DAYS;
        $days = max(1, min(self::MAX_DAYS, $days));

        $plugins = Plugin::query()
            ->published()
            ->where('created_at', '>=', now()->subDays($days))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return new JsonResponse([
            'data' => $plugins->map(static fn (Plugin $plugin): array => [
                'id' => $plugin->id,
                'slug' => $plugin->slug,
                'name' => $plugin->name,
                'description' => $plugin->description,
                'type' => $plugin->type,
                'created_at' => $plugin->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'days' => $days,
            ],
        ]);
    }
}

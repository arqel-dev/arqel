<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Controllers;

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Listagem pública de reviews `published` de um plugin (MKTPLC-006).
 *
 * Suporta sort options:
 * - `helpful` (default) — `scopeMostHelpful`.
 * - `recent` — `scopeMostRecent`.
 * - `rating` — `scopeHighestRated`.
 */
final class PluginReviewListController
{
    public function __invoke(Request $request, string $slug): JsonResponse
    {
        $plugin = Plugin::query()->published()->where('slug', $slug)->first();

        if (! $plugin instanceof Plugin) {
            return new JsonResponse(['message' => "Plugin [{$slug}] not found"], 404);
        }

        /** @var mixed $sortInput */
        $sortInput = $request->query('sort', 'helpful');
        $sort = is_string($sortInput) ? $sortInput : 'helpful';

        if (! in_array($sort, ['helpful', 'recent', 'rating'], true)) {
            $sort = 'helpful';
        }

        $query = PluginReview::query()
            ->where('plugin_id', $plugin->id)
            ->published();

        $query = match ($sort) {
            'recent' => $query->mostRecent(),
            'rating' => $query->highestRated(),
            default => $query->mostHelpful(),
        };

        $reviews = $query->get();

        return new JsonResponse([
            'data' => $reviews->map(static fn (PluginReview $review): array => [
                'id' => $review->id,
                'plugin_id' => $review->plugin_id,
                'user_id' => $review->user_id,
                'stars' => $review->stars,
                'comment' => $review->comment,
                'verified_purchaser' => $review->verified_purchaser,
                'helpful_count' => $review->helpful_count,
                'unhelpful_count' => $review->unhelpful_count,
                'created_at' => $review->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'sort' => $sort,
                'total' => $reviews->count(),
            ],
        ]);
    }
}

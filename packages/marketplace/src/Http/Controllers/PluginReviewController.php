<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Controllers;

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Cria (ou atualiza idempotentemente) uma review para um plugin
 * published (MKTPLC-001).
 *
 * Idempotência: `firstOrCreate` por `(user_id, plugin_id)` — múltiplas
 * chamadas do mesmo user para o mesmo plugin não geram rows duplicadas.
 * MKTPLC-003 vai expandir para update + média ponderada de stars.
 */
final class PluginReviewController
{
    public function __invoke(Request $request, string $slug): JsonResponse
    {
        $userId = $this->resolveUserId($request);

        if ($userId === null) {
            return new JsonResponse(['message' => 'Unauthenticated'], 401);
        }

        try {
            /** @var array{stars: int, comment: ?string} $data */
            $data = $request->validate([
                'stars' => ['required', 'integer', 'min:1', 'max:5'],
                'comment' => ['nullable', 'string', 'max:5000'],
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $plugin = Plugin::query()
            ->published()
            ->where('slug', $slug)
            ->first();

        if (! $plugin instanceof Plugin) {
            return new JsonResponse(['message' => "Plugin [{$slug}] not found"], 404);
        }

        $review = PluginReview::query()->firstOrCreate(
            [
                'plugin_id' => $plugin->id,
                'user_id' => $userId,
            ],
            [
                'stars' => $data['stars'],
                'comment' => $data['comment'] ?? null,
                'status' => 'pending',
            ],
        );

        return new JsonResponse([
            'review' => [
                'id' => $review->id,
                'plugin_id' => $review->plugin_id,
                'user_id' => $review->user_id,
                'stars' => $review->stars,
                'comment' => $review->comment,
                'status' => $review->status,
                'created_at' => $review->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    private function resolveUserId(Request $request): ?int
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $key = $user->getAuthIdentifier();

        return is_numeric($key) ? (int) $key : null;
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Controllers;

use Arqel\Marketplace\Models\PluginReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Endpoints admin de moderação de reviews (MKTPLC-006).
 *
 * - `index` lista reviews por status (default `pending`).
 * - `moderate` aplica `publish` (status → `published`) ou `hide`
 *   (status → `hidden`, requer `reason`).
 *
 * Ambos os endpoints exigem ability `marketplace.moderate-reviews`.
 */
final class PluginReviewModerationController
{
    public function index(Request $request): JsonResponse
    {
        if (! Gate::allows('marketplace.moderate-reviews')) {
            return new JsonResponse(['message' => 'Forbidden'], 403);
        }

        /** @var mixed $statusInput */
        $statusInput = $request->query('status', 'pending');
        $status = is_string($statusInput) ? $statusInput : 'pending';

        if (! in_array($status, ['pending', 'published', 'hidden'], true)) {
            $status = 'pending';
        }

        /** @var mixed $perPageInput */
        $perPageInput = $request->query('per_page', 20);
        $perPage = is_numeric($perPageInput) ? (int) $perPageInput : 20;
        $perPage = max(1, min(100, $perPage));

        $paginator = PluginReview::query()
            ->where('status', $status)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return new JsonResponse([
            'data' => $paginator->getCollection()->map(static fn (PluginReview $review): array => [
                'id' => $review->id,
                'plugin_id' => $review->plugin_id,
                'user_id' => $review->user_id,
                'stars' => $review->stars,
                'comment' => $review->comment,
                'status' => $review->status,
                'moderation_reason' => $review->moderation_reason,
                'created_at' => $review->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'status' => $status,
            ],
        ]);
    }

    public function moderate(Request $request, int $reviewId): JsonResponse
    {
        if (! Gate::allows('marketplace.moderate-reviews')) {
            return new JsonResponse(['message' => 'Forbidden'], 403);
        }

        try {
            /** @var array{action: string, reason?: ?string} $data */
            $data = $request->validate([
                'action' => ['required', 'in:publish,hide'],
                'reason' => ['nullable', 'string', 'max:2000', 'required_if:action,hide'],
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $review = PluginReview::query()->find($reviewId);

        if (! $review instanceof PluginReview) {
            return new JsonResponse(['message' => 'Review not found'], 404);
        }

        if ($data['action'] === 'publish') {
            $review->update([
                'status' => 'published',
                'moderation_reason' => null,
            ]);
        } else {
            $review->update([
                'status' => 'hidden',
                'moderation_reason' => $data['reason'] ?? '',
            ]);
        }

        return new JsonResponse([
            'review' => [
                'id' => $review->id,
                'plugin_id' => $review->plugin_id,
                'status' => $review->status,
                'moderation_reason' => $review->moderation_reason,
            ],
        ], 200);
    }
}

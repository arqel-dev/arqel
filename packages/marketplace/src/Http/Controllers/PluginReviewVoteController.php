<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Controllers;

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginReview;
use Arqel\Marketplace\Models\PluginReviewVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Endpoints de helpful/unhelpful vote em uma review (MKTPLC-006).
 *
 * `store` é idempotente — re-submeter o mesmo voto não altera contadores;
 * trocar de `helpful` para `unhelpful` (ou vice-versa) decrementa o lado
 * antigo e incrementa o novo via transaction. `destroy` remove o voto e
 * decrementa o contador correspondente.
 */
final class PluginReviewVoteController
{
    public function store(Request $request, string $slug, int $reviewId): JsonResponse
    {
        $userId = $this->resolveUserId($request);

        if ($userId === null) {
            return new JsonResponse(['message' => 'Unauthenticated'], 401);
        }

        try {
            /** @var array{vote: string} $data */
            $data = $request->validate([
                'vote' => ['required', 'in:helpful,unhelpful'],
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $review = $this->findReview($slug, $reviewId);

        if (! $review instanceof PluginReview) {
            return new JsonResponse(['message' => 'Review not found'], 404);
        }

        $newVote = $data['vote'];

        DB::transaction(static function () use ($review, $userId, $newVote): void {
            $existing = PluginReviewVote::query()
                ->where('review_id', $review->id)
                ->where('user_id', $userId)
                ->first();

            if (! $existing instanceof PluginReviewVote) {
                PluginReviewVote::query()->create([
                    'review_id' => $review->id,
                    'user_id' => $userId,
                    'vote' => $newVote,
                ]);

                if ($newVote === 'helpful') {
                    $review->increment('helpful_count');
                } else {
                    $review->increment('unhelpful_count');
                }

                return;
            }

            if ($existing->vote === $newVote) {
                return;
            }

            $existing->update(['vote' => $newVote]);

            if ($newVote === 'helpful') {
                $review->decrement('unhelpful_count');
                $review->increment('helpful_count');
            } else {
                $review->decrement('helpful_count');
                $review->increment('unhelpful_count');
            }
        });

        $review->refresh();

        return new JsonResponse([
            'review' => [
                'id' => $review->id,
                'helpful_count' => $review->helpful_count,
                'unhelpful_count' => $review->unhelpful_count,
            ],
            'vote' => $newVote,
        ], 200);
    }

    public function destroy(Request $request, string $slug, int $reviewId): JsonResponse
    {
        $userId = $this->resolveUserId($request);

        if ($userId === null) {
            return new JsonResponse(['message' => 'Unauthenticated'], 401);
        }

        $review = $this->findReview($slug, $reviewId);

        if (! $review instanceof PluginReview) {
            return new JsonResponse(['message' => 'Review not found'], 404);
        }

        DB::transaction(static function () use ($review, $userId): void {
            $existing = PluginReviewVote::query()
                ->where('review_id', $review->id)
                ->where('user_id', $userId)
                ->first();

            if (! $existing instanceof PluginReviewVote) {
                return;
            }

            $previousVote = $existing->vote;
            $existing->delete();

            if ($previousVote === 'helpful' && $review->helpful_count > 0) {
                $review->decrement('helpful_count');
            } elseif ($previousVote === 'unhelpful' && $review->unhelpful_count > 0) {
                $review->decrement('unhelpful_count');
            }
        });

        $review->refresh();

        return new JsonResponse([
            'review' => [
                'id' => $review->id,
                'helpful_count' => $review->helpful_count,
                'unhelpful_count' => $review->unhelpful_count,
            ],
        ], 200);
    }

    private function findReview(string $slug, int $reviewId): ?PluginReview
    {
        $plugin = Plugin::query()->published()->where('slug', $slug)->first();

        if (! $plugin instanceof Plugin) {
            return null;
        }

        $review = PluginReview::query()
            ->where('id', $reviewId)
            ->where('plugin_id', $plugin->id)
            ->first();

        return $review instanceof PluginReview ? $review : null;
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

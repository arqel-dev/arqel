<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Controllers;

use Arqel\Marketplace\Events\PluginApproved;
use Arqel\Marketplace\Events\PluginRejected;
use Arqel\Marketplace\Models\Plugin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint admin para aprovar/rejeitar plugins pendentes (MKTPLC-002).
 *
 * Requer ability `marketplace.review` via Gate. Approve transiciona para
 * `published`; reject move para `archived` registrando `rejection_reason`.
 */
final class PluginAdminReviewController
{
    public function __invoke(Request $request, string $slug): JsonResponse
    {
        if (! Gate::allows('marketplace.review')) {
            return new JsonResponse(['message' => 'Forbidden'], 403);
        }

        try {
            /** @var array{action: string, rejection_reason?: ?string} $data */
            $data = $request->validate([
                'action' => ['required', 'in:approve,reject'],
                'rejection_reason' => ['nullable', 'string', 'max:2000', 'required_if:action,reject'],
            ]);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $plugin = Plugin::query()->where('slug', $slug)->first();

        if (! $plugin instanceof Plugin) {
            return new JsonResponse(['message' => "Plugin [{$slug}] not found"], 404);
        }

        $user = $request->user();
        $reviewerId = null;

        if ($user !== null) {
            $key = $user->getAuthIdentifier();
            $reviewerId = is_numeric($key) ? (int) $key : null;
        }

        if ($data['action'] === 'approve') {
            $plugin->update([
                'status' => 'published',
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $reviewerId,
                'rejection_reason' => null,
            ]);

            PluginApproved::dispatch($plugin);
        } else {
            $reason = $data['rejection_reason'] ?? '';
            $plugin->update([
                'status' => 'archived',
                'rejection_reason' => $reason,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $reviewerId,
            ]);

            PluginRejected::dispatch($plugin, $reason);
        }

        return new JsonResponse([
            'plugin' => [
                'id' => $plugin->id,
                'slug' => $plugin->slug,
                'status' => $plugin->status,
                'reviewed_at' => $plugin->reviewed_at?->toIso8601String(),
                'reviewed_by_user_id' => $plugin->reviewed_by_user_id,
                'rejection_reason' => $plugin->rejection_reason,
            ],
        ], 200);
    }
}

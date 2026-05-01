<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Controllers;

use Arqel\Marketplace\Models\Plugin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Toggle do flag `featured` de um plugin (MKTPLC-007).
 *
 * Requer ability `marketplace.feature` via Gate. Body: `{featured: bool}`.
 * Quando `featured=true`, popula `featured_at = now()`; senão limpa.
 */
final class PluginFeatureController
{
    public function __invoke(Request $request, string $slug): JsonResponse
    {
        if (! Gate::allows('marketplace.feature')) {
            return new JsonResponse(['message' => 'Forbidden'], 403);
        }

        try {
            /** @var array{featured: bool} $data */
            $data = $request->validate([
                'featured' => ['required', 'boolean'],
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

        $featured = (bool) $data['featured'];

        $plugin->update([
            'featured' => $featured,
            'featured_at' => $featured ? now() : null,
        ]);

        return new JsonResponse([
            'plugin' => [
                'id' => $plugin->id,
                'slug' => $plugin->slug,
                'featured' => $plugin->featured,
                'featured_at' => $plugin->featured_at?->toIso8601String(),
            ],
        ]);
    }
}

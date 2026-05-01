<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Controllers;

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginPurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Retorna URL de download do plugin (MKTPLC-008).
 *
 * Plugins free são acessíveis a qualquer usuário autenticado. Premium exige
 * `PluginPurchase` com `status=completed` no nome do user autenticado.
 */
final class PluginDownloadController
{
    public function __invoke(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return new JsonResponse(['message' => 'Unauthenticated'], 401);
        }

        $plugin = Plugin::query()->where('slug', $slug)->first();

        if (! $plugin instanceof Plugin) {
            return new JsonResponse(['message' => "Plugin [{$slug}] not found"], 404);
        }

        $key = $user->getAuthIdentifier();
        $userId = is_numeric($key) ? (int) $key : 0;

        if ($plugin->isPremium()) {
            $hasPurchase = PluginPurchase::query()
                ->where('plugin_id', $plugin->id)
                ->where('buyer_user_id', $userId)
                ->where('status', 'completed')
                ->exists();

            if (! $hasPurchase) {
                return new JsonResponse(['message' => 'License required'], 403);
            }
        }

        return new JsonResponse([
            'plugin' => [
                'id' => $plugin->id,
                'slug' => $plugin->slug,
            ],
            'download_url' => sprintf('https://arqel.dev/marketplace/download/%s/latest.zip', $plugin->slug),
        ]);
    }
}

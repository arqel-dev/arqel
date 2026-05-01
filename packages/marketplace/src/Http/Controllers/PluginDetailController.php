<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Controllers;

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginReview;
use Arqel\Marketplace\Models\PluginVersion;
use Illuminate\Http\JsonResponse;

/**
 * Detalhe de um plugin published por slug (MKTPLC-001).
 *
 * Retorna o plugin + as últimas 5 reviews + histórico completo de
 * versões. 404 quando o slug não existe ou o plugin ainda não está
 * published (draft/pending/archived são opacos para o público).
 */
final class PluginDetailController
{
    public function __invoke(string $slug): JsonResponse
    {
        $plugin = Plugin::query()
            ->published()
            ->where('slug', $slug)
            ->first();

        if (! $plugin instanceof Plugin) {
            return new JsonResponse(['message' => "Plugin [{$slug}] not found"], 404);
        }

        $reviews = PluginReview::query()
            ->where('plugin_id', $plugin->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $versions = PluginVersion::query()
            ->where('plugin_id', $plugin->id)
            ->orderByDesc('released_at')
            ->orderByDesc('id')
            ->get();

        return new JsonResponse([
            'plugin' => [
                'id' => $plugin->id,
                'slug' => $plugin->slug,
                'name' => $plugin->name,
                'description' => $plugin->description,
                'type' => $plugin->type,
                'composer_package' => $plugin->composer_package,
                'npm_package' => $plugin->npm_package,
                'github_url' => $plugin->github_url,
                'license' => $plugin->license,
                'screenshots' => $plugin->screenshots,
                'latest_version' => $plugin->latest_version,
            ],
            'reviews' => $reviews->map(static fn (PluginReview $review): array => [
                'id' => $review->id,
                'stars' => $review->stars,
                'comment' => $review->comment,
                'user_id' => $review->user_id,
                'created_at' => $review->created_at?->toIso8601String(),
            ])->all(),
            'versions' => $versions->map(static fn (PluginVersion $version): array => [
                'id' => $version->id,
                'version' => $version->version,
                'changelog' => $version->changelog,
                'released_at' => $version->released_at->toIso8601String(),
            ])->all(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Controllers;

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lista plugins published de uma categoria (MKTPLC-007).
 */
final class PluginsByCategoryController
{
    public function __invoke(Request $request, string $slug): JsonResponse
    {
        $category = PluginCategory::query()->where('slug', $slug)->first();

        if (! $category instanceof PluginCategory) {
            return new JsonResponse(['message' => "Category [{$slug}] not found"], 404);
        }

        /** @var mixed $perPageInput */
        $perPageInput = $request->query('per_page', 20);
        $perPage = is_numeric($perPageInput) ? (int) $perPageInput : 20;
        $perPage = max(1, min(100, $perPage));

        $paginator = $category->plugins()
            ->where('status', 'published')
            ->orderBy('name')
            ->orderBy('arqel_plugins.id')
            ->paginate($perPage);

        return new JsonResponse([
            'data' => $paginator->getCollection()->map(static fn (Plugin $plugin): array => [
                'id' => $plugin->id,
                'slug' => $plugin->slug,
                'name' => $plugin->name,
                'description' => $plugin->description,
                'type' => $plugin->type,
                'github_url' => $plugin->github_url,
                'license' => $plugin->license,
                'latest_version' => $plugin->latest_version,
            ])->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'category' => [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->name,
            ],
        ]);
    }
}

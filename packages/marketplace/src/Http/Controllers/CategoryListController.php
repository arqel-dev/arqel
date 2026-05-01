<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Controllers;

use Arqel\Marketplace\Models\PluginCategory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lista categorias do marketplace (MKTPLC-007).
 *
 * Quando `?root=1` é passado, retorna apenas categorias raiz com children
 * eager-loaded ordenados. Sem o flag, devolve a lista flat completa.
 */
final class CategoryListController
{
    public function __invoke(Request $request): JsonResponse
    {
        $rootOnly = $request->boolean('root');

        if ($rootOnly) {
            $categories = PluginCategory::query()
                ->root()
                ->ordered()
                ->with(['children' => static function (Relation $q): void {
                    $q->orderBy('sort_order');
                }])
                ->get();
        } else {
            $categories = PluginCategory::query()->ordered()->get();
        }

        return new JsonResponse([
            'data' => $categories->map(static fn (PluginCategory $cat): array => self::present($cat))->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function present(PluginCategory $cat): array
    {
        $payload = [
            'id' => $cat->id,
            'slug' => $cat->slug,
            'name' => $cat->name,
            'description' => $cat->description,
            'sort_order' => $cat->sort_order,
            'parent_id' => $cat->parent_id,
        ];

        if ($cat->relationLoaded('children')) {
            $payload['children'] = $cat->children->map(static fn (PluginCategory $child): array => [
                'id' => $child->id,
                'slug' => $child->slug,
                'name' => $child->name,
                'sort_order' => $child->sort_order,
                'parent_id' => $child->parent_id,
            ])->all();
        }

        return $payload;
    }
}

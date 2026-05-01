<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Controllers;

use Arqel\Marketplace\Models\Plugin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Lista paginada de plugins published no marketplace (MKTPLC-001).
 *
 * Query params suportados:
 *   - `type` — `field` | `widget` | `integration` | `theme`
 *   - `search` — busca textual em `name`/`description`
 *   - `per_page` — clampado em [1, 100], default vem de
 *     `config('arqel-marketplace.pagination', 20)`
 *   - `page` — paginação stock do Laravel
 */
final class PluginListController
{
    private const PER_PAGE_MAX = 100;

    public function __invoke(Request $request): JsonResponse
    {
        /** @var mixed $rawDefault */
        $rawDefault = config('arqel-marketplace.pagination', 20);
        $defaultPerPage = is_numeric($rawDefault) ? (int) $rawDefault : 20;

        $perPage = $this->resolvePerPage($request, $defaultPerPage);

        $query = Plugin::query()->published();

        /** @var mixed $type */
        $type = $request->query('type');
        if (is_string($type) && $type !== '') {
            $query = $query->ofType($type);
        }

        /** @var mixed $search */
        $search = $request->query('search');
        if (is_string($search) && $search !== '') {
            $query = $query->search($search);
        }

        $query = $query
            ->orderBy('name')
            ->orderBy('id');

        /** @var LengthAwarePaginator<int, Plugin> $paginator */
        $paginator = $query->paginate($perPage);

        $items = [];
        foreach ($paginator->items() as $plugin) {
            assert($plugin instanceof Plugin);
            $items[] = $this->present($plugin);
        }

        return new JsonResponse([
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Plugin $plugin): array
    {
        return [
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
        ];
    }

    private function resolvePerPage(Request $request, int $default): int
    {
        /** @var mixed $raw */
        $raw = $request->query('per_page', $default);

        $perPage = is_numeric($raw) ? (int) $raw : $default;

        if ($perPage < 1) {
            return $default;
        }

        return min($perPage, self::PER_PAGE_MAX);
    }
}

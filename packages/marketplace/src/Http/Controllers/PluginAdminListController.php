<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Controllers;

use Arqel\Marketplace\Models\Plugin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Listagem admin da fila de moderação (MKTPLC-002).
 *
 * Requer ability `marketplace.review`. Aceita filtro `status` (default
 * `pending`) e paginação via `per_page` clamp [1, 100].
 */
final class PluginAdminListController
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! Gate::allows('marketplace.review')) {
            return new JsonResponse(['message' => 'Forbidden'], 403);
        }

        /** @var mixed $statusInput */
        $statusInput = $request->query('status', 'pending');
        $status = is_string($statusInput) ? $statusInput : 'pending';

        if (! in_array($status, ['draft', 'pending', 'published', 'archived'], true)) {
            $status = 'pending';
        }

        /** @var mixed $perPageInput */
        $perPageInput = $request->query('per_page', 20);
        $perPage = is_numeric($perPageInput) ? (int) $perPageInput : 20;
        $perPage = max(1, min(100, $perPage));

        $paginator = Plugin::query()
            ->where('status', $status)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return new JsonResponse([
            'data' => $paginator->getCollection()->map(static fn (Plugin $plugin): array => [
                'id' => $plugin->id,
                'slug' => $plugin->slug,
                'name' => $plugin->name,
                'type' => $plugin->type,
                'status' => $plugin->status,
                'submitted_by_user_id' => $plugin->submitted_by_user_id,
                'submitted_at' => $plugin->submitted_at?->toIso8601String(),
                'submission_metadata' => $plugin->submission_metadata,
            ])->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}

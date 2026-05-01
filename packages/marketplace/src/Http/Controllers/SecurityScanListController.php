<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Controllers;

use Arqel\Marketplace\Models\SecurityScan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Listagem admin de security scans recentes (MKTPLC-009).
 *
 * Requer ability `marketplace.security-scans`. Aceita filtro `?status=`.
 */
final class SecurityScanListController
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! Gate::allows('marketplace.security-scans')) {
            return new JsonResponse(['message' => 'Forbidden'], 403);
        }

        $query = SecurityScan::query()
            ->orderByDesc('scan_started_at')
            ->orderByDesc('id');

        /** @var mixed $statusInput */
        $statusInput = $request->query('status');
        if (is_string($statusInput) && in_array($statusInput, ['pending', 'running', 'passed', 'flagged', 'failed'], true)) {
            $query->where('status', $statusInput);
        }

        /** @var mixed $perPageInput */
        $perPageInput = $request->query('per_page', 20);
        $perPage = is_numeric($perPageInput) ? (int) $perPageInput : 20;
        $perPage = max(1, min(100, $perPage));

        $paginator = $query->paginate($perPage);

        return new JsonResponse([
            'data' => $paginator->getCollection()->map(static fn (SecurityScan $scan): array => [
                'id' => $scan->id,
                'plugin_id' => $scan->plugin_id,
                'status' => $scan->status,
                'severity' => $scan->severity,
                'findings' => $scan->findings,
                'scanner_version' => $scan->scanner_version,
                'scan_started_at' => $scan->scan_started_at?->toIso8601String(),
                'scan_completed_at' => $scan->scan_completed_at?->toIso8601String(),
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

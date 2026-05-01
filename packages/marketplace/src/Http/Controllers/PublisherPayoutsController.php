<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Controllers;

use Arqel\Marketplace\Models\PluginPayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lista payouts do publisher autenticado (MKTPLC-008).
 *
 * Filtra automaticamente por `publisher_user_id = auth()->id()`.
 */
final class PublisherPayoutsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return new JsonResponse(['message' => 'Unauthenticated'], 401);
        }

        $key = $user->getAuthIdentifier();
        $userId = is_numeric($key) ? (int) $key : 0;

        $rawPerPage = $request->query('per_page', '20');
        $perPage = is_numeric($rawPerPage) ? (int) $rawPerPage : 20;
        $perPage = max(1, min(100, $perPage));

        $payouts = PluginPayout::query()
            ->where('publisher_user_id', $userId)
            ->orderByDesc('id')
            ->paginate($perPage);

        return new JsonResponse([
            'data' => $payouts->getCollection()->map(static fn (PluginPayout $p): array => [
                'id' => $p->id,
                'plugin_id' => $p->plugin_id,
                'amount_cents' => $p->amount_cents,
                'currency' => $p->currency,
                'status' => $p->status,
                'period_start' => $p->period_start->toDateString(),
                'period_end' => $p->period_end->toDateString(),
            ])->all(),
            'meta' => [
                'current_page' => $payouts->currentPage(),
                'per_page' => $payouts->perPage(),
                'total' => $payouts->total(),
                'last_page' => $payouts->lastPage(),
            ],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Controllers;

use Arqel\Marketplace\Contracts\PaymentGateway;
use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginPurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Processa refund de uma compra (MKTPLC-008).
 *
 * Requer Gate `marketplace.refund`. 422 quando a compra já está refunded ou
 * em status que não permite refund.
 */
final class AdminRefundController
{
    public function __invoke(Request $request, PaymentGateway $gateway, string $slug, int $purchaseId): JsonResponse
    {
        if (! Gate::allows('marketplace.refund')) {
            return new JsonResponse(['message' => 'Forbidden'], 403);
        }

        $plugin = Plugin::query()->where('slug', $slug)->first();

        if (! $plugin instanceof Plugin) {
            return new JsonResponse(['message' => "Plugin [{$slug}] not found"], 404);
        }

        /** @var PluginPurchase|null $purchase */
        $purchase = PluginPurchase::query()
            ->where('id', $purchaseId)
            ->where('plugin_id', $plugin->id)
            ->first();

        if (! $purchase instanceof PluginPurchase) {
            return new JsonResponse(['message' => 'Purchase not found'], 404);
        }

        if ($purchase->status === 'refunded') {
            return new JsonResponse([
                'message' => 'Validation failed',
                'errors' => ['purchase' => ['Purchase is already refunded.']],
            ], 422);
        }

        if ($purchase->status !== 'completed') {
            return new JsonResponse([
                'message' => 'Validation failed',
                'errors' => ['purchase' => ['Only completed purchases can be refunded.']],
            ], 422);
        }

        $ok = $gateway->processRefund($purchase);

        if (! $ok) {
            return new JsonResponse([
                'message' => 'Refund failed at gateway',
            ], 422);
        }

        $purchase->update([
            'status' => 'refunded',
            'refunded_at' => now(),
        ]);

        return new JsonResponse([
            'purchase' => [
                'id' => $purchase->id,
                'status' => $purchase->status,
                'refunded_at' => $purchase->refunded_at?->toIso8601String(),
            ],
        ]);
    }
}

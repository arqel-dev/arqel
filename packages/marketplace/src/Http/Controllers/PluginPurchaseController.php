<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Controllers;

use Arqel\Marketplace\Contracts\PaymentGateway;
use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginPurchase;
use Arqel\Marketplace\Services\LicenseKeyGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inicia + confirma compras de plugins premium (MKTPLC-008).
 *
 * - `initiate()` cria `PluginPurchase` em `pending` e retorna a URL de checkout.
 * - `confirm()` valida o pagamento via gateway, marca `completed` e gera license key.
 *
 * Idempotência: se o usuário já tem uma purchase pending para o plugin, ela é reusada.
 * Se já tem uma completed, retorna 200 com a compra existente.
 */
final class PluginPurchaseController
{
    public function initiate(Request $request, PaymentGateway $gateway, string $slug): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return new JsonResponse(['message' => 'Unauthenticated'], 401);
        }

        $plugin = Plugin::query()->where('slug', $slug)->first();

        if (! $plugin instanceof Plugin) {
            return new JsonResponse(['message' => "Plugin [{$slug}] not found"], 404);
        }

        if (! $plugin->isPremium()) {
            return new JsonResponse([
                'message' => 'Validation failed',
                'errors' => ['plugin' => ['Plugin is free.']],
            ], 422);
        }

        $key = $user->getAuthIdentifier();
        $userId = is_numeric($key) ? (int) $key : 0;

        if ($userId === 0) {
            return new JsonResponse(['message' => 'Unauthenticated'], 401);
        }

        /** @var PluginPurchase|null $existingCompleted */
        $existingCompleted = PluginPurchase::query()
            ->where('plugin_id', $plugin->id)
            ->where('buyer_user_id', $userId)
            ->where('status', 'completed')
            ->first();

        if ($existingCompleted instanceof PluginPurchase) {
            return new JsonResponse([
                'purchase' => $this->serialize($existingCompleted),
                'checkout' => null,
                'already_owned' => true,
            ], 200);
        }

        $session = $gateway->createCheckoutSession($plugin, $userId);

        /** @var PluginPurchase|null $existingPending */
        $existingPending = PluginPurchase::query()
            ->where('plugin_id', $plugin->id)
            ->where('buyer_user_id', $userId)
            ->where('status', 'pending')
            ->first();

        if ($existingPending instanceof PluginPurchase) {
            $existingPending->update(['payment_id' => $session->sessionId]);
            $purchase = $existingPending;
        } else {
            /** @var PluginPurchase $purchase */
            $purchase = PluginPurchase::query()->create([
                'plugin_id' => $plugin->id,
                'buyer_user_id' => $userId,
                'license_key' => 'PENDING-'.bin2hex(random_bytes(8)),
                'amount_cents' => $plugin->price_cents,
                'currency' => $plugin->currency,
                'payment_id' => $session->sessionId,
                'status' => 'pending',
            ]);
        }

        return new JsonResponse([
            'purchase' => $this->serialize($purchase),
            'checkout' => [
                'url' => $session->url,
                'session_id' => $session->sessionId,
            ],
        ], 201);
    }

    public function confirm(Request $request, PaymentGateway $gateway, LicenseKeyGenerator $generator, string $slug): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return new JsonResponse(['message' => 'Unauthenticated'], 401);
        }

        $key = $user->getAuthIdentifier();
        $userId = is_numeric($key) ? (int) $key : 0;

        if ($userId === 0) {
            return new JsonResponse(['message' => 'Unauthenticated'], 401);
        }

        $rawPaymentId = $request->input('paymentId');
        if (! is_string($rawPaymentId) || $rawPaymentId === '') {
            return new JsonResponse([
                'message' => 'Validation failed',
                'errors' => ['paymentId' => ['paymentId is required.']],
            ], 422);
        }

        $plugin = Plugin::query()->where('slug', $slug)->first();

        if (! $plugin instanceof Plugin) {
            return new JsonResponse(['message' => "Plugin [{$slug}] not found"], 404);
        }

        /** @var PluginPurchase|null $purchase */
        $purchase = PluginPurchase::query()
            ->where('plugin_id', $plugin->id)
            ->where('buyer_user_id', $userId)
            ->where('payment_id', $rawPaymentId)
            ->first();

        if (! $purchase instanceof PluginPurchase) {
            return new JsonResponse(['message' => 'Purchase not found'], 404);
        }

        if ($purchase->status === 'completed') {
            return new JsonResponse([
                'purchase' => $this->serialize($purchase),
                'idempotent' => true,
            ], 200);
        }

        $result = $gateway->verifyPayment($rawPaymentId);

        if ($result->status !== 'completed') {
            $purchase->update(['status' => 'failed']);

            return new JsonResponse([
                'message' => 'Payment verification failed',
                'purchase' => $this->serialize($purchase),
            ], 422);
        }

        $licenseKey = $generator->generate();

        $purchase->update([
            'status' => 'completed',
            'license_key' => $licenseKey,
            'purchased_at' => now(),
        ]);

        return new JsonResponse([
            'purchase' => $this->serialize($purchase),
        ], 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PluginPurchase $purchase): array
    {
        return [
            'id' => $purchase->id,
            'plugin_id' => $purchase->plugin_id,
            'buyer_user_id' => $purchase->buyer_user_id,
            'license_key' => $purchase->status === 'completed' ? $purchase->license_key : null,
            'amount_cents' => $purchase->amount_cents,
            'currency' => $purchase->currency,
            'status' => $purchase->status,
            'purchased_at' => $purchase->purchased_at?->toIso8601String(),
        ];
    }
}

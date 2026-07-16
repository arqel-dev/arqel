<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Services\Payments;

use Arqel\Marketplace\Contracts\CheckoutSession;
use Arqel\Marketplace\Contracts\PaymentGateway;
use Arqel\Marketplace\Contracts\PaymentResult;
use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginPurchase;

/**
 * Implementação stub usada em testes/dev (MKTPLC-008).
 *
 * Sessões com prefixo `mock_` são sempre tratadas como `completed` no verify.
 * Refunds só passam quando a compra está `completed`.
 */
final readonly class MockPaymentGateway implements PaymentGateway
{
    public function createCheckoutSession(Plugin $plugin, int $userId): CheckoutSession
    {
        $sessionId = 'mock_'.uniqid('', true);

        return new CheckoutSession(
            url: '/marketplace/mock-checkout/'.$plugin->slug,
            sessionId: $sessionId,
        );
    }

    public function verifyPayment(string $paymentId): PaymentResult
    {
        $isMock = str_starts_with($paymentId, 'mock_');

        // Mirrors a real gateway reporting the amount actually charged for this session: look
        // up the purchase this payment_id belongs to and echo back its expected amount, so the
        // amount-matching defense in PluginPurchaseController::confirm() behaves realistically
        // in tests/dev instead of always reporting 0.
        $amountCents = 0;

        if ($isMock) {
            /** @var PluginPurchase|null $purchase */
            $purchase = PluginPurchase::query()->where('payment_id', $paymentId)->first();
            $amountCents = $purchase instanceof PluginPurchase ? $purchase->amount_cents : 0;
        }

        return new PaymentResult(
            status: $isMock ? 'completed' : 'failed',
            amountCents: $amountCents,
            paymentId: $paymentId,
        );
    }

    public function processRefund(PluginPurchase $purchase): bool
    {
        return $purchase->status === 'completed';
    }
}

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

        return new PaymentResult(
            status: $isMock ? 'completed' : 'failed',
            amountCents: 0,
            paymentId: $paymentId,
        );
    }

    public function processRefund(PluginPurchase $purchase): bool
    {
        return $purchase->status === 'completed';
    }
}

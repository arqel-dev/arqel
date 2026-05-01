<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Contracts;

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginPurchase;

/**
 * Strategy contract para gateways de pagamento (MKTPLC-008).
 *
 * Implementações default:
 *
 * - {@see \Arqel\Marketplace\Services\Payments\MockPaymentGateway} — usado em testes/dev.
 * - {@see \Arqel\Marketplace\Services\Payments\StripeConnectGateway} — placeholder; integração
 *   real com Stripe Connect fica para follow-up (TODO MKTPLC-008-stripe-real).
 */
interface PaymentGateway
{
    public function createCheckoutSession(Plugin $plugin, int $userId): CheckoutSession;

    public function verifyPayment(string $paymentId): PaymentResult;

    public function processRefund(PluginPurchase $purchase): bool;
}

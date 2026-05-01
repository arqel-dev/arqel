<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Services\Payments;

use Arqel\Marketplace\Contracts\CheckoutSession;
use Arqel\Marketplace\Contracts\PaymentGateway;
use Arqel\Marketplace\Contracts\PaymentResult;
use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginPurchase;
use RuntimeException;

/**
 * Placeholder para integração futura com Stripe Connect (MKTPLC-008).
 *
 * Todos os métodos lançam `RuntimeException` — a integração real depende
 * de revisão legal/fiscal e SDK `stripe-php`. Host apps podem implementar
 * gateway próprio a qualquer momento e rebindar via container.
 */
final readonly class StripeConnectGateway implements PaymentGateway
{
    public function createCheckoutSession(Plugin $plugin, int $userId): CheckoutSession
    {
        throw new RuntimeException('TODO MKTPLC-008-stripe-real: integrate stripe-php SDK');
    }

    public function verifyPayment(string $paymentId): PaymentResult
    {
        throw new RuntimeException('TODO MKTPLC-008-stripe-real: integrate stripe-php SDK');
    }

    public function processRefund(PluginPurchase $purchase): bool
    {
        throw new RuntimeException('TODO MKTPLC-008-stripe-real: integrate stripe-php SDK');
    }
}

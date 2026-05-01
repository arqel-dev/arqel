<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Contracts;

/**
 * Resultado da verificação de pagamento via {@see PaymentGateway::verifyPayment()}.
 */
final readonly class PaymentResult
{
    public function __construct(
        public string $status,
        public int $amountCents,
        public string $paymentId,
    ) {}
}

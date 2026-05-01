<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Contracts;

/**
 * Sessão de checkout retornada por um {@see PaymentGateway}.
 *
 * `url` é onde o usuário é redirecionado para concluir o pagamento.
 * `sessionId` é a referência opaca usada depois em {@see PaymentGateway::verifyPayment()}.
 */
final readonly class CheckoutSession
{
    public function __construct(
        public string $url,
        public string $sessionId,
    ) {}
}

<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Exceptions;

use RuntimeException;

/**
 * Exceção base do pacote `arqel-dev/marketplace`.
 *
 * Usada por gateways de pagamento para sinalizar falhas operacionais
 * (e.g. Stripe API errors) sem vazar detalhes do SDK.
 */
final class MarketplaceException extends RuntimeException {}

<?php

declare(strict_types=1);

namespace Arqel\Ai\Exceptions;

/**
 * Disparada quando o custo diário acumulado por um usuário específico
 * ultrapassa `arqel-ai.cost_tracking.per_user_limit_usd`. Permite seguir
 * servindo outros usuários enquanto bloqueia o autor da quota estourada.
 */
final class UserLimitExceeded extends AiException {}

<?php

declare(strict_types=1);

namespace Arqel\Ai\Exceptions;

/**
 * Disparada quando a soma diária global de custo de uso AI ultrapassa
 * `arqel-ai.cost_tracking.daily_limit_usd`. Bloqueia novas chamadas até a
 * janela rotacionar (medida em UTC, dia calendário).
 */
final class DailyLimitExceeded extends AiException {}

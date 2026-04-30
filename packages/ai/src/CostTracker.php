<?php

declare(strict_types=1);

namespace Arqel\Ai;

use Arqel\Ai\Exceptions\DailyLimitExceeded;
use Arqel\Ai\Exceptions\UserLimitExceeded;
use Arqel\Ai\Models\AiUsage;
use Illuminate\Support\Carbon;

/**
 * Persiste cada chamada AI em `ai_usage` e enforça os limites
 * configurados em `arqel-ai.cost_tracking.{daily_limit_usd,per_user_limit_usd}`.
 *
 * `assertWithinLimit()` é defensivo: limite `null` ou `<= 0` é tratado
 * como "ilimitado", permitindo que apps em desenvolvimento desactivem
 * o gating sem remover o serviço do pipeline.
 */
final class CostTracker
{
    public function assertWithinLimit(?int $userId): void
    {
        $dailyLimit = $this->floatConfig('arqel-ai.cost_tracking.daily_limit_usd');
        $userLimit = $this->floatConfig('arqel-ai.cost_tracking.per_user_limit_usd');

        if ($dailyLimit !== null && $this->getCostSince() >= $dailyLimit) {
            throw new DailyLimitExceeded(
                "Daily AI limit of \${$dailyLimit} exceeded"
            );
        }

        if ($userId !== null && $userLimit !== null
            && $this->getCostForUserSince($userId) >= $userLimit) {
            throw new UserLimitExceeded(
                "User #{$userId} daily AI limit of \${$userLimit} exceeded"
            );
        }
    }

    public function record(?int $userId, AiCompletionResult $result, string $providerName): void
    {
        AiUsage::query()->create([
            'user_id' => $userId,
            'provider' => $providerName,
            'model' => $result->model,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'cost_usd' => $result->estimatedCost,
        ]);
    }

    public function getCostSince(?Carbon $since = null): float
    {
        $since ??= Carbon::today();

        return (float) AiUsage::query()
            ->where('created_at', '>=', $since)
            ->sum('cost_usd');
    }

    public function getCostForUserSince(int $userId, ?Carbon $since = null): float
    {
        $since ??= Carbon::today();

        return (float) AiUsage::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->sum('cost_usd');
    }

    private function floatConfig(string $key): ?float
    {
        /** @var mixed $value */
        $value = config($key);

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $float = (float) $value;

            return $float > 0.0 ? $float : null;
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Ai;

use Arqel\Ai\Exceptions\DailyLimitExceeded;
use Arqel\Ai\Exceptions\UserLimitExceeded;
use Arqel\Ai\Models\AiUsage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Persiste cada chamada AI em `ai_usage` e enforça os limites
 * configurados em `arqel-ai.cost_tracking.{daily_limit_usd,per_user_limit_usd}`.
 *
 * `assertWithinLimit()` é defensivo em três níveis:
 *
 * - `arqel-ai.cost_tracking.enabled = false` desliga totalmente o
 *   tracker (espelha `AiCache::enabled()`) — zero acessos à base de dados.
 * - Limite `null` ou `<= 0` é tratado como "ilimitado", permitindo que
 *   apps em desenvolvimento desactivem o gating sem remover o serviço.
 * - Quando a tabela `ai_usage` ainda não foi migrada (cenário offline /
 *   fresh-install), as leituras degradam graciosamente para custo 0 em
 *   vez de lançar `QueryException`.
 */
final class CostTracker
{
    private const TABLE = 'ai_usage';

    public function assertWithinLimit(?int $userId): void
    {
        if (! $this->enabled()) {
            return;
        }

        $dailyLimit = $this->floatConfig('arqel-ai.cost_tracking.daily_limit_usd');
        $userLimit = $this->floatConfig('arqel-ai.cost_tracking.per_user_limit_usd');

        if ($dailyLimit !== null && $this->getCostSince() >= $dailyLimit) {
            throw new DailyLimitExceeded(
                (string) __('arqel::messages.ai.daily_limit_exceeded', ['limit' => (string) $dailyLimit]),
            );
        }

        if ($userId !== null && $userLimit !== null
            && $this->getCostForUserSince($userId) >= $userLimit) {
            throw new UserLimitExceeded(
                (string) __('arqel::messages.ai.user_limit_exceeded', [
                    'userId' => (string) $userId,
                    'limit' => (string) $userLimit,
                ]),
            );
        }
    }

    public function record(?int $userId, AiCompletionResult $result, string $providerName): void
    {
        if (! $this->enabled() || ! Schema::hasTable(self::TABLE)) {
            return;
        }

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
        if (! Schema::hasTable(self::TABLE)) {
            return 0.0;
        }

        $since ??= Carbon::today();

        return (float) AiUsage::query()
            ->where('created_at', '>=', $since)
            ->sum('cost_usd');
    }

    public function getCostForUserSince(int $userId, ?Carbon $since = null): float
    {
        if (! Schema::hasTable(self::TABLE)) {
            return 0.0;
        }

        $since ??= Carbon::today();

        return (float) AiUsage::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->sum('cost_usd');
    }

    private function enabled(): bool
    {
        return (bool) config('arqel-ai.cost_tracking.enabled', true);
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

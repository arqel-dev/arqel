<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Services;

use Arqel\Marketplace\Models\Plugin;

/**
 * Calcula `trending_score` por plugin (MKTPLC-007).
 *
 * Heurística simples:
 *   `score = installations_last_7d * 1.0 + recent_positive_reviews * 5.0`
 *
 * Reviews positivas (≥ 4 estrelas, últimos 30 dias) pesam mais que instalações
 * porque sinalizam endorsement de utilizador. Score arredondado em 2 casas.
 */
final readonly class TrendingScoreCalculator
{
    public function __construct() {}

    public function calculate(Plugin $plugin): float
    {
        $installationsLast7d = $plugin->installations()
            ->where('installed_at', '>=', now()->subDays(7))
            ->count();

        $recentPositiveReviews = $plugin->reviews()
            ->published()
            ->where('stars', '>=', 4)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $score = ((float) $installationsLast7d * 1.0) + ((float) $recentPositiveReviews * 5.0);

        return round($score, 2);
    }

    /**
     * Recalcula score para todos os plugins `published`. Retorna a contagem
     * de plugins atualizados.
     */
    public function recalculateAll(): int
    {
        $count = 0;

        Plugin::query()->published()->each(function (Plugin $plugin) use (&$count): void {
            $score = $this->calculate($plugin);
            $plugin->update([
                'trending_score' => $score,
                'trending_score_updated_at' => now(),
            ]);
            $count++;
        });

        return $count;
    }
}

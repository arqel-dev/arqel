<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Console;

use Arqel\Marketplace\Services\TrendingScoreCalculator;
use Illuminate\Console\Command;

/**
 * Recalcula `trending_score` para todos os plugins published (MKTPLC-007).
 *
 * Apps host devem agendar este comando via `Schedule::command(...)->daily()`
 * para manter os feeds de trending atualizados.
 */
final class RecalculateTrendingScoresCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'arqel:marketplace:trending';

    /**
     * @var string
     */
    protected $description = 'Recalculate trending scores for all published marketplace plugins.';

    public function handle(TrendingScoreCalculator $calculator): int
    {
        $count = $calculator->recalculateAll();

        $this->info("Updated {$count} plugins.");

        return self::SUCCESS;
    }
}

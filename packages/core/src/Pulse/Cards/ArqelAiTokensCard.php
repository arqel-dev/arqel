<?php

declare(strict_types=1);

namespace Arqel\Core\Pulse\Cards;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pulse card: Arqel AI tokens consumed today + cost (LCLOUD-003).
 *
 * Reads the optional `arqel_ai_usage` table written by `arqel/ai`.
 * If the table does not exist (apps without the AI package), the
 * card renders 0/0.0 rather than throwing.
 */
final class ArqelAiTokensCard extends \Laravel\Pulse\Livewire\Card
{
    public function render(): View
    {
        $tokens = 0;
        $cost = 0.0;

        try {
            if (Schema::hasTable('arqel_ai_usage')) {
                $start = now()->startOfDay();
                /** @var object{tokens: int|string|null, cost: float|string|null} $row */
                $row = DB::table('arqel_ai_usage')
                    ->selectRaw('COALESCE(SUM(total_tokens), 0) as tokens, COALESCE(SUM(cost_usd), 0) as cost')
                    ->where('created_at', '>=', $start)
                    ->first();

                if ($row !== null) {
                    $tokens = (int) ($row->tokens ?? 0);
                    $cost = (float) ($row->cost ?? 0.0);
                }
            }
        } catch (Throwable) {
            // keep defaults
        }

        return view('arqel::pulse.ai-tokens', [
            'tokens' => $tokens,
            'cost' => $cost,
        ]);
    }
}

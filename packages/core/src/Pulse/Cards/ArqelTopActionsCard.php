<?php

declare(strict_types=1);

namespace Arqel\Core\Pulse\Cards;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pulse card: top 10 Arqel actions by execution count over the last
 * 24h (LCLOUD-003).
 *
 * Source priority:
 *   1. `arqel_audit` table when present (action_name, executed_at).
 *   2. Empty list otherwise (Pulse recorder may still drive its own
 *      aggregate via `arqel_action` key — handled by Pulse itself).
 */
final class ArqelTopActionsCard extends \Laravel\Pulse\Livewire\Card
{
    public function render(): View
    {
        /** @var list<array{action: string, count: int}> $rows */
        $rows = [];

        try {
            if (Schema::hasTable('arqel_audit')) {
                $since = now()->subDay();
                /** @var \Illuminate\Support\Collection<int, object{action: string|null, total: int|string}> $records */
                $records = DB::table('arqel_audit')
                    ->select('action', DB::raw('count(*) as total'))
                    ->where('executed_at', '>=', $since)
                    ->groupBy('action')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->get();

                foreach ($records as $record) {
                    $rows[] = [
                        'action' => is_string($record->action) ? $record->action : 'unknown',
                        'count' => (int) $record->total,
                    ];
                }
            }
        } catch (Throwable) {
            $rows = [];
        }

        return view('arqel::pulse.top-actions', ['rows' => $rows]);
    }
}

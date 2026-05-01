<?php

declare(strict_types=1);

namespace Arqel\Core\Pulse\Cards;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pulse card: Arqel-specific job metrics (LCLOUD-003).
 *
 * Filters `jobs` and `failed_jobs` by `payload->displayName` matching
 * the `Arqel\` namespace. Renders processed/failed counts. When the
 * tables do not exist (memory queue driver), the card renders zeros.
 */
final class ArqelJobMetricsCard extends \Laravel\Pulse\Livewire\Card
{
    public function render(): View
    {
        $pending = 0;
        $failed = 0;

        try {
            if (Schema::hasTable('jobs')) {
                $pending = (int) DB::table('jobs')
                    ->where('payload', 'like', '%"displayName":"Arqel\\\\\\\\%')
                    ->count();
            }
            if (Schema::hasTable('failed_jobs')) {
                $failed = (int) DB::table('failed_jobs')
                    ->where('payload', 'like', '%"displayName":"Arqel\\\\\\\\%')
                    ->count();
            }
        } catch (Throwable) {
            // keep defaults
        }

        return view('arqel::pulse.job-metrics', [
            'pending' => $pending,
            'failed' => $failed,
        ]);
    }
}

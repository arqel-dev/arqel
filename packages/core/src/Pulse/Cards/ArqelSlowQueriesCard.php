<?php

declare(strict_types=1);

namespace Arqel\Core\Pulse\Cards;

use Illuminate\Contracts\View\View;

/**
 * Pulse card: Arqel-specific slow queries (LCLOUD-003).
 *
 * Placeholder for now — Pulse already ships a generic "Slow Queries"
 * card that surfaces the offending SQL + caller. We will wire a
 * scoped version filtered by Arqel resource controllers in a
 * follow-up ticket once we instrument the controllers with explicit
 * query tags.
 *
 * TODO(LCLOUD-003-followup): hook into Pulse's SlowQueries recorder
 * and filter by call-site within `Arqel\Core\Http\Controllers\*`.
 */
final class ArqelSlowQueriesCard extends \Laravel\Pulse\Livewire\Card
{
    public function render(): View
    {
        return view('arqel::pulse.slow-queries');
    }
}

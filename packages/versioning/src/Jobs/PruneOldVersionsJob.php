<?php

declare(strict_types=1);

namespace Arqel\Versioning\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

/**
 * Job queueable que invoca `arqel:versions:prune` (VERS-006).
 *
 * Pensado para schedulers — `$schedule->job(new PruneOldVersionsJob(days: 90))->weekly();`
 * — ou dispatch on-demand a partir de admin actions.
 */
final class PruneOldVersionsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly ?int $days = null,
        public readonly ?int $keep = null,
    ) {}

    public function handle(): void
    {
        /** @var array<string, int|string|bool> $params */
        $params = [];

        if ($this->days !== null) {
            $params['--days'] = $this->days;
        }

        if ($this->keep !== null) {
            $params['--keep'] = $this->keep;
        }

        Artisan::call('arqel:versions:prune', $params);
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Versioning\Console;

use Arqel\Versioning\Models\Version;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Comando de retention para `arqel_versions` (VERS-006).
 *
 * Suporta três modos:
 *
 * - `--days=N` — deleta versions mais antigas que N dias.
 * - `--keep=N` — mantém top-N versions mais recentes por record.
 * - sem flags — usa `arqel-versioning.keep_versions` como default
 *   (modo `--keep` derivado da config).
 *
 * `--dry-run` mostra a contagem sem deletar. Idempotente: rodar duas
 * vezes seguidas é seguro (segunda corrida não encontra nada para
 * apagar).
 */
final class PruneVersionsCommand extends Command
{
    /** @var string */
    protected $signature = 'arqel:versions:prune
        {--days= : Keep versions newer than N days}
        {--keep= : Keep N most recent versions per record}
        {--dry-run : Show count without deleting}';

    /** @var string */
    protected $description = 'Prune old Version rows according to retention policy.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        /** @var mixed $daysOpt */
        $daysOpt = $this->option('days');
        /** @var mixed $keepOpt */
        $keepOpt = $this->option('keep');

        $days = is_numeric($daysOpt) ? (int) $daysOpt : null;
        $keep = is_numeric($keepOpt) ? (int) $keepOpt : null;

        if ($days === null && $keep === null) {
            /** @var mixed $rawKeep */
            $rawKeep = config('arqel-versioning.keep_versions', 50);
            $keep = is_numeric($rawKeep) ? (int) $rawKeep : 50;
        }

        $deleted = 0;

        if ($days !== null && $days > 0) {
            $deleted += $this->pruneByDays($days, $dryRun);
        }

        if ($keep !== null && $keep > 0) {
            $deleted += $this->pruneByKeep($keep, $dryRun);
        }

        if ($dryRun) {
            $this->info("[DRY RUN] would delete {$deleted} rows.");
        } else {
            $this->info("Pruned {$deleted} version rows.");
        }

        return self::SUCCESS;
    }

    private function pruneByDays(int $days, bool $dryRun): int
    {
        $cutoff = Carbon::now()->subDays($days);

        $query = Version::query()->where('created_at', '<', $cutoff);

        if ($dryRun) {
            return (int) $query->count();
        }

        /** @var int $count */
        $count = $query->delete();

        return $count;
    }

    private function pruneByKeep(int $keep, bool $dryRun): int
    {
        $deleted = 0;

        /** @var Collection<int, object{versionable_type: string, versionable_id: int|string}> $groups */
        $groups = Version::query()
            ->select(['versionable_type', 'versionable_id'])
            ->groupBy('versionable_type', 'versionable_id')
            ->get();

        foreach ($groups as $group) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, Version> $stale */
            $stale = Version::query()
                ->where('versionable_type', $group->versionable_type)
                ->where('versionable_id', $group->versionable_id)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->skip($keep)
                ->take(PHP_INT_MAX)
                ->get();

            if ($stale->isEmpty()) {
                continue;
            }

            if ($dryRun) {
                $deleted += $stale->count();

                continue;
            }

            foreach ($stale as $version) {
                $version->delete();
                $deleted++;
            }
        }

        return $deleted;
    }
}

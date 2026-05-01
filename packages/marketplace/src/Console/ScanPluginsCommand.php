<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Console;

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\SecurityScan;
use Arqel\Marketplace\Services\SecurityScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Comando Artisan para executar security scans em massa (MKTPLC-009).
 *
 * Apps host devem agendar via `Schedule::command(...)->daily()`.
 */
final class ScanPluginsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'arqel:marketplace:scan {--plugin= : Slug específico a scanear} {--dry-run : Roda sem persistir SecurityScan}';

    /**
     * @var string
     */
    protected $description = 'Run security scans against marketplace plugins.';

    public function handle(SecurityScanner $scanner): int
    {
        $slug = $this->option('plugin');
        $dryRun = (bool) $this->option('dry-run');

        $query = Plugin::query();
        if (is_string($slug) && $slug !== '') {
            $query->where('slug', $slug);
        } else {
            $query->where('status', 'published');
        }

        $plugins = $query->get();

        $counters = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];

        foreach ($plugins as $plugin) {
            if ($dryRun) {
                DB::beginTransaction();
                try {
                    $scan = $scanner->scan($plugin);
                    $this->bumpCounter($counters, $scan);
                } finally {
                    DB::rollBack();
                }
            } else {
                $scan = $scanner->scan($plugin);
                $this->bumpCounter($counters, $scan);
            }
        }

        $count = $plugins->count();
        $this->info(sprintf(
            'Scanned %d plugins. Findings: %d critical, %d high, %d medium, %d low.',
            $count,
            $counters['critical'],
            $counters['high'],
            $counters['medium'],
            $counters['low'],
        ));

        return self::SUCCESS;
    }

    /**
     * @param array<string, int> $counters
     */
    private function bumpCounter(array &$counters, SecurityScan $scan): void
    {
        $findings = $scan->findings ?? [];
        foreach ($findings as $finding) {
            $severity = $finding['severity'] ?? null;
            if (is_string($severity) && array_key_exists($severity, $counters)) {
                $counters[$severity]++;
            }
        }
    }
}

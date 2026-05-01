<?php

declare(strict_types=1);

namespace Arqel\Core\Console;

use Arqel\Core\Pulse\PulseIntegration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * `arqel:pulse:info` — diagnose the Laravel Pulse integration.
 *
 * Read-only CLI used for debugging Arqel Pulse cards/recorders in
 * production deployments. When Pulse is not installed, prints a
 * neutral message and exits zero — the command is safe to invoke
 * everywhere.
 *
 * Implements LCLOUD-003 from PLANNING/11-fase-4-ecossistema.md.
 */
final class PulseInfoCommand extends Command
{
    /** @var string */
    protected $signature = 'arqel:pulse:info {--json : Output as JSON}';

    /** @var string */
    protected $description = 'Show the status of the Arqel Pulse integration (cards, recorders, samples).';

    public function handle(PulseIntegration $integration): int
    {
        $available = $integration->isAvailable();
        $version = $integration->pulseVersion();
        $cards = $integration->registeredCardTags();
        $recorders = PulseIntegration::RECORDERS;

        $sample = [];
        if ($available) {
            $sample = $this->collectSamples();
        }

        $payload = [
            'available' => $available,
            'pulse_version' => $version,
            'cards' => $cards,
            'recorders' => $recorders,
            'sample' => $sample,
        ];

        if ($this->option('json') === true) {
            $this->line((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->renderHuman($payload);

        return self::SUCCESS;
    }

    /**
     * @return array{ai_tokens_today: int, top_actions_count: int}
     */
    private function collectSamples(): array
    {
        $tokens = 0;
        $topActions = 0;

        try {
            if (Schema::hasTable('arqel_ai_usage')) {
                $row = DB::table('arqel_ai_usage')
                    ->selectRaw('COALESCE(SUM(total_tokens), 0) as tokens')
                    ->where('created_at', '>=', now()->startOfDay())
                    ->first();
                if ($row !== null && property_exists($row, 'tokens')) {
                    $tokens = (int) $row->tokens;
                }
            }
        } catch (Throwable) {
            // ignore
        }

        try {
            if (Schema::hasTable('arqel_audit')) {
                $topActions = (int) DB::table('arqel_audit')
                    ->where('executed_at', '>=', now()->subDay())
                    ->distinct()
                    ->count('action');
            }
        } catch (Throwable) {
            // ignore
        }

        return [
            'ai_tokens_today' => $tokens,
            'top_actions_count' => $topActions,
        ];
    }

    /**
     * @param array{available: bool, pulse_version: ?string, cards: list<string>, recorders: list<string>, sample: array<string, mixed>} $payload
     */
    private function renderHuman(array $payload): void
    {
        $this->line('<fg=cyan;options=bold>Arqel Pulse Integration</>');
        $this->line('');

        if (! $payload['available']) {
            $this->line('  <fg=gray>[info]</> Laravel Pulse not detected. Install with:');
            $this->line('         <fg=yellow>composer require laravel/pulse</>');

            return;
        }

        $this->line('  <fg=green>[ok]</> Pulse detected (version: '.($payload['pulse_version'] ?? 'unknown').')');
        $this->line('  <fg=green>[ok]</> Registered cards: '.count($payload['cards']));
        foreach ($payload['cards'] as $tag) {
            $this->line('       • <fg=cyan>'.$tag.'</>');
        }
        $this->line('  <fg=green>[ok]</> Registered recorders: '.count($payload['recorders']));
        foreach ($payload['recorders'] as $recorder) {
            $this->line('       • <fg=cyan>'.$recorder.'</>');
        }

        $sample = $payload['sample'];
        $this->line('');
        $this->line('<options=bold>Sample data:</>');
        $this->line(sprintf('  AI tokens today:   %s', isset($sample['ai_tokens_today']) ? (string) $sample['ai_tokens_today'] : 'n/a'));
        $this->line(sprintf('  Distinct actions:  %s', isset($sample['top_actions_count']) ? (string) $sample['top_actions_count'] : 'n/a'));
    }
}

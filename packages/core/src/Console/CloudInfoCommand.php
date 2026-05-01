<?php

declare(strict_types=1);

namespace Arqel\Core\Console;

use Arqel\Core\Cloud\CloudDetector;
use Illuminate\Console\Command;

/**
 * `arqel:cloud:info` — print the detected hosting platform and the
 * effective driver configuration.
 *
 * Useful as a deploy-time smoke check: after a Laravel Cloud release,
 * running `php artisan arqel:cloud:info` confirms the runtime saw the
 * cloud signals and that auto-configure is enabled.
 *
 * Output is human-readable by default, or JSON with `--json`.
 *
 * Implements LCLOUD-002 from PLANNING/11-fase-4-ecossistema.md.
 */
final class CloudInfoCommand extends Command
{
    /** @var string */
    protected $signature = 'arqel:cloud:info {--json : Output as JSON}';

    /** @var string */
    protected $description = 'Show detected cloud platform and effective driver configuration.';

    public function handle(CloudDetector $detector): int
    {
        $platform = $detector->description();
        $detected = $detector->isLaravelCloud();
        $autoConfigure = $detector->autoConfigureEnabled();

        $drivers = [
            'filesystems.default' => $this->stringConfig('filesystems.default'),
            'cache.default' => $this->stringConfig('cache.default'),
            'queue.default' => $this->stringConfig('queue.default'),
            'session.driver' => $this->stringConfig('session.driver'),
            'broadcasting.default' => $this->stringConfig('broadcasting.default'),
            'logging.default' => $this->stringConfig('logging.default'),
        ];

        $payload = [
            'platform' => $platform,
            'detected' => $detected,
            'auto_configure' => $autoConfigure,
            'drivers' => $drivers,
        ];

        if ($this->option('json') === true) {
            $this->line((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line('<fg=cyan;options=bold>Arqel Cloud Info</>');
        $this->line('');
        $this->line(sprintf('Platform:        %s', $platform));
        $this->line(sprintf('Detected:        %s', $detected ? 'yes' : 'no'));
        $this->line(sprintf('Auto-configure:  %s', $autoConfigure ? 'enabled' : 'disabled'));
        $this->line('');
        $this->line('<options=bold>Effective drivers:</>');
        foreach ($drivers as $key => $value) {
            $this->line(sprintf('  %-22s %s', $key, $value));
        }

        return self::SUCCESS;
    }

    private function stringConfig(string $key): string
    {
        $value = config($key);

        return is_string($value) ? $value : 'unknown';
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Core\Console;

use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Throwable;

/**
 * `arqel:doctor` — diagnose the health of an Arqel installation.
 *
 * The command runs a curated list of read-only checks against the
 * current Laravel application and reports a structured verdict for
 * each one. It is **idempotent and read-only**: it never mutates
 * filesystem state, never runs migrations, and never writes to the
 * database. Each check is wrapped in a `try/catch` so a single
 * misbehaving probe degrades to a `warn` rather than crashing the
 * whole report.
 *
 * Output modes:
 *   - default: emoji + colour, one check per line
 *   - `--json`: a single line of JSON `{checks: [...], summary: {...}}`
 *
 * Exit code:
 *   - 0 when no `fail` is present (and no `warn` in `--strict` mode)
 *   - 1 otherwise
 *
 * Implements CLI-TUI-004 from PLANNING/11-fase-4-ecossistema.md.
 */
final class DoctorCommand extends Command
{
    /** @var string */
    protected $signature = 'arqel:doctor {--json : Output as JSON} {--strict : Exit non-zero on warnings}';

    /** @var string */
    protected $description = 'Diagnose Arqel installation health: providers, configs, migrations, panels.';

    private const string STATUS_OK = 'ok';

    private const string STATUS_WARN = 'warn';

    private const string STATUS_FAIL = 'fail';

    public function handle(): int
    {
        /** @var list<array{name: string, status: string, message: string, details?: mixed}> $checks */
        $checks = [
            $this->checkPhpVersion(),
            $this->checkLaravelVersion(),
            $this->checkPhpExtensions(),
            $this->checkArqelCoreVersion(),
            $this->checkServiceProvider(),
            $this->checkConfigPublished(),
            $this->checkMigrations(),
            $this->checkStorageWritable(),
            $this->checkCacheDriver(),
            $this->checkSessionDriver(),
        ];

        $summary = $this->summarise($checks);

        if ($this->option('json') === true) {
            $this->line((string) json_encode([
                'checks' => $checks,
                'summary' => $summary,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderHuman($checks, $summary);
        }

        $strict = $this->option('strict') === true;

        if ($summary['fail'] > 0) {
            return self::FAILURE;
        }

        if ($strict && $summary['warn'] > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------
    // Individual checks
    // ---------------------------------------------------------------

    /**
     * @return array{name: string, status: string, message: string, details?: mixed}
     */
    private function checkPhpVersion(): array
    {
        $current = PHP_VERSION;
        $ok = version_compare($current, '8.3.0', '>=');

        return [
            'name' => 'php.version',
            'status' => $ok ? self::STATUS_OK : self::STATUS_FAIL,
            'message' => $ok
                ? "PHP {$current} satisfies >= 8.3."
                : "PHP {$current} is below the required 8.3.",
            'details' => ['current' => $current, 'required' => '>=8.3'],
        ];
    }

    /**
     * @return array{name: string, status: string, message: string, details?: mixed}
     */
    private function checkLaravelVersion(): array
    {
        // Read the version through the container instead of the
        // class constant so PHPStan does not collapse the comparison
        // into a literal-string evaluation.
        $current = $this->getLaravel()->version();
        $ok = version_compare($current, '12.0.0', '>=');

        return [
            'name' => 'laravel.version',
            'status' => $ok ? self::STATUS_OK : self::STATUS_FAIL,
            'message' => $ok
                ? "Laravel {$current} satisfies >= 12.0."
                : "Laravel {$current} is below the required 12.0.",
            'details' => ['current' => $current, 'required' => '>=12.0'],
        ];
    }

    /**
     * @return array{name: string, status: string, message: string, details?: mixed}
     */
    private function checkPhpExtensions(): array
    {
        try {
            $required = ['json', 'pdo', 'mbstring', 'tokenizer', 'openssl'];
            $missing = array_values(array_filter(
                $required,
                static fn (string $ext): bool => ! extension_loaded($ext),
            ));

            return [
                'name' => 'php.extensions',
                'status' => $missing === [] ? self::STATUS_OK : self::STATUS_FAIL,
                'message' => $missing === []
                    ? 'All required PHP extensions are loaded.'
                    : 'Missing PHP extensions: '.implode(', ', $missing).'.',
                'details' => ['required' => $required, 'missing' => $missing],
            ];
        } catch (Throwable $e) {
            return $this->fromThrowable('php.extensions', $e);
        }
    }

    /**
     * @return array{name: string, status: string, message: string, details?: mixed}
     */
    private function checkArqelCoreVersion(): array
    {
        try {
            $version = null;
            if (class_exists(InstalledVersions::class)
                && InstalledVersions::isInstalled('arqel/core')) {
                $version = InstalledVersions::getVersion('arqel/core');
            }

            return [
                'name' => 'arqel.core.version',
                'status' => $version !== null ? self::STATUS_OK : self::STATUS_WARN,
                'message' => $version !== null
                    ? "arqel/core installed at {$version}."
                    : 'arqel/core version could not be determined (path repo / dev install?).',
                'details' => ['version' => $version],
            ];
        } catch (Throwable $e) {
            return $this->fromThrowable('arqel.core.version', $e);
        }
    }

    /**
     * @return array{name: string, status: string, message: string, details?: mixed}
     */
    private function checkServiceProvider(): array
    {
        try {
            $providers = $this->getLaravel()->getLoadedProviders();
            $loaded = isset($providers[\Arqel\Core\ArqelServiceProvider::class])
                && $providers[\Arqel\Core\ArqelServiceProvider::class] === true;

            return [
                'name' => 'arqel.provider',
                'status' => $loaded ? self::STATUS_OK : self::STATUS_FAIL,
                'message' => $loaded
                    ? 'ArqelServiceProvider is registered.'
                    : 'ArqelServiceProvider is not loaded.',
            ];
        } catch (Throwable $e) {
            return $this->fromThrowable('arqel.provider', $e);
        }
    }

    /**
     * @return array{name: string, status: string, message: string, details?: mixed}
     */
    private function checkConfigPublished(): array
    {
        try {
            $config = config('arqel');
            $published = is_array($config) && $config !== [];

            return [
                'name' => 'arqel.config',
                'status' => $published ? self::STATUS_OK : self::STATUS_WARN,
                'message' => $published
                    ? 'Arqel config namespace is loaded.'
                    : 'Arqel config namespace is empty — run `php artisan vendor:publish --tag=arqel-config`.',
            ];
        } catch (Throwable $e) {
            return $this->fromThrowable('arqel.config', $e);
        }
    }

    /**
     * @return array{name: string, status: string, message: string, details?: mixed}
     */
    private function checkMigrations(): array
    {
        try {
            // Inspect the Migrator directly instead of shelling out
            // to `migrate:status`. Running a nested Artisan command
            // hijacks the shared `lastOutput` buffer of the parent
            // command, which would silently truncate `arqel:doctor`'s
            // own output.
            $app = $this->getLaravel();
            if (! $app->bound('migrator')) {
                return [
                    'name' => 'database.migrations',
                    'status' => self::STATUS_WARN,
                    'message' => 'Migrator service is not bound.',
                ];
            }

            /** @var Migrator $migrator */
            $migrator = $app->make('migrator');
            $repository = $migrator->getRepository();

            if (! $repository->repositoryExists()) {
                return [
                    'name' => 'database.migrations',
                    'status' => self::STATUS_WARN,
                    'message' => 'Migrations table does not exist — run `php artisan migrate`.',
                ];
            }

            $configPath = $app->databasePath('migrations');
            $files = is_dir($configPath) ? $migrator->getMigrationFiles($configPath) : [];
            $ran = $repository->getRan();

            $pending = array_values(array_diff(array_keys($files), $ran));

            if ($files === []) {
                return [
                    'name' => 'database.migrations',
                    'status' => self::STATUS_OK,
                    'message' => 'No migrations declared (clean install).',
                ];
            }

            return [
                'name' => 'database.migrations',
                'status' => $pending === [] ? self::STATUS_OK : self::STATUS_WARN,
                'message' => $pending === []
                    ? 'All migrations are up to date.'
                    : count($pending).' migration(s) pending.',
                'details' => ['pending' => $pending],
            ];
        } catch (Throwable $e) {
            return [
                'name' => 'database.migrations',
                'status' => self::STATUS_WARN,
                'message' => 'Could not determine migration status: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{name: string, status: string, message: string, details?: mixed}
     */
    private function checkStorageWritable(): array
    {
        try {
            $path = storage_path('app');
            $writable = is_dir($path) && is_writable($path);

            return [
                'name' => 'storage.writable',
                'status' => $writable ? self::STATUS_OK : self::STATUS_FAIL,
                'message' => $writable
                    ? "storage/app is writable ({$path})."
                    : "storage/app is not writable ({$path}).",
            ];
        } catch (Throwable $e) {
            return $this->fromThrowable('storage.writable', $e);
        }
    }

    /**
     * @return array{name: string, status: string, message: string, details?: mixed}
     */
    private function checkCacheDriver(): array
    {
        try {
            $driverRaw = config('cache.default');
            $driver = is_string($driverRaw) ? $driverRaw : 'unknown';
            $isVolatile = $driver === 'array';

            return [
                'name' => 'cache.driver',
                'status' => $isVolatile ? self::STATUS_WARN : self::STATUS_OK,
                'message' => $isVolatile
                    ? "Cache driver is 'array' — values are not persisted between requests."
                    : "Cache driver is '{$driver}'.",
                'details' => ['driver' => $driver],
            ];
        } catch (Throwable $e) {
            return $this->fromThrowable('cache.driver', $e);
        }
    }

    /**
     * @return array{name: string, status: string, message: string, details?: mixed}
     */
    private function checkSessionDriver(): array
    {
        try {
            $driverRaw = config('session.driver');
            $driver = is_string($driverRaw) ? $driverRaw : 'unknown';
            $isVolatile = $driver === 'array';

            return [
                'name' => 'session.driver',
                'status' => $isVolatile ? self::STATUS_WARN : self::STATUS_OK,
                'message' => $isVolatile
                    ? "Session driver is 'array' — sessions are not persisted between requests."
                    : "Session driver is '{$driver}'.",
                'details' => ['driver' => $driver],
            ];
        } catch (Throwable $e) {
            return $this->fromThrowable('session.driver', $e);
        }
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * @return array{name: string, status: string, message: string, details?: mixed}
     */
    private function fromThrowable(string $name, Throwable $e): array
    {
        return [
            'name' => $name,
            'status' => self::STATUS_WARN,
            'message' => "Check '{$name}' degraded: ".$e->getMessage(),
            'details' => ['exception' => $e::class],
        ];
    }

    /**
     * @param  list<array{name: string, status: string, message: string, details?: mixed}>  $checks
     * @return array{ok: int, warn: int, fail: int}
     */
    private function summarise(array $checks): array
    {
        $summary = ['ok' => 0, 'warn' => 0, 'fail' => 0];
        foreach ($checks as $check) {
            $key = $check['status'];
            if ($key === self::STATUS_OK || $key === self::STATUS_WARN || $key === self::STATUS_FAIL) {
                $summary[$key]++;
            }
        }

        return $summary;
    }

    /**
     * @param  list<array{name: string, status: string, message: string, details?: mixed}>  $checks
     * @param  array{ok: int, warn: int, fail: int}  $summary
     */
    private function renderHuman(array $checks, array $summary): void
    {
        $this->line('<fg=cyan;options=bold>Arqel Doctor</> — diagnostic report');
        $this->line('');

        foreach ($checks as $check) {
            $icon = match ($check['status']) {
                self::STATUS_OK => '<fg=green>[ok]</>   ✅',
                self::STATUS_WARN => '<fg=yellow>[warn]</> ⚠️ ',
                self::STATUS_FAIL => '<fg=red>[fail]</> ❌',
                default => '[?]',
            };

            $this->line(sprintf('%s %s — %s', $icon, $check['name'], $check['message']));
        }

        $this->line('');
        $this->line(sprintf(
            '<options=bold>Summary:</> %d ok • %d warn • %d fail',
            $summary['ok'],
            $summary['warn'],
            $summary['fail'],
        ));
    }
}

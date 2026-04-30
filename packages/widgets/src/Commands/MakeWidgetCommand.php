<?php

declare(strict_types=1);

namespace Arqel\Widgets\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Scaffolds an Arqel widget class under `app/Widgets/`.
 *
 * Mirrors the TENANT-010 / TENANT-011 scaffolder pattern: stub-based,
 * `--force`, idempotent (`make:*` semantics), `setBasePath()` test hook.
 *
 * Supported widget types — the `--type` flag picks which stub to render:
 *   - `stat` (default) → extends `StatWidget`
 *   - `chart` → extends `ChartWidget`
 *   - `table` → extends `TableWidget`
 *   - `custom` → extends `CustomWidget`
 *
 * Snake-cased widget name (used as the constructor `name` arg) is derived
 * from the class name (e.g., `TotalUsers` → `total_users`).
 */
final class MakeWidgetCommand extends Command
{
    private const TYPES = ['stat', 'chart', 'table', 'custom'];

    /** @var string */
    protected $signature = 'arqel:widget {name : Class name (e.g., TotalUsers)} {--type=stat : Widget type (stat|chart|table|custom)} {--force : Overwrite existing files}';

    /** @var string */
    protected $description = 'Scaffold an Arqel widget class';

    private ?string $basePathOverride = null;

    /**
     * Override the application base path used when resolving the
     * destination. Test-only hook.
     */
    public function setBasePath(string $basePath): void
    {
        $this->basePathOverride = rtrim($basePath, DIRECTORY_SEPARATOR);
    }

    public function handle(Filesystem $files): int
    {
        $name = (string) $this->argument('name');
        $type = (string) $this->option('type');
        $force = (bool) $this->option('force');

        if (! in_array($type, self::TYPES, true)) {
            $this->components->error(sprintf(
                'Invalid --type=%s. Allowed: %s.',
                $type,
                implode(', ', self::TYPES),
            ));

            return self::FAILURE;
        }

        $target = $this->basePath('app/Widgets/'.$name.'.php');

        if ($files->exists($target) && ! $force) {
            $this->components->info(sprintf('Widget [%s] already exists; skipped (use --force to overwrite).', $name));

            return self::SUCCESS;
        }

        $stub = (string) $files->get($this->stubPath('widgets/'.$type.'.stub'));
        $contents = strtr($stub, [
            '{{class}}' => $name,
            '{{namespace}}' => 'App\\Widgets',
            '{{snakeName}}' => Str::snake($name),
        ]);

        $files->ensureDirectoryExists(dirname($target));
        $files->put($target, $contents);

        $this->components->info(sprintf('Widget [%s] (%s) scaffolded at app/Widgets/%s.php.', $name, $type, $name));

        return self::SUCCESS;
    }

    private function basePath(string $relative): string
    {
        $base = $this->basePathOverride ?? base_path();

        return rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($relative, DIRECTORY_SEPARATOR);
    }

    private function stubPath(string $relative): string
    {
        return __DIR__.'/../../stubs/'.ltrim($relative, '/');
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Widgets\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Scaffolds an Arqel dashboard wrapper class under `app/Dashboards/`.
 *
 * Mirrors `MakeWidgetCommand` ergonomics. Stub renders a `Dashboard::make`
 * call with the resolved id (snake_case of the class name unless `--id` is
 * passed) and a humanised label.
 */
final class MakeDashboardCommand extends Command
{
    /** @var string */
    protected $signature = 'arqel:dashboard {name : Class name (e.g., AnalyticsDashboard)} {--id= : Dashboard id (defaults to snake_case of name)} {--force : Overwrite existing files}';

    /** @var string */
    protected $description = 'Scaffold an Arqel dashboard class';

    private ?string $basePathOverride = null;

    public function setBasePath(string $basePath): void
    {
        $this->basePathOverride = rtrim($basePath, DIRECTORY_SEPARATOR);
    }

    public function handle(Filesystem $files): int
    {
        $name = (string) $this->argument('name');
        $force = (bool) $this->option('force');
        $idOption = $this->option('id');
        $id = is_string($idOption) && $idOption !== '' ? $idOption : Str::snake($name);
        $label = Str::headline($name);

        $target = $this->basePath('app/Dashboards/'.$name.'.php');

        if ($files->exists($target) && ! $force) {
            $this->components->info(sprintf('Dashboard [%s] already exists; skipped (use --force to overwrite).', $name));

            return self::SUCCESS;
        }

        $stub = (string) $files->get($this->stubPath('dashboards/dashboard.stub'));
        $contents = strtr($stub, [
            '{{class}}' => $name,
            '{{namespace}}' => 'App\\Dashboards',
            '{{id}}' => $id,
            '{{label}}' => $label,
        ]);

        $files->ensureDirectoryExists(dirname($target));
        $files->put($target, $contents);

        $this->components->info(sprintf('Dashboard [%s] scaffolded at app/Dashboards/%s.php.', $name, $name));

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

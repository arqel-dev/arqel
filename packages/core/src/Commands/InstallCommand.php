<?php

declare(strict_types=1);

namespace Arqel\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

final class InstallCommand extends Command
{
    /** @var string */
    protected $signature = 'arqel:install {--force : Overwrite existing files without prompting}';

    /** @var string */
    protected $description = 'Install Arqel and scaffold starter files';

    public function handle(Filesystem $files): int
    {
        $this->displayBanner();

        $this->publishConfig();
        $this->scaffoldDirectories($files);
        $this->scaffoldProvider($files);
        $this->scaffoldLayout($files);
        $this->scaffoldAgentsFile($files);

        $this->displaySuccess();

        return self::SUCCESS;
    }

    protected function displayBanner(): void
    {
        info('Welcome to Arqel — admin panels for Laravel, forged in PHP, rendered in React.');
    }

    protected function publishConfig(): void
    {
        $this->callSilently('vendor:publish', [
            '--tag' => 'arqel-config',
            '--force' => (bool) $this->option('force'),
        ]);

        note('Published config/arqel.php.');
    }

    protected function scaffoldDirectories(Filesystem $files): void
    {
        foreach ($this->scaffoldedDirectories() as $directory) {
            if (! $files->isDirectory($directory)) {
                $files->makeDirectory($directory, 0755, recursive: true);
                note("Created {$this->relative($directory)}.");
            }
        }
    }

    protected function scaffoldProvider(Filesystem $files): void
    {
        $target = app_path('Providers/ArqelServiceProvider.php');

        $this->writeStub(
            $files,
            stub: $this->stubPath('provider.stub'),
            target: $target,
            replacements: [],
        );
    }

    protected function scaffoldLayout(Filesystem $files): void
    {
        $target = resource_path('views/arqel/layout.blade.php');

        $files->ensureDirectoryExists(dirname($target));

        $this->writeStub(
            $files,
            stub: $this->stubPath('layout.stub'),
            target: $target,
            replacements: [],
        );
    }

    protected function scaffoldAgentsFile(Filesystem $files): void
    {
        $target = base_path('AGENTS.md');

        $this->writeStub(
            $files,
            stub: $this->stubPath('agents.stub'),
            target: $target,
            replacements: [
                '{{app_name}}' => is_string($appName = config('app.name')) ? $appName : 'Application',
                '{{arqel_version}}' => 'dev-main',
                '{{php_version}}' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
                '{{laravel_version}}' => app()->version(),
            ],
        );
    }

    protected function displaySuccess(): void
    {
        info('Arqel installed successfully.');

        note('Next steps:');
        note('  1. Run `npm install` to add the Arqel React packages (when published).');
        note('  2. Add `App\\Providers\\ArqelServiceProvider::class` to bootstrap/providers.php.');
        note('  3. Generate your first resource: `php artisan arqel:resource User`.');
    }

    /**
     * @param array<string, string> $replacements
     */
    protected function writeStub(Filesystem $files, string $stub, string $target, array $replacements): void
    {
        if ($files->exists($target) && ! $this->shouldOverwrite($target)) {
            warning("Skipped {$this->relative($target)} (already exists).");

            return;
        }

        $files->ensureDirectoryExists(dirname($target));

        $contents = (string) $files->get($stub);

        if ($replacements !== []) {
            $contents = strtr($contents, $replacements);
        }

        $files->put($target, $contents);

        note("Created {$this->relative($target)}.");
    }

    protected function shouldOverwrite(string $target): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return confirm(
            label: "{$this->relative($target)} already exists. Overwrite?",
            default: false,
        );
    }

    protected function stubPath(string $name): string
    {
        return dirname(__DIR__, 2).'/stubs/'.$name;
    }

    protected function relative(string $path): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }

    /**
     * @return array<int, string>
     */
    protected function scaffoldedDirectories(): array
    {
        return [
            app_path('Arqel/Resources'),
            app_path('Arqel/Widgets'),
            resource_path('js/Pages/Arqel'),
        ];
    }
}

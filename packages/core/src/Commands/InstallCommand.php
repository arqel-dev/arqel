<?php

declare(strict_types=1);

namespace Arqel\Core\Commands;

use Arqel\Core\Support\InteractiveTerminal;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

final class InstallCommand extends Command
{
    /** @var string */
    protected $signature = 'arqel:install
                            {--force : Overwrite existing files without prompting}
                            {--no-frontend : Skip JS package install and resources/js scaffolding}';

    /** @var string */
    protected $description = 'Install Arqel and scaffold starter files';

    /**
     * Process factory — overridable in tests.
     *
     * @var (Closure(array<int, string>, string): Process)|null
     */
    public static ?Closure $processFactory = null;

    public function handle(Filesystem $files): int
    {
        $this->displayBanner();

        $this->publishConfig();
        $this->scaffoldDirectories($files);
        $this->scaffoldProvider($files);
        $this->scaffoldLayout($files);
        $this->scaffoldAgentsFile($files);

        if (! $this->option('no-frontend')) {
            $this->installFrontend($files);
        }

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

    protected function installFrontend(Filesystem $files): void
    {
        if (! $files->exists(base_path('package.json'))) {
            warning('package.json not found — skipping frontend setup. Run `composer require arqel-dev/core` inside a Laravel app to enable it.');

            return;
        }

        $packageManager = $this->detectPackageManager($files);
        $shouldInstall = $this->option('force')
            || ! InteractiveTerminal::supportsPrompts()
            || confirm(
                label: "Install Arqel frontend packages with {$packageManager}?",
                default: true,
            );

        if ($shouldInstall) {
            $this->installFrontendPackages($packageManager);
        }

        $this->scaffoldAppTsx($files);
        $this->scaffoldAppCss($files);
    }

    protected function detectPackageManager(Filesystem $files): string
    {
        if ($files->exists(base_path('pnpm-lock.yaml'))) {
            return 'pnpm';
        }

        if ($files->exists(base_path('yarn.lock'))) {
            return 'yarn';
        }

        if ($files->exists(base_path('package-lock.json'))) {
            return 'npm';
        }

        if (! InteractiveTerminal::supportsPrompts()) {
            return 'pnpm';
        }

        return (string) select(
            label: 'Which package manager would you like to use?',
            options: ['pnpm', 'npm', 'yarn'],
            default: 'pnpm',
        );
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    protected function frontendPackages(): array
    {
        return [
            ['@arqel-dev/react', '@arqel-dev/ui', '@arqel-dev/hooks', '@arqel-dev/fields', '@arqel-dev/types'],
            ['@inertiajs/react', 'react', 'react-dom', '@types/react', '@types/react-dom'],
        ];
    }

    protected function installFrontendPackages(string $packageManager): void
    {
        [$runtime, $devDeps] = $this->frontendPackages();

        $addCommand = $packageManager === 'npm' ? 'install' : 'add';
        $devFlag = $packageManager === 'yarn' ? '--dev' : '-D';

        $runtimeCommand = array_merge([$packageManager, $addCommand], $runtime);
        $devCommand = array_merge([$packageManager, $addCommand, $devFlag], $devDeps);

        note('Installing Arqel runtime packages…');
        $this->runProcess($runtimeCommand);

        note('Installing peer dev dependencies…');
        $this->runProcess($devCommand);
    }

    /**
     * @param list<string> $command
     */
    protected function runProcess(array $command): void
    {
        $factory = self::$processFactory ?? static fn (array $cmd, string $cwd): Process => new Process($cmd, $cwd);

        $process = $factory($command, base_path());
        $process->setTimeout(300);

        if (Process::isTtySupported()) {
            $process->setTty(true);
        }

        $exitCode = $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if ($exitCode !== 0) {
            warning(sprintf(
                '`%s` exited with code %d. You may need to run it manually.',
                implode(' ', $command),
                $exitCode,
            ));
        }
    }

    protected function scaffoldAppTsx(Filesystem $files): void
    {
        $target = resource_path('js/app.tsx');
        $contents = $files->exists($target) ? (string) $files->get($target) : '';
        $marker = "import '@arqel-dev/ui/styles.css'";

        if (str_contains($contents, $marker) && ! $this->option('force')) {
            note('resources/js/app.tsx already configured for Arqel — skipping.');

            return;
        }

        if ($files->exists($target) && ! $this->shouldOverwrite($target)) {
            warning("Skipped {$this->relative($target)} (already exists).");

            return;
        }

        $files->ensureDirectoryExists(dirname($target));

        $stub = (string) $files->get($this->stubPath('app.tsx.stub'));
        $appName = is_string($name = config('app.name')) ? $name : 'Arqel';
        $files->put($target, strtr($stub, ['{{app_name}}' => $appName]));

        note("Created {$this->relative($target)}.");
    }

    protected function scaffoldAppCss(Filesystem $files): void
    {
        $target = resource_path('css/app.css');
        $files->ensureDirectoryExists(dirname($target));

        $existing = $files->exists($target) ? (string) $files->get($target) : '';
        $tailwindImport = "@import 'tailwindcss';";
        $arqelImport = "@import '@arqel-dev/ui/styles.css';";

        $hasTailwind = str_contains($existing, "@import 'tailwindcss'") || str_contains($existing, '@import "tailwindcss"');
        $hasArqel = str_contains($existing, "@import '@arqel-dev/ui/styles.css'") || str_contains($existing, '@import "@arqel-dev/ui/styles.css"');

        if ($hasTailwind && $hasArqel && ! $this->option('force')) {
            note('resources/css/app.css already configured for Arqel — skipping.');

            return;
        }

        $additions = [];
        if (! $hasTailwind) {
            $additions[] = $tailwindImport;
        }
        if (! $hasArqel) {
            $additions[] = $arqelImport;
        }

        $contents = trim($existing);
        if ($additions !== []) {
            $contents = implode("\n", array_filter([$contents, implode("\n", $additions)]));
        }

        $files->put($target, $contents."\n");
        note("Updated {$this->relative($target)}.");
    }

    protected function displaySuccess(): void
    {
        info('Arqel installed successfully.');

        note('Next steps:');
        note('  1. Add `App\\Providers\\ArqelServiceProvider::class` to bootstrap/providers.php.');
        note('  2. Generate your first resource: `php artisan arqel:resource User`.');
        note('  3. Start the dev servers: `php artisan serve` and `pnpm dev` (or your package manager equivalent).');
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

        if (! InteractiveTerminal::supportsPrompts()) {
            warning("{$this->relative($target)} already exists. Skipping (use --force to overwrite).");

            return false;
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

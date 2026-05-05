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
        $this->registerProviderInBootstrap($files);
        $this->scaffoldUserResource($files);
        $this->scaffoldLayout($files);
        $this->scaffoldInertiaMiddleware($files);
        $this->scaffoldViteConfig($files);
        $this->scaffoldHeroImage($files);
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

    protected function scaffoldUserResource(Filesystem $files): void
    {
        $target = app_path('Arqel/Resources/UserResource.php');

        $this->writeStub(
            $files,
            stub: $this->stubPath('user_resource.stub'),
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

    protected function scaffoldHeroImage(Filesystem $files): void
    {
        $target = public_path('login-hero.svg');

        $this->writeStub(
            $files,
            stub: $this->stubPath('login-hero.svg.stub'),
            target: $target,
            replacements: [],
        );
    }

    /**
     * Publish `app/Http/Middleware/HandleInertiaRequests.php` pinned to
     * the `arqel.layout` Blade root. The middleware itself is registered
     * in the `web` group by `ArqelServiceProvider::boot()` (see
     * `provider.stub`), so apps don't need to edit `bootstrap/app.php`.
     */
    protected function scaffoldInertiaMiddleware(Filesystem $files): void
    {
        $target = app_path('Http/Middleware/HandleInertiaRequests.php');

        $this->writeStub(
            $files,
            stub: $this->stubPath('handle_inertia_requests.stub'),
            target: $target,
            replacements: [],
        );
    }

    /**
     * Publish `vite.config.ts` (replacing the Laravel default
     * `vite.config.js`) configured for React + Tailwind v4 + the
     * Arqel Inertia entry points.
     */
    protected function scaffoldViteConfig(Filesystem $files): void
    {
        $target = base_path('vite.config.ts');

        $this->writeStub(
            $files,
            stub: $this->stubPath('vite.config.ts.stub'),
            target: $target,
            replacements: [],
        );

        // Drop the default `vite.config.js` shipped by the Laravel skeleton
        // — having both confuses Vite (it picks one non-deterministically).
        $jsConfig = base_path('vite.config.js');
        if ($files->exists($jsConfig)) {
            $files->delete($jsConfig);
            note('Removed legacy vite.config.js.');
        }
    }

    /**
     * Append `App\Providers\ArqelServiceProvider::class` to
     * `bootstrap/providers.php` if it is not already registered.
     * Idempotent. Falls back to a manual hint when the file does
     * not match the expected `return [...]` shape.
     */
    protected function registerProviderInBootstrap(Filesystem $files): void
    {
        $target = base_path('bootstrap/providers.php');

        if (! $files->exists($target)) {
            warning('bootstrap/providers.php not found — register ArqelServiceProvider manually.');

            return;
        }

        $contents = (string) $files->get($target);

        if (str_contains($contents, 'App\\Providers\\ArqelServiceProvider::class')) {
            note('bootstrap/providers.php already registers ArqelServiceProvider — skipping.');

            return;
        }

        // Match the inner array body: `return [` ... `]`
        if (! preg_match('/return\s*\[(.*)\];/s', $contents, $match)) {
            warning(
                'bootstrap/providers.php does not match the expected shape. '.
                'Add `App\\Providers\\ArqelServiceProvider::class` to the array manually.',
            );

            return;
        }

        $body = rtrim($match[1]);
        $entry = '    App\\Providers\\ArqelServiceProvider::class,';
        $newBody = $body === ''
            ? "\n{$entry}\n"
            : $body."\n{$entry}\n";

        $newContents = str_replace($match[0], "return [{$newBody}];", $contents);
        $files->put($target, $newContents);

        note('Registered ArqelServiceProvider in bootstrap/providers.php.');
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
            ['@arqel-dev/react', '@arqel-dev/ui', '@arqel-dev/auth', '@arqel-dev/hooks', '@arqel-dev/fields', '@arqel-dev/types'],
            ['@inertiajs/react', 'react', 'react-dom', '@types/react', '@types/react-dom', '@vitejs/plugin-react', '@tailwindcss/vite', 'tailwindcss', 'laravel-vite-plugin', 'vite', 'typescript'],
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
        $alreadyConfigured = str_contains($existing, "@import '@arqel-dev/ui/styles.css'")
            || str_contains($existing, '@import "@arqel-dev/ui/styles.css"');

        if ($alreadyConfigured && ! $this->option('force')) {
            note('resources/css/app.css already configured for Arqel — skipping.');

            return;
        }

        // The shadcn-style tokens + Tailwind v4 `@theme inline` bridge live
        // in `@arqel-dev/ui/styles.css`, which itself imports Tailwind. We
        // intentionally do NOT add a second `@import 'tailwindcss'` here —
        // that would duplicate every utility (e.g. two `.hidden` rules) and
        // make later overrides win unpredictably.
        //
        // The `@source` directives below tell Tailwind v4 to scan the
        // built JS of the Arqel JS packages for utility classes used inside
        // their components (Button, Card, AppShell, etc.). Without these,
        // utilities like `md:grid-cols-2` referenced only in node_modules
        // never appear in the generated CSS and components render unstyled.
        $contents = <<<'CSS'
@import '@arqel-dev/ui/styles.css';

@source "../../node_modules/@arqel-dev/ui/dist/**/*.{js,mjs}";
@source "../../node_modules/@arqel-dev/auth/dist/**/*.{js,mjs}";
@source "../../node_modules/@arqel-dev/fields/dist/**/*.{js,mjs}";
@source "../../node_modules/@arqel-dev/react/dist/**/*.{js,mjs}";

CSS;

        $files->put($target, $contents);
        note("Wrote {$this->relative($target)}.");
    }

    protected function displaySuccess(): void
    {
        info('Arqel installed successfully.');

        note('Next steps:');
        note('  1. Run database migrations:');
        note('     php artisan migrate');
        note('  2. Create your first admin user:');
        note('     php artisan arqel:make-user');
        note('  3. Start the dev servers (two terminals):');
        note('     php artisan serve');
        note('     pnpm dev   (or `npm run dev` / `yarn dev`)');
        note('  4. Open http://localhost:8000/admin/login in your browser.');
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

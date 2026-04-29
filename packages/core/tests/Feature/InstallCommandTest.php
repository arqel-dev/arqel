<?php

declare(strict_types=1);

use Arqel\Core\Commands\InstallCommand;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    $this->files = new Filesystem;

    $this->cleanupPaths = [
        config_path('arqel.php'),
        app_path('Arqel'),
        app_path('Providers/ArqelServiceProvider.php'),
        resource_path('js'),
        resource_path('css'),
        resource_path('views/arqel'),
        base_path('AGENTS.md'),
        base_path('package.json'),
        base_path('pnpm-lock.yaml'),
        base_path('yarn.lock'),
        base_path('package-lock.json'),
    ];

    foreach ($this->cleanupPaths as $path) {
        $this->files->isDirectory($path)
            ? $this->files->deleteDirectory($path)
            : $this->files->delete($path);
    }

    InstallCommand::$processFactory = null;
});

afterEach(function (): void {
    foreach ($this->cleanupPaths as $path) {
        $this->files->isDirectory($path)
            ? $this->files->deleteDirectory($path)
            : $this->files->delete($path);
    }

    InstallCommand::$processFactory = null;
});

it('runs the arqel:install command successfully', function (): void {
    $exitCode = Artisan::call('arqel:install', ['--force' => true, '--no-frontend' => true]);

    expect($exitCode)->toBe(0);
});

it('publishes the package config file', function (): void {
    Artisan::call('arqel:install', ['--force' => true, '--no-frontend' => true]);

    expect($this->files->exists(config_path('arqel.php')))->toBeTrue();
});

it('scaffolds the Arqel resource directories', function (): void {
    Artisan::call('arqel:install', ['--force' => true, '--no-frontend' => true]);

    expect($this->files->isDirectory(app_path('Arqel/Resources')))->toBeTrue()
        ->and($this->files->isDirectory(app_path('Arqel/Widgets')))->toBeTrue()
        ->and($this->files->isDirectory(resource_path('js/Pages/Arqel')))->toBeTrue();
});

it('scaffolds the application service provider stub', function (): void {
    Artisan::call('arqel:install', ['--force' => true, '--no-frontend' => true]);

    $contents = (string) $this->files->get(app_path('Providers/ArqelServiceProvider.php'));

    expect($contents)
        ->toContain('namespace App\\Providers;')
        ->toContain('final class ArqelServiceProvider extends ServiceProvider')
        ->not->toContain('{{');
});

it('scaffolds the inertia layout view', function (): void {
    Artisan::call('arqel:install', ['--force' => true, '--no-frontend' => true]);

    $layout = resource_path('views/arqel/layout.blade.php');

    expect($this->files->exists($layout))->toBeTrue()
        ->and((string) $this->files->get($layout))->toContain('@inertia');
});

it('renders AGENTS.md with all required sections and no unsubstituted tokens', function (): void {
    Artisan::call('arqel:install', ['--force' => true, '--no-frontend' => true]);

    $contents = (string) $this->files->get(base_path('AGENTS.md'));

    expect($contents)
        ->toContain('## Project overview')
        ->toContain('## Key conventions')
        ->toContain('## Commands')
        ->toContain('## Architecture')
        ->not->toContain('{{');
});

it('overwrites existing files when --force is passed', function (): void {
    $this->files->ensureDirectoryExists(app_path('Providers'));
    $this->files->put(app_path('Providers/ArqelServiceProvider.php'), '<?php // sentinel');

    Artisan::call('arqel:install', ['--force' => true, '--no-frontend' => true]);

    expect((string) $this->files->get(app_path('Providers/ArqelServiceProvider.php')))
        ->not->toContain('// sentinel')
        ->toContain('final class ArqelServiceProvider');
});

it('skips frontend setup when package.json is missing', function (): void {
    $invocations = collectProcessInvocations();

    Artisan::call('arqel:install', ['--force' => true]);

    expect($this->files->exists(resource_path('js/app.tsx')))->toBeFalse()
        ->and($invocations->count())->toBe(0);
});

it('detects pnpm via lockfile and runs add commands', function (): void {
    $this->files->put(base_path('package.json'), '{}');
    $this->files->put(base_path('pnpm-lock.yaml'), '');
    $invocations = collectProcessInvocations();

    Artisan::call('arqel:install', ['--force' => true]);

    expect($invocations->count())->toBe(2)
        ->and($invocations[0][0])->toBe('pnpm')
        ->and($invocations[0][1])->toBe('add')
        ->and($invocations[0])->toContain('@arqel/ui')
        ->and($invocations[1])->toContain('-D')
        ->and($invocations[1])->toContain('@inertiajs/react');
});

it('detects yarn via lockfile with --dev for dev deps', function (): void {
    $this->files->put(base_path('package.json'), '{}');
    $this->files->put(base_path('yarn.lock'), '');
    $invocations = collectProcessInvocations();

    Artisan::call('arqel:install', ['--force' => true]);

    expect($invocations->count())->toBe(2)
        ->and($invocations[0][0])->toBe('yarn')
        ->and($invocations[1])->toContain('--dev');
});

it('detects npm via lockfile and uses install verb', function (): void {
    $this->files->put(base_path('package.json'), '{}');
    $this->files->put(base_path('package-lock.json'), '{}');
    $invocations = collectProcessInvocations();

    Artisan::call('arqel:install', ['--force' => true]);

    expect($invocations->count())->toBe(2)
        ->and($invocations[0][0])->toBe('npm')
        ->and($invocations[0][1])->toBe('install');
});

it('writes resources/js/app.tsx with the createArqelApp boilerplate', function (): void {
    $this->files->put(base_path('package.json'), '{}');
    $this->files->put(base_path('pnpm-lock.yaml'), '');
    collectProcessInvocations();

    Artisan::call('arqel:install', ['--force' => true]);

    $contents = (string) $this->files->get(resource_path('js/app.tsx'));

    expect($contents)
        ->toContain("import '@arqel/ui/styles.css'")
        ->toContain("import '@arqel/fields/register'")
        ->toContain('createArqelApp')
        ->not->toContain('{{');
});

it('appends arqel imports to existing resources/css/app.css without duplicating', function (): void {
    $this->files->put(base_path('package.json'), '{}');
    $this->files->put(base_path('pnpm-lock.yaml'), '');
    $this->files->ensureDirectoryExists(resource_path('css'));
    $this->files->put(resource_path('css/app.css'), "@import 'tailwindcss';\n");
    collectProcessInvocations();

    Artisan::call('arqel:install', ['--force' => true]);

    $contents = (string) $this->files->get(resource_path('css/app.css'));

    expect(substr_count($contents, "@import 'tailwindcss'"))->toBe(1)
        ->and($contents)->toContain("@import '@arqel/ui/styles.css'");
});

it('surfaces a warning instead of failing when the package install exits non-zero', function (): void {
    $this->files->put(base_path('package.json'), '{}');
    $this->files->put(base_path('pnpm-lock.yaml'), '');
    InstallCommand::$processFactory = static function (array $cmd, string $cwd): Process {
        return new Process(['false']);
    };

    $exitCode = Artisan::call('arqel:install', ['--force' => true]);

    expect($exitCode)->toBe(0)
        ->and($this->files->exists(resource_path('js/app.tsx')))->toBeTrue();
});

/**
 * Replaces the InstallCommand process factory with one that records every
 * invocation and short-circuits the actual exec. Returns an ArrayObject so
 * the recorder can be inspected after Artisan::call returns.
 */
function collectProcessInvocations(): ArrayObject
{
    /** @var ArrayObject<int, list<string>> $invocations */
    $invocations = new ArrayObject;

    InstallCommand::$processFactory = static function (array $cmd, string $cwd) use ($invocations): Process {
        $invocations[] = $cmd;

        return new Process(['true']);
    };

    return $invocations;
}

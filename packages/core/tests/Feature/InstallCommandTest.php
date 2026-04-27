<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->files = new Filesystem;

    $this->cleanupPaths = [
        config_path('arqel.php'),
        app_path('Arqel'),
        app_path('Providers/ArqelServiceProvider.php'),
        resource_path('js/Pages/Arqel'),
        resource_path('views/arqel'),
        base_path('AGENTS.md'),
    ];

    foreach ($this->cleanupPaths as $path) {
        $this->files->isDirectory($path)
            ? $this->files->deleteDirectory($path)
            : $this->files->delete($path);
    }
});

afterEach(function (): void {
    foreach ($this->cleanupPaths as $path) {
        $this->files->isDirectory($path)
            ? $this->files->deleteDirectory($path)
            : $this->files->delete($path);
    }
});

it('runs the arqel:install command successfully', function (): void {
    $exitCode = Artisan::call('arqel:install', ['--force' => true]);

    expect($exitCode)->toBe(0);
});

it('publishes the package config file', function (): void {
    Artisan::call('arqel:install', ['--force' => true]);

    expect($this->files->exists(config_path('arqel.php')))->toBeTrue();
});

it('scaffolds the Arqel resource directories', function (): void {
    Artisan::call('arqel:install', ['--force' => true]);

    expect($this->files->isDirectory(app_path('Arqel/Resources')))->toBeTrue()
        ->and($this->files->isDirectory(app_path('Arqel/Widgets')))->toBeTrue()
        ->and($this->files->isDirectory(resource_path('js/Pages/Arqel')))->toBeTrue();
});

it('scaffolds the application service provider stub', function (): void {
    Artisan::call('arqel:install', ['--force' => true]);

    $contents = (string) $this->files->get(app_path('Providers/ArqelServiceProvider.php'));

    expect($contents)
        ->toContain('namespace App\\Providers;')
        ->toContain('final class ArqelServiceProvider extends ServiceProvider')
        ->not->toContain('{{');
});

it('scaffolds the inertia layout view', function (): void {
    Artisan::call('arqel:install', ['--force' => true]);

    $layout = resource_path('views/arqel/layout.blade.php');

    expect($this->files->exists($layout))->toBeTrue()
        ->and((string) $this->files->get($layout))->toContain('@inertia');
});

it('renders AGENTS.md with all required sections and no unsubstituted tokens', function (): void {
    Artisan::call('arqel:install', ['--force' => true]);

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

    Artisan::call('arqel:install', ['--force' => true]);

    expect((string) $this->files->get(app_path('Providers/ArqelServiceProvider.php')))
        ->not->toContain('// sentinel')
        ->toContain('final class ArqelServiceProvider');
});

<?php

declare(strict_types=1);

use Arqel\Versioning\VersioningServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelPackageTools\Package;

it('boots the provider and exposes default config', function (): void {
    expect(app()->getProvider(VersioningServiceProvider::class))
        ->not->toBeNull();

    expect(config('arqel-versioning.enabled'))->toBeTrue();
    expect(config('arqel-versioning.keep_versions'))->toBe(50);
    expect(config('arqel-versioning.prune_strategy'))->toBe('count');
});

it('publishes the package config file', function (): void {
    $configPath = realpath(__DIR__.'/../../config/arqel-versioning.php');

    expect($configPath)->toBeString();
    expect(file_exists((string) $configPath))->toBeTrue();
});

it('loads the arqel_versions migration', function (): void {
    expect(Schema::hasTable('arqel_versions'))->toBeTrue();
    expect(Schema::hasColumns('arqel_versions', [
        'id',
        'versionable_type',
        'versionable_id',
        'payload',
        'changes',
        'created_by_user_id',
        'reason',
        'created_at',
    ]))->toBeTrue();
});

it('registers a migration that exists on disk and can be published', function (): void {
    $provider = new VersioningServiceProvider(app());
    $package = new Package;
    $provider->configurePackage($package);

    expect($package->migrationFileNames)->toContain('2026_05_01_000000_create_arqel_versions_table');

    foreach ($package->migrationFileNames as $name) {
        expect($name)->toBeString();

        if (! is_string($name)) {
            continue;
        }

        $base = __DIR__.'/../../database/migrations/'.$name;
        $found = file_exists($base.'.php') || file_exists($base.'.php.stub');

        expect($found)->toBeTrue("migration source for '{$name}' not found on disk");
    }

    $exitCode = Artisan::call('vendor:publish', [
        '--tag' => 'arqel-versioning-migrations',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0);
});

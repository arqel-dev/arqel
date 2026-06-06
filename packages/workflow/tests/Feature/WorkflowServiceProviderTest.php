<?php

declare(strict_types=1);

use Arqel\Workflow\WorkflowServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Spatie\LaravelPackageTools\Package;

it('boots the workflow service provider', function (): void {
    $providers = app()->getLoadedProviders();

    expect($providers)->toHaveKey(WorkflowServiceProvider::class)
        ->and($providers[WorkflowServiceProvider::class])->toBeTrue();
});

it('exposes the arqel-workflow config with default fallbacks', function (): void {
    expect(config('arqel-workflow'))->toBeArray()
        ->and(config('arqel-workflow.default_color'))->toBe('secondary')
        ->and(config('arqel-workflow.default_icon'))->toBe('circle');
});

it('registers a migration that exists on disk and can be published', function (): void {
    $provider = new WorkflowServiceProvider(app());
    $package = new Package;
    $provider->configurePackage($package);

    expect($package->migrationFileNames)->toContain('2026_05_01_000000_create_arqel_state_transitions_table');

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
        '--tag' => 'arqel-workflow-migrations',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0);
});

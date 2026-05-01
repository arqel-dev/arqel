<?php

declare(strict_types=1);

use Arqel\Versioning\VersioningServiceProvider;
use Illuminate\Support\Facades\Schema;

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

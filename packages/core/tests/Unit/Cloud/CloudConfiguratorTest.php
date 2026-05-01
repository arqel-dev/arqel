<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Unit\Cloud;

use Arqel\Core\Cloud\CloudConfigurator;
use Arqel\Core\Cloud\CloudDetector;
use Mockery;

/**
 * Build a Mockery double of {@see CloudDetector}. We mock here rather
 * than rely on real env vars so each scenario stays isolated and
 * deterministic across PHP versions.
 */
function makeDetector(bool $isCloud, bool $autoConfigure = true): CloudDetector
{
    /** @var CloudDetector $detector */
    $detector = Mockery::mock(CloudDetector::class);
    $detector->shouldReceive('isLaravelCloud')->andReturn($isCloud);
    $detector->shouldReceive('autoConfigureEnabled')->andReturn($autoConfigure);
    $detector->shouldReceive('description')->andReturn($isCloud ? 'laravel-cloud' : 'unknown');

    return $detector;
}

afterEach(function (): void {
    putenv('REVERB_HOST');
});

it('returns no changes when not running on cloud', function (): void {
    config(['filesystems.default' => 'local']);

    $configurator = new CloudConfigurator(makeDetector(isCloud: false));

    expect($configurator->configure())->toBe([]);
    expect(config('filesystems.default'))->toBe('local');
});

it('returns no changes when auto-configure is disabled', function (): void {
    config(['filesystems.default' => 'local']);

    $configurator = new CloudConfigurator(makeDetector(isCloud: true, autoConfigure: false));

    expect($configurator->configure())->toBe([]);
    expect(config('filesystems.default'))->toBe('local');
});

it('upgrades filesystems.default from local to s3 on cloud', function (): void {
    config(['filesystems.default' => 'local']);

    $configurator = new CloudConfigurator(makeDetector(isCloud: true));
    $changed = $configurator->configure();

    expect($changed)->toContain('filesystems.default');
    expect(config('filesystems.default'))->toBe('s3');
});

it('upgrades cache.default from array/file to redis on cloud', function (): void {
    config(['cache.default' => 'array']);

    $configurator = new CloudConfigurator(makeDetector(isCloud: true));
    $changed = $configurator->configure();

    expect($changed)->toContain('cache.default');
    expect(config('cache.default'))->toBe('redis');
});

it('upgrades queue.default from sync to redis on cloud', function (): void {
    config(['queue.default' => 'sync']);

    $configurator = new CloudConfigurator(makeDetector(isCloud: true));
    $changed = $configurator->configure();

    expect($changed)->toContain('queue.default');
    expect(config('queue.default'))->toBe('redis');
});

it('switches broadcasting.default to reverb when REVERB_HOST is set', function (): void {
    putenv('REVERB_HOST=ws.example.com');
    config(['broadcasting.default' => 'log']);

    $configurator = new CloudConfigurator(makeDetector(isCloud: true));
    $changed = $configurator->configure();

    expect($changed)->toContain('broadcasting.default');
    expect(config('broadcasting.default'))->toBe('reverb');
});

it('does not touch broadcasting.default without REVERB_HOST', function (): void {
    putenv('REVERB_HOST');
    config(['broadcasting.default' => 'log']);

    $configurator = new CloudConfigurator(makeDetector(isCloud: true));
    $changed = $configurator->configure();

    expect($changed)->not->toContain('broadcasting.default');
    expect(config('broadcasting.default'))->toBe('log');
});

it('routes logging.default to stderr on cloud', function (): void {
    config(['logging.default' => 'stack']);

    $configurator = new CloudConfigurator(makeDetector(isCloud: true));
    $changed = $configurator->configure();

    expect($changed)->toContain('logging.default');
    expect(config('logging.default'))->toBe('stderr');
});

it('respects existing non-default filesystem driver', function (): void {
    config(['filesystems.default' => 'minio']);

    $configurator = new CloudConfigurator(makeDetector(isCloud: true));
    $changed = $configurator->configure();

    expect($changed)->not->toContain('filesystems.default');
    expect(config('filesystems.default'))->toBe('minio');
});

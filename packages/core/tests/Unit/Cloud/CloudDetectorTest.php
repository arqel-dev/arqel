<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Unit\Cloud;

use Arqel\Core\Cloud\CloudDetector;

afterEach(function (): void {
    putenv('LARAVEL_CLOUD');
    putenv('CLOUD_ENVIRONMENT');
    $_ENV['LARAVEL_CLOUD'] = null;
    $_SERVER['LARAVEL_CLOUD'] = null;
    unset($_ENV['LARAVEL_CLOUD'], $_SERVER['LARAVEL_CLOUD']);
});

it('isLaravelCloud returns true when LARAVEL_CLOUD env is set', function (): void {
    putenv('LARAVEL_CLOUD=true');
    $_ENV['LARAVEL_CLOUD'] = 'true';
    $_SERVER['LARAVEL_CLOUD'] = 'true';

    $detector = new CloudDetector;

    expect($detector->isLaravelCloud())->toBeTrue();
    expect($detector->description())->toBe('laravel-cloud');
});

it('isLaravelCloud returns true when CLOUD_ENVIRONMENT env is set', function (): void {
    putenv('CLOUD_ENVIRONMENT=production');

    $detector = new CloudDetector;

    expect($detector->isLaravelCloud())->toBeTrue();
});

it('isLaravelCloud returns false without any cloud signal', function (): void {
    putenv('LARAVEL_CLOUD');
    putenv('CLOUD_ENVIRONMENT');
    unset($_ENV['LARAVEL_CLOUD'], $_SERVER['LARAVEL_CLOUD']);

    $detector = new CloudDetector;

    expect($detector->isLaravelCloud())->toBeFalse();
    expect($detector->description())->toBe('unknown');
});

it('autoConfigureEnabled defaults to true', function (): void {
    config(['arqel.cloud.auto_configure' => true]);

    $detector = new CloudDetector;

    expect($detector->autoConfigureEnabled())->toBeTrue();
});

it('autoConfigureEnabled honours config opt-out', function (): void {
    config(['arqel.cloud.auto_configure' => false]);

    $detector = new CloudDetector;

    expect($detector->autoConfigureEnabled())->toBeFalse();
});

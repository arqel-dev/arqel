<?php

declare(strict_types=1);

use Arqel\Audit\AuditServiceProvider;
use Illuminate\Foundation\Application;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

function arqelAuditApp(): Application
{
    /** @var Application $app */
    $app = app();

    return $app;
}

it('boots the service provider cleanly', function (): void {
    $provider = arqelAuditApp()->getProvider(AuditServiceProvider::class);

    expect($provider)->toBeInstanceOf(AuditServiceProvider::class)
        ->and($provider)->toBeInstanceOf(PackageServiceProvider::class);
});

it('registers the package under the arqel-audit name', function (): void {
    $provider = arqelAuditApp()->getProvider(AuditServiceProvider::class);

    expect($provider)->toBeInstanceOf(AuditServiceProvider::class);
    /** @var AuditServiceProvider $provider */
    $reflection = new ReflectionProperty($provider, 'package');
    $package = $reflection->getValue($provider);

    expect($package)->toBeInstanceOf(Package::class);
    /** @var Package $package */
    expect($package->name)->toBe('arqel-audit');
});

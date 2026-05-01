<?php

declare(strict_types=1);

use Arqel\Cli\Exceptions\MarketplaceException;
use Arqel\Cli\Models\PluginMetadata;

it('builds from a fully-populated array', function (): void {
    $meta = PluginMetadata::fromArray([
        'name' => 'stripe',
        'type' => 'fields',
        'composerPackage' => 'acme/stripe',
        'npmPackage' => '@acme/stripe',
        'compat' => ['arqel' => '^1.0'],
        'installerCommand' => 'stripe:install',
    ]);

    expect($meta->name)->toBe('stripe');
    expect($meta->type)->toBe('fields');
    expect($meta->composerPackage)->toBe('acme/stripe');
    expect($meta->npmPackage)->toBe('@acme/stripe');
    expect($meta->compat)->toBe(['arqel' => '^1.0']);
    expect($meta->installerCommand)->toBe('stripe:install');
});

it('throws MarketplaceException when required fields are missing', function (): void {
    expect(fn () => PluginMetadata::fromArray([
        'name' => 'foo',
        'type' => 'fields',
    ]))->toThrow(MarketplaceException::class, "missing required field 'composerPackage'");
});

it('leaves optional fields null when omitted', function (): void {
    $meta = PluginMetadata::fromArray([
        'name' => 'simple',
        'type' => 'theme',
        'composerPackage' => 'acme/theme',
    ]);

    expect($meta->npmPackage)->toBeNull();
    expect($meta->installerCommand)->toBeNull();
    expect($meta->compat)->toBe([]);
});

it('discards non-string compat entries', function (): void {
    $meta = PluginMetadata::fromArray([
        'name' => 'x',
        'type' => 't',
        'composerPackage' => 'a/b',
        'compat' => ['arqel' => '^1.0', 'php' => 8, 'noisy' => ['nope']],
    ]);

    expect($meta->compat)->toBe(['arqel' => '^1.0']);
});

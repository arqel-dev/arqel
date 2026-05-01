<?php

declare(strict_types=1);

use Arqel\Cli\Exceptions\MarketplaceException;
use Arqel\Cli\Services\MarketplaceClient;

it('fetches a plugin happy path with closure stub', function (): void {
    $captured = null;
    $fetcher = function (string $url) use (&$captured): string {
        $captured = $url;

        return json_encode([
            'name' => 'stripe-fields',
            'type' => 'fields',
            'composerPackage' => 'acme/arqel-stripe-fields',
            'npmPackage' => '@acme/arqel-stripe-fields',
            'compat' => ['arqel' => '^1.0'],
            'installerCommand' => 'stripe-fields:install',
        ], JSON_THROW_ON_ERROR);
    };

    $client = new MarketplaceClient('https://api.example.test/marketplace', $fetcher);
    $metadata = $client->fetchPlugin('acme/arqel-stripe-fields');

    expect($captured)->toBe('https://api.example.test/marketplace/plugins/acme%2Farqel-stripe-fields');
    expect($metadata->name)->toBe('stripe-fields');
    expect($metadata->composerPackage)->toBe('acme/arqel-stripe-fields');
    expect($metadata->npmPackage)->toBe('@acme/arqel-stripe-fields');
    expect($metadata->installerCommand)->toBe('stripe-fields:install');
    expect($metadata->compat)->toBe(['arqel' => '^1.0']);
});

it('throws MarketplaceException when fetcher reports failure', function (): void {
    $fetcher = function (string $url): string {
        throw new MarketplaceException("HTTP 404 for {$url}");
    };

    $client = new MarketplaceClient('https://api.example.test/marketplace', $fetcher);

    expect(fn () => $client->fetchPlugin('acme/missing'))
        ->toThrow(MarketplaceException::class, 'HTTP 404');
});

it('throws MarketplaceException on malformed JSON', function (): void {
    $fetcher = fn (): string => '<<<not json>>>';

    $client = new MarketplaceClient('https://api.example.test/marketplace', $fetcher);

    expect(fn () => $client->fetchPlugin('acme/foo'))
        ->toThrow(MarketplaceException::class, 'malformed JSON');
});

it('rejects invalid package identifiers before hitting fetcher', function (): void {
    $called = false;
    $fetcher = function () use (&$called): string {
        $called = true;

        return '{}';
    };

    $client = new MarketplaceClient('https://api.example.test/marketplace', $fetcher);

    expect(fn () => $client->fetchPlugin('not a package'))
        ->toThrow(MarketplaceException::class, 'Invalid package');
    expect($called)->toBeFalse();
});

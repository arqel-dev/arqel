<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginPurchase;
use Arqel\Marketplace\Services\LicenseKeyGenerator;

function lkgPlugin(): Plugin
{
    /** @var Plugin $p */
    $p = Plugin::query()->create([
        'slug' => 'lkg-plugin',
        'name' => 'lkg',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
        'price_cents' => 1000,
    ]);

    return $p;
}

it('generates keys in ARQ-XXXX-XXXX-XXXX-XXXX format', function (): void {
    $gen = new LicenseKeyGenerator;
    $key = $gen->generate();
    expect($key)->toMatch('/^ARQ-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}$/');
});

it('generates unique keys across calls', function (): void {
    $gen = new LicenseKeyGenerator;
    $keys = [];
    for ($i = 0; $i < 50; $i++) {
        $keys[] = $gen->generate();
    }
    expect(count(array_unique($keys)))->toBe(50);
});

it('verifies a valid completed key', function (): void {
    $gen = new LicenseKeyGenerator;
    $plugin = lkgPlugin();
    $key = $gen->generate();

    /** @var PluginPurchase $purchase */
    $purchase = PluginPurchase::query()->create([
        'plugin_id' => $plugin->id,
        'buyer_user_id' => 1,
        'license_key' => $key,
        'amount_cents' => 1000,
        'currency' => 'USD',
        'status' => 'completed',
        'purchased_at' => now(),
    ]);

    expect($gen->verify($key, $purchase))->toBeTrue();
});

it('rejects invalid format or non-completed status', function (): void {
    $gen = new LicenseKeyGenerator;
    $plugin = lkgPlugin();
    $key = $gen->generate();

    /** @var PluginPurchase $purchase */
    $purchase = PluginPurchase::query()->create([
        'plugin_id' => $plugin->id,
        'buyer_user_id' => 1,
        'license_key' => $key,
        'amount_cents' => 1000,
        'currency' => 'USD',
        'status' => 'pending',
    ]);

    // Invalid format
    expect($gen->verify('not-a-key', $purchase))->toBeFalse();

    // Valid format but pending status
    expect($gen->verify($key, $purchase))->toBeFalse();
});

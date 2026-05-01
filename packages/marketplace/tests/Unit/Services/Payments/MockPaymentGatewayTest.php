<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginPurchase;
use Arqel\Marketplace\Services\Payments\MockPaymentGateway;

function mockGwPlugin(): Plugin
{
    /** @var Plugin $p */
    $p = Plugin::query()->create([
        'slug' => 'mockgw-plugin',
        'name' => 'mockgw',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
        'price_cents' => 5000,
    ]);

    return $p;
}

it('createCheckoutSession returns mock url and prefixed session id', function (): void {
    $gateway = new MockPaymentGateway;
    $plugin = mockGwPlugin();

    $session = $gateway->createCheckoutSession($plugin, 42);

    expect($session->url)->toBe('/marketplace/mock-checkout/mockgw-plugin');
    expect($session->sessionId)->toStartWith('mock_');
});

it('verifyPayment returns completed for mock_ ids', function (): void {
    $gateway = new MockPaymentGateway;
    $result = $gateway->verifyPayment('mock_abc123');

    expect($result->status)->toBe('completed');
    expect($result->paymentId)->toBe('mock_abc123');
});

it('verifyPayment returns failed for non-mock ids', function (): void {
    $gateway = new MockPaymentGateway;
    $result = $gateway->verifyPayment('stripe_xyz');

    expect($result->status)->toBe('failed');
});

it('processRefund only succeeds for completed purchases', function (): void {
    $gateway = new MockPaymentGateway;
    $plugin = mockGwPlugin();

    /** @var PluginPurchase $completed */
    $completed = PluginPurchase::query()->create([
        'plugin_id' => $plugin->id,
        'buyer_user_id' => 1,
        'license_key' => 'ARQ-aaaa-bbbb-cccc-dddd',
        'amount_cents' => 5000,
        'currency' => 'USD',
        'status' => 'completed',
        'purchased_at' => now(),
    ]);

    /** @var PluginPurchase $pending */
    $pending = PluginPurchase::query()->create([
        'plugin_id' => $plugin->id,
        'buyer_user_id' => 2,
        'license_key' => 'ARQ-1111-2222-3333-4444',
        'amount_cents' => 5000,
        'currency' => 'USD',
        'status' => 'pending',
    ]);

    expect($gateway->processRefund($completed))->toBeTrue();
    expect($gateway->processRefund($pending))->toBeFalse();
});

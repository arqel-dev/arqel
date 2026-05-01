<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginPurchase;
use Arqel\Marketplace\Tests\Fixtures\TestUser;

function purPlugin(array $overrides = []): Plugin
{
    /** @var Plugin $p */
    $p = Plugin::query()->create(array_merge([
        'slug' => 'paid-widget',
        'name' => 'paid widget',
        'description' => 'desc',
        'type' => 'widget',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
        'price_cents' => 2500,
        'currency' => 'USD',
    ], $overrides));

    return $p;
}

function purBuyer(string $name = 'buyer'): TestUser
{
    /** @var TestUser $u */
    $u = TestUser::query()->create(['name' => $name]);

    return $u;
}

it('initiates a purchase and returns checkout url', function (): void {
    $plugin = purPlugin();
    $buyer = purBuyer();

    $response = $this->actingAs($buyer)
        ->postJson("/api/marketplace/plugins/{$plugin->slug}/purchase");

    $response->assertStatus(201);
    $response->assertJsonPath('purchase.status', 'pending');
    $response->assertJsonPath('purchase.amount_cents', 2500);
    $response->assertJsonStructure(['purchase', 'checkout' => ['url', 'session_id']]);
    expect($response->json('checkout.url'))->toBe('/marketplace/mock-checkout/paid-widget');
});

it('rejects purchase for free plugin with 422', function (): void {
    $plugin = purPlugin(['slug' => 'free-widget', 'price_cents' => 0]);
    $buyer = purBuyer();

    $this->actingAs($buyer)
        ->postJson("/api/marketplace/plugins/{$plugin->slug}/purchase")
        ->assertStatus(422);
});

it('returns 401 when unauthenticated', function (): void {
    $plugin = purPlugin();

    $this->postJson("/api/marketplace/plugins/{$plugin->slug}/purchase")
        ->assertStatus(401);
});

it('confirms purchase and generates license key', function (): void {
    $plugin = purPlugin();
    $buyer = purBuyer();

    $initiate = $this->actingAs($buyer)
        ->postJson("/api/marketplace/plugins/{$plugin->slug}/purchase");
    $sessionId = $initiate->json('checkout.session_id');

    $confirm = $this->actingAs($buyer)
        ->postJson("/api/marketplace/plugins/{$plugin->slug}/purchase/confirm", [
            'paymentId' => $sessionId,
        ]);

    $confirm->assertOk();
    $confirm->assertJsonPath('purchase.status', 'completed');
    expect($confirm->json('purchase.license_key'))
        ->toMatch('/^ARQ-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}$/');
});

it('is idempotent on confirm and re-initiate', function (): void {
    $plugin = purPlugin();
    $buyer = purBuyer();

    // Initiate twice → reuses pending row
    $first = $this->actingAs($buyer)
        ->postJson("/api/marketplace/plugins/{$plugin->slug}/purchase");
    $second = $this->actingAs($buyer)
        ->postJson("/api/marketplace/plugins/{$plugin->slug}/purchase");

    expect($first->json('purchase.id'))->toBe($second->json('purchase.id'));

    $sessionId = $second->json('checkout.session_id');

    // Confirm
    $this->actingAs($buyer)
        ->postJson("/api/marketplace/plugins/{$plugin->slug}/purchase/confirm", [
            'paymentId' => $sessionId,
        ])->assertOk();

    // Re-initiate after completion → returns already_owned without new pending
    $third = $this->actingAs($buyer)
        ->postJson("/api/marketplace/plugins/{$plugin->slug}/purchase");
    $third->assertOk();
    expect($third->json('already_owned'))->toBeTrue();

    expect(PluginPurchase::query()->where('plugin_id', $plugin->id)->count())->toBe(1);
});

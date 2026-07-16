<?php

declare(strict_types=1);

use Arqel\Marketplace\Contracts\CheckoutSession;
use Arqel\Marketplace\Contracts\PaymentGateway;
use Arqel\Marketplace\Contracts\PaymentResult;
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

it('returns 404 when initiating a purchase for an archived plugin', function (): void {
    $plugin = purPlugin(['status' => 'archived']);
    $buyer = purBuyer();

    $this->actingAs($buyer)
        ->postJson("/api/marketplace/plugins/{$plugin->slug}/purchase")
        ->assertStatus(404);
});

it('returns 404 when confirming a purchase for an archived plugin', function (): void {
    $plugin = purPlugin();
    $buyer = purBuyer();

    $initiate = $this->actingAs($buyer)
        ->postJson("/api/marketplace/plugins/{$plugin->slug}/purchase");
    $sessionId = $initiate->json('checkout.session_id');

    // Plugin gets archived (e.g. auto-delisted by SecurityScanner) after checkout started.
    $plugin->update(['status' => 'archived']);

    $this->actingAs($buyer)
        ->postJson("/api/marketplace/plugins/{$plugin->slug}/purchase/confirm", [
            'paymentId' => $sessionId,
        ])
        ->assertStatus(404);
});

it('returns 404 for pending (not yet published) plugin on initiate', function (): void {
    $plugin = purPlugin(['status' => 'pending']);
    $buyer = purBuyer();

    $this->actingAs($buyer)
        ->postJson("/api/marketplace/plugins/{$plugin->slug}/purchase")
        ->assertStatus(404);
});

it('does not complete the purchase when gateway amount does not match expected amount', function (): void {
    $plugin = purPlugin(['price_cents' => 2500]);
    $buyer = purBuyer();

    $initiate = $this->actingAs($buyer)
        ->postJson("/api/marketplace/plugins/{$plugin->slug}/purchase");
    $sessionId = $initiate->json('checkout.session_id');

    // Simulate a gateway that confirms payment as completed but reports a tampered/incorrect
    // amount (e.g. a compromised webhook payload) — the confirm step must reject this even
    // though the gateway says the payment itself succeeded.
    $this->app->bind(PaymentGateway::class, fn () => new class implements PaymentGateway
    {
        public function createCheckoutSession(Plugin $plugin, int $userId): CheckoutSession
        {
            return new CheckoutSession(url: '/mock', sessionId: 'mock_tampered');
        }

        public function verifyPayment(string $paymentId): PaymentResult
        {
            return new PaymentResult(status: 'completed', amountCents: 100, paymentId: $paymentId);
        }

        public function processRefund(PluginPurchase $purchase): bool
        {
            return false;
        }
    });

    $confirm = $this->actingAs($buyer)
        ->postJson("/api/marketplace/plugins/{$plugin->slug}/purchase/confirm", [
            'paymentId' => $sessionId,
        ]);

    $confirm->assertStatus(422);
    expect(PluginPurchase::query()->where('plugin_id', $plugin->id)->first()->status)
        ->not->toBe('completed');
});

it('completes the purchase when gateway amount matches expected amount', function (): void {
    $plugin = purPlugin(['price_cents' => 2500]);
    $buyer = purBuyer();

    $initiate = $this->actingAs($buyer)
        ->postJson("/api/marketplace/plugins/{$plugin->slug}/purchase");
    $sessionId = $initiate->json('checkout.session_id');

    // MockPaymentGateway reports the amount actually stored on the purchase for the session,
    // mirroring a real gateway confirming the true charged amount.
    $confirm = $this->actingAs($buyer)
        ->postJson("/api/marketplace/plugins/{$plugin->slug}/purchase/confirm", [
            'paymentId' => $sessionId,
        ]);

    $confirm->assertOk();
    $confirm->assertJsonPath('purchase.status', 'completed');
});

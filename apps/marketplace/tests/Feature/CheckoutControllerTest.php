<?php

declare(strict_types=1);

use App\Tests\Fixtures\TestUser;
use Arqel\Marketplace\Contracts\CheckoutSession;
use Arqel\Marketplace\Contracts\PaymentGateway;
use Arqel\Marketplace\Contracts\PaymentResult;
use Arqel\Marketplace\Events\PluginPurchased;
use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginPurchase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;

function premiumPlugin(array $overrides = []): Plugin
{
    /** @var Plugin $p */
    $p = Plugin::query()->create(array_merge([
        'slug' => 'paid',
        'name' => 'Paid',
        'description' => 'a paid plugin',
        'type' => 'field',
        'github_url' => 'https://github.com/x/paid',
        'license' => 'MIT',
        'status' => 'published',
        'price_cents' => 2500,
        'currency' => 'USD',
        'revenue_share_percent' => 80,
    ], $overrides));

    return $p;
}

function checkoutUser(): TestUser
{
    /** @var TestUser $u */
    $u = TestUser::query()->create(['name' => 'buyer']);

    return $u;
}

it('renders the checkout start page with plugin summary', function (): void {
    $plugin = premiumPlugin();
    $user = checkoutUser();

    $this->actingAs($user)
        ->get('/checkout/'.$plugin->slug)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Marketplace/Checkout')
            ->where('plugin.slug', 'paid')
            ->where('summary.price_cents', 2500)
            ->where('summary.currency', 'USD')
            ->where('summary.fee_estimate_cents', 500)
            ->where('summary.total_cents', 2500),
        );
});

it('rejects checkout for free plugins', function (): void {
    $plugin = premiumPlugin(['slug' => 'free-one', 'price_cents' => 0]);
    $user = checkoutUser();

    $this->actingAs($user)
        ->get('/checkout/'.$plugin->slug)
        ->assertStatus(302); // ValidationException redirects with errors

    $this->actingAs($user)
        ->getJson('/checkout/'.$plugin->slug)
        ->assertStatus(422);
});

it('rejects checkout when user already owns plugin', function (): void {
    $plugin = premiumPlugin();
    $user = checkoutUser();

    PluginPurchase::query()->create([
        'plugin_id' => $plugin->id,
        'buyer_user_id' => $user->id,
        'license_key' => 'ARQ-aaaa-bbbb-cccc-dddd',
        'amount_cents' => 2500,
        'currency' => 'USD',
        'status' => 'completed',
        'purchased_at' => now(),
    ]);

    $this->actingAs($user)
        ->getJson('/checkout/'.$plugin->slug)
        ->assertStatus(422);
});

it('initiate creates pending purchase and redirects to gateway URL', function (): void {
    $plugin = premiumPlugin();
    $user = checkoutUser();

    $fake = new class implements PaymentGateway
    {
        public function createCheckoutSession(Plugin $plugin, int $userId): CheckoutSession
        {
            return new CheckoutSession(
                url: 'https://gateway.test/session/abc123',
                sessionId: 'mock_abc123',
            );
        }

        public function verifyPayment(string $paymentId): PaymentResult
        {
            return new PaymentResult('completed', 0, $paymentId);
        }

        public function processRefund(PluginPurchase $purchase): bool
        {
            return true;
        }
    };
    app()->instance(PaymentGateway::class, $fake);

    $response = $this->actingAs($user)
        ->post('/checkout/'.$plugin->slug.'/initiate');

    $response->assertRedirect('https://gateway.test/session/abc123');

    expect(PluginPurchase::query()
        ->where('plugin_id', $plugin->id)
        ->where('buyer_user_id', $user->id)
        ->where('status', 'pending')
        ->where('payment_id', 'mock_abc123')
        ->exists())->toBeTrue();
});

it('blocks unauthenticated checkout requests', function (): void {
    $plugin = premiumPlugin();

    $this->getJson('/checkout/'.$plugin->slug)
        ->assertStatus(401);

    $this->postJson('/checkout/'.$plugin->slug.'/initiate')
        ->assertStatus(401);
});

it('success completes payment and renders license key', function (): void {
    Event::fake([PluginPurchased::class]);

    $plugin = premiumPlugin();
    $user = checkoutUser();

    $purchase = PluginPurchase::query()->create([
        'plugin_id' => $plugin->id,
        'buyer_user_id' => $user->id,
        'license_key' => 'PENDING-deadbeef',
        'amount_cents' => 2500,
        'currency' => 'USD',
        'payment_id' => 'mock_xyz',
        'status' => 'pending',
    ]);

    $fake = new class implements PaymentGateway
    {
        public function createCheckoutSession(Plugin $plugin, int $userId): CheckoutSession
        {
            return new CheckoutSession('https://x', 'mock_xyz');
        }

        public function verifyPayment(string $paymentId): PaymentResult
        {
            return new PaymentResult('completed', 2500, $paymentId);
        }

        public function processRefund(PluginPurchase $purchase): bool
        {
            return true;
        }
    };
    app()->instance(PaymentGateway::class, $fake);

    $this->actingAs($user)
        ->get('/checkout/'.$plugin->slug.'/success?session_id=mock_xyz')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Marketplace/CheckoutSuccess')
            ->where('plugin.slug', 'paid')
            ->where('download_url', '/plugins/paid/download')
            ->has('license_key'),
        );

    $purchase->refresh();
    expect($purchase->status)->toBe('completed');
    expect($purchase->license_key)->toMatch('/^ARQ-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}$/');

    Event::assertDispatched(PluginPurchased::class);
});

it('success rejects when gateway returns non-completed status', function (): void {
    $plugin = premiumPlugin();
    $user = checkoutUser();

    PluginPurchase::query()->create([
        'plugin_id' => $plugin->id,
        'buyer_user_id' => $user->id,
        'license_key' => 'PENDING-x',
        'amount_cents' => 2500,
        'currency' => 'USD',
        'payment_id' => 'pending_session',
        'status' => 'pending',
    ]);

    $fake = new class implements PaymentGateway
    {
        public function createCheckoutSession(Plugin $plugin, int $userId): CheckoutSession
        {
            return new CheckoutSession('https://x', 'x');
        }

        public function verifyPayment(string $paymentId): PaymentResult
        {
            return new PaymentResult('pending', 0, $paymentId);
        }

        public function processRefund(PluginPurchase $purchase): bool
        {
            return false;
        }
    };
    app()->instance(PaymentGateway::class, $fake);

    $this->actingAs($user)
        ->getJson('/checkout/'.$plugin->slug.'/success?session_id=pending_session')
        ->assertStatus(422);
});

it('success requires session_id', function (): void {
    $plugin = premiumPlugin();
    $user = checkoutUser();

    $this->actingAs($user)
        ->getJson('/checkout/'.$plugin->slug.'/success')
        ->assertStatus(422);
});

it('renders the cancel page', function (): void {
    $plugin = premiumPlugin();
    $user = checkoutUser();

    $this->actingAs($user)
        ->get('/checkout/'.$plugin->slug.'/cancel')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Marketplace/CheckoutCancelled')
            ->where('plugin.slug', 'paid'),
        );
});

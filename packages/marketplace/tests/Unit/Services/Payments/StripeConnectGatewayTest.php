<?php

declare(strict_types=1);

use Arqel\Marketplace\Exceptions\MarketplaceException;
use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginPurchase;
use Arqel\Marketplace\Services\Payments\StripeConnectGateway;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Exception\ApiErrorException;
use Stripe\Service\Checkout\CheckoutServiceFactory;
use Stripe\Service\Checkout\SessionService;
use Stripe\Service\RefundService;
use Stripe\StripeClient;

function stripeGwPlugin(array $overrides = []): Plugin
{
    /** @var Plugin $p */
    $p = Plugin::query()->create(array_merge([
        'slug' => 'stripe-gw-plugin',
        'name' => 'Stripe GW Plugin',
        'description' => 'A premium plugin for Stripe gateway test purposes covering edge cases.',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
        'price_cents' => 5000,
        'currency' => 'USD',
    ], $overrides));

    return $p;
}

it('instantiates real StripeClient when no mock provided and SDK is available', function (): void {
    // When SDK is installed, the constructor must NOT throw and must build a real client.
    $gateway = new StripeConnectGateway('sk_test_dummy', 'acct_platform');

    expect($gateway)->toBeInstanceOf(StripeConnectGateway::class);
});

it('createCheckoutSession returns url and sessionId via Stripe client', function (): void {
    $session = StripeSession::constructFrom([
        'id' => 'cs_test_123',
        'url' => 'https://checkout.stripe.com/pay/cs_test_123',
    ]);

    $sessionService = Mockery::mock(SessionService::class);
    $sessionService->shouldReceive('create')
        ->once()
        ->andReturn($session);

    $checkoutFactory = Mockery::mock(CheckoutServiceFactory::class);
    $checkoutFactory->sessions = $sessionService;

    $client = Mockery::mock(StripeClient::class);
    $client->checkout = $checkoutFactory;

    $gateway = new StripeConnectGateway(
        secretKey: 'sk_test',
        platformAccountId: 'acct_platform',
        client: $client,
    );

    $plugin = stripeGwPlugin();
    $result = $gateway->createCheckoutSession($plugin, 42);

    expect($result->url)->toBe('https://checkout.stripe.com/pay/cs_test_123');
    expect($result->sessionId)->toBe('cs_test_123');
});

it('createCheckoutSession includes application_fee_amount and transfer_data when publisher has stripe account', function (): void {
    $session = StripeSession::constructFrom([
        'id' => 'cs_test_connect',
        'url' => 'https://checkout.stripe.com/pay/cs_test_connect',
    ]);

    $capturedParams = null;

    $sessionService = Mockery::mock(SessionService::class);
    $sessionService->shouldReceive('create')
        ->once()
        ->with(Mockery::on(function (array $params) use (&$capturedParams): bool {
            $capturedParams = $params;

            return true;
        }))
        ->andReturn($session);

    $checkoutFactory = Mockery::mock(CheckoutServiceFactory::class);
    $checkoutFactory->sessions = $sessionService;

    $client = Mockery::mock(StripeClient::class);
    $client->checkout = $checkoutFactory;

    $gateway = new StripeConnectGateway(
        secretKey: 'sk_test',
        platformAccountId: 'acct_platform',
        platformFeePercent: 20,
        client: $client,
    );

    $plugin = stripeGwPlugin([
        'slug' => 'stripe-gw-connect',
        'price_cents' => 10000,
        'publisher_stripe_account_id' => 'acct_publisher_123',
    ]);

    $gateway->createCheckoutSession($plugin, 7);

    expect($capturedParams)->toHaveKey('payment_intent_data');
    expect($capturedParams['payment_intent_data']['application_fee_amount'])->toBe(2000);
    expect($capturedParams['payment_intent_data']['transfer_data']['destination'])->toBe('acct_publisher_123');
});

it('verifyPayment returns completed when session is paid', function (): void {
    $session = StripeSession::constructFrom([
        'id' => 'cs_paid',
        'payment_status' => 'paid',
        'amount_total' => 5000,
    ]);

    $sessionService = Mockery::mock(SessionService::class);
    $sessionService->shouldReceive('retrieve')
        ->with('cs_paid')
        ->once()
        ->andReturn($session);

    $checkoutFactory = Mockery::mock(CheckoutServiceFactory::class);
    $checkoutFactory->sessions = $sessionService;

    $client = Mockery::mock(StripeClient::class);
    $client->checkout = $checkoutFactory;

    $gateway = new StripeConnectGateway(
        secretKey: 'sk_test',
        platformAccountId: 'acct_platform',
        client: $client,
    );

    $result = $gateway->verifyPayment('cs_paid');

    expect($result->status)->toBe('completed');
    expect($result->amountCents)->toBe(5000);
    expect($result->paymentId)->toBe('cs_paid');
});

it('verifyPayment returns pending when session is not paid', function (): void {
    $session = StripeSession::constructFrom([
        'id' => 'cs_unpaid',
        'payment_status' => 'unpaid',
        'amount_total' => 5000,
    ]);

    $sessionService = Mockery::mock(SessionService::class);
    $sessionService->shouldReceive('retrieve')
        ->with('cs_unpaid')
        ->once()
        ->andReturn($session);

    $checkoutFactory = Mockery::mock(CheckoutServiceFactory::class);
    $checkoutFactory->sessions = $sessionService;

    $client = Mockery::mock(StripeClient::class);
    $client->checkout = $checkoutFactory;

    $gateway = new StripeConnectGateway(
        secretKey: 'sk_test',
        platformAccountId: 'acct_platform',
        client: $client,
    );

    $result = $gateway->verifyPayment('cs_unpaid');

    expect($result->status)->toBe('pending');
});

it('verifyPayment wraps Stripe API errors in MarketplaceException', function (): void {
    Log::spy();

    $sessionService = Mockery::mock(SessionService::class);
    $sessionService->shouldReceive('retrieve')
        ->andThrow(new class('boom') extends ApiErrorException {});

    $checkoutFactory = Mockery::mock(CheckoutServiceFactory::class);
    $checkoutFactory->sessions = $sessionService;

    $client = Mockery::mock(StripeClient::class);
    $client->checkout = $checkoutFactory;

    $gateway = new StripeConnectGateway(
        secretKey: 'sk_test',
        platformAccountId: 'acct_platform',
        client: $client,
    );

    expect(fn () => $gateway->verifyPayment('cs_bad'))
        ->toThrow(MarketplaceException::class);

    Log::shouldHaveReceived('warning')->once();
});

it('processRefund returns true on happy path', function (): void {
    $refundService = Mockery::mock(RefundService::class);
    $refundService->shouldReceive('create')
        ->once()
        ->andReturn(new stdClass);

    $client = Mockery::mock(StripeClient::class);
    $client->refunds = $refundService;

    $gateway = new StripeConnectGateway(
        secretKey: 'sk_test',
        platformAccountId: 'acct_platform',
        client: $client,
    );

    $plugin = stripeGwPlugin(['slug' => 'stripe-refund-happy']);
    /** @var PluginPurchase $purchase */
    $purchase = PluginPurchase::query()->create([
        'plugin_id' => $plugin->id,
        'buyer_user_id' => 1,
        'license_key' => 'ARQ-aaaa-bbbb-cccc-dddd',
        'amount_cents' => 5000,
        'currency' => 'USD',
        'payment_id' => 'pi_test_123',
        'status' => 'completed',
        'purchased_at' => now(),
    ]);

    expect($gateway->processRefund($purchase))->toBeTrue();
});

it('processRefund returns false when Stripe API throws', function (): void {
    Log::spy();

    $refundService = Mockery::mock(RefundService::class);
    $refundService->shouldReceive('create')
        ->andThrow(new class('refund failed') extends ApiErrorException {});

    $client = Mockery::mock(StripeClient::class);
    $client->refunds = $refundService;

    $gateway = new StripeConnectGateway(
        secretKey: 'sk_test',
        platformAccountId: 'acct_platform',
        client: $client,
    );

    $plugin = stripeGwPlugin(['slug' => 'stripe-refund-fail']);
    /** @var PluginPurchase $purchase */
    $purchase = PluginPurchase::query()->create([
        'plugin_id' => $plugin->id,
        'buyer_user_id' => 1,
        'license_key' => 'ARQ-1111-2222-3333-4444',
        'amount_cents' => 5000,
        'currency' => 'USD',
        'payment_id' => 'pi_bad',
        'status' => 'completed',
        'purchased_at' => now(),
    ]);

    expect($gateway->processRefund($purchase))->toBeFalse();
    Log::shouldHaveReceived('warning')->once();
});

it('processRefund returns false when purchase has no payment_id', function (): void {
    $client = Mockery::mock(StripeClient::class);

    $gateway = new StripeConnectGateway(
        secretKey: 'sk_test',
        platformAccountId: 'acct_platform',
        client: $client,
    );

    $plugin = stripeGwPlugin(['slug' => 'stripe-refund-nopayment']);
    /** @var PluginPurchase $purchase */
    $purchase = PluginPurchase::query()->create([
        'plugin_id' => $plugin->id,
        'buyer_user_id' => 1,
        'license_key' => 'ARQ-9999-8888-7777-6666',
        'amount_cents' => 5000,
        'currency' => 'USD',
        'payment_id' => null,
        'status' => 'completed',
        'purchased_at' => now(),
    ]);

    expect($gateway->processRefund($purchase))->toBeFalse();
});

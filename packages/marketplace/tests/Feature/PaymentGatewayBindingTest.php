<?php

declare(strict_types=1);

use Arqel\Marketplace\Contracts\PaymentGateway;
use Arqel\Marketplace\Services\Payments\MockPaymentGateway;
use Arqel\Marketplace\Services\Payments\StripeConnectGateway;
use Illuminate\Support\Facades\Log;

it('binds MockPaymentGateway by default', function (): void {
    config()->set('arqel-marketplace.payment_gateway', 'mock');

    $gateway = $this->app->make(PaymentGateway::class);

    expect($gateway)->toBeInstanceOf(MockPaymentGateway::class);
});

it('binds StripeConnectGateway when payment_gateway=stripe and SDK is available', function (): void {
    if (! class_exists(Stripe\StripeClient::class)) {
        $this->markTestSkipped('stripe/stripe-php not installed in test env.');
    }

    config()->set('arqel-marketplace.payment_gateway', 'stripe');
    config()->set('arqel-marketplace.stripe.secret', 'sk_test_dummy');
    config()->set('arqel-marketplace.stripe.platform_account_id', 'acct_platform_test');

    $this->app->forgetInstance(PaymentGateway::class);

    $gateway = $this->app->make(PaymentGateway::class);

    expect($gateway)->toBeInstanceOf(StripeConnectGateway::class);
});

it('falls back to MockPaymentGateway with warning when stripe driver chosen but SDK missing', function (): void {
    if (class_exists(Stripe\StripeClient::class)) {
        $this->markTestSkipped('stripe/stripe-php IS installed; cannot test missing-SDK fallback path.');
    }

    Log::spy();

    config()->set('arqel-marketplace.payment_gateway', 'stripe');
    $this->app->forgetInstance(PaymentGateway::class);

    $gateway = $this->app->make(PaymentGateway::class);

    expect($gateway)->toBeInstanceOf(MockPaymentGateway::class);
    Log::shouldHaveReceived('warning')->once();
});

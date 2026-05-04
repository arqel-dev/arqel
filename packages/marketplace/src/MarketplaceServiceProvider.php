<?php

declare(strict_types=1);

namespace Arqel\Marketplace;

use Arqel\Marketplace\Contracts\PaymentGateway;
use Arqel\Marketplace\Contracts\VulnerabilityDatabase;
use Arqel\Marketplace\Services\Payments\MockPaymentGateway;
use Arqel\Marketplace\Services\Payments\StripeConnectGateway;
use Arqel\Marketplace\Services\StaticVulnerabilityDatabase;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Stripe\StripeClient;

/**
 * Auto-discovered provider para `arqel-dev/marketplace`.
 *
 * Bind do `PaymentGateway` é configurável via `config('arqel-marketplace.payment_gateway')`:
 *
 * - `'mock'` (default) → `MockPaymentGateway`
 * - `'stripe'` → `StripeConnectGateway` (requer `composer require stripe/stripe-php`)
 *
 * Quando `stripe` é selecionado mas o SDK não está disponível, o provider
 * faz fallback para `MockPaymentGateway` e loga warning — evita quebrar o app.
 */
final class MarketplaceServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('arqel-marketplace')
            ->hasConfigFile('arqel-marketplace')
            ->hasMigration('create_arqel_marketplace_tables')
            ->hasMigration('add_submission_columns_to_arqel_plugins')
            ->hasMigration('add_categories_and_trending')
            ->hasMigration('add_security_scans')
            ->hasMigration('add_paid_plugins')
            ->hasMigration('add_publisher_stripe_to_arqel_plugins')
            ->hasRoute('api');
    }

    public function packageRegistered(): void
    {
        $this->app->bind(VulnerabilityDatabase::class, StaticVulnerabilityDatabase::class);

        $this->app->bind(PaymentGateway::class, function (Application $app): PaymentGateway {
            $driver = (string) $app['config']->get('arqel-marketplace.payment_gateway', 'mock');

            if ($driver === 'stripe') {
                if (! class_exists(StripeClient::class)) {
                    Log::warning(
                        'arqel-marketplace.payment_gateway=stripe but stripe/stripe-php SDK is not installed. '
                        .'Falling back to MockPaymentGateway. Run "composer require stripe/stripe-php" to enable Stripe.',
                    );

                    return new MockPaymentGateway;
                }

                $secret = (string) $app['config']->get('arqel-marketplace.stripe.secret', '');
                $platformAccountId = (string) $app['config']->get('arqel-marketplace.stripe.platform_account_id', '');
                $feePercent = (int) $app['config']->get('arqel-marketplace.stripe.platform_fee_percent', 20);
                $successUrl = (string) $app['config']->get(
                    'arqel-marketplace.stripe.success_url',
                    'https://arqel.dev/marketplace/checkout/success?session_id={CHECKOUT_SESSION_ID}',
                );
                $cancelUrl = (string) $app['config']->get(
                    'arqel-marketplace.stripe.cancel_url',
                    'https://arqel.dev/marketplace/checkout/cancel',
                );

                return new StripeConnectGateway(
                    secretKey: $secret,
                    platformAccountId: $platformAccountId,
                    platformFeePercent: $feePercent,
                    successUrl: $successUrl,
                    cancelUrl: $cancelUrl,
                );
            }

            return new MockPaymentGateway;
        });
    }

    public function packageBooted(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\PluginListCommand::class,
                Console\RecalculateTrendingScoresCommand::class,
                Console\ScanPluginsCommand::class,
            ]);
        }
    }
}

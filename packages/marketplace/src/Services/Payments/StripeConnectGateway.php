<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Services\Payments;

use Arqel\Marketplace\Contracts\CheckoutSession;
use Arqel\Marketplace\Contracts\PaymentGateway;
use Arqel\Marketplace\Contracts\PaymentResult;
use Arqel\Marketplace\Exceptions\MarketplaceException;
use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginPurchase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Gateway real de Stripe Connect para premium plugins (MKTPLC-008 follow-up).
 *
 * O SDK `stripe/stripe-php` é declarado em `suggest` (não require) — apps que
 * querem ativar este gateway precisam rodar `composer require stripe/stripe-php`.
 * Se a classe `\Stripe\StripeClient` não existe, o constructor lança
 * `RuntimeException` com mensagem orientativa.
 *
 * **Application fee + transfer_data**: quando o `Plugin` tem
 * `publisher_stripe_account_id` configurado, o checkout dispara uma
 * `payment_intent_data` com `application_fee_amount` (Arqel cut) e
 * `transfer_data.destination` (publisher account). Caso contrário, todo o
 * pagamento fica na conta da plataforma — útil para plugins próprios da Arqel.
 */
final readonly class StripeConnectGateway implements PaymentGateway
{
    private StripeClient $client;

    public function __construct(
        private string $secretKey,
        private string $platformAccountId,
        private int $platformFeePercent = 20,
        private string $successUrl = 'https://arqel.dev/marketplace/checkout/success?session_id={CHECKOUT_SESSION_ID}',
        private string $cancelUrl = 'https://arqel.dev/marketplace/checkout/cancel',
        ?StripeClient $client = null,
    ) {
        if ($client === null && ! class_exists(StripeClient::class)) {
            throw new RuntimeException(
                'stripe/stripe-php SDK not installed. Run "composer require stripe/stripe-php" '
                .'to enable StripeConnectGateway, or rebind PaymentGateway to MockPaymentGateway.',
            );
        }

        $this->client = $client ?? new StripeClient($secretKey);
    }

    public function createCheckoutSession(Plugin $plugin, int $userId): CheckoutSession
    {
        try {
            $params = [
                'payment_method_types' => ['card'],
                'mode' => 'payment',
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($plugin->currency),
                        'unit_amount' => $plugin->price_cents,
                        'product_data' => [
                            'name' => $plugin->name,
                            'description' => mb_substr($plugin->description, 0, 200),
                        ],
                    ],
                ]],
                'metadata' => [
                    'plugin_slug' => $plugin->slug,
                    'buyer_user_id' => (string) $userId,
                ],
                'success_url' => $this->successUrl,
                'cancel_url' => $this->cancelUrl,
            ];

            if ($plugin->publisher_stripe_account_id !== null && $plugin->publisher_stripe_account_id !== '') {
                $applicationFee = (int) round($plugin->price_cents * $this->platformFeePercent / 100);
                $params['payment_intent_data'] = [
                    'application_fee_amount' => $applicationFee,
                    'transfer_data' => [
                        'destination' => $plugin->publisher_stripe_account_id,
                    ],
                ];
            }

            $session = $this->client->checkout->sessions->create($params);

            return new CheckoutSession(
                url: (string) $session->url,
                sessionId: (string) $session->id,
            );
        } catch (ApiErrorException $e) {
            Log::warning('Stripe checkout session creation failed', [
                'plugin_slug' => $plugin->slug,
                'error' => $e->getMessage(),
            ]);

            throw new MarketplaceException(
                'Failed to create Stripe checkout session: '.$e->getMessage(),
                previous: $e,
            );
        }
    }

    public function verifyPayment(string $paymentId): PaymentResult
    {
        try {
            $session = $this->client->checkout->sessions->retrieve($paymentId);

            $status = $session->payment_status === 'paid' ? 'completed' : 'pending';

            return new PaymentResult(
                status: $status,
                amountCents: (int) ($session->amount_total ?? 0),
                paymentId: (string) $session->id,
            );
        } catch (ApiErrorException $e) {
            Log::warning('Stripe payment verification failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            throw new MarketplaceException(
                'Failed to verify Stripe payment: '.$e->getMessage(),
                previous: $e,
            );
        }
    }

    public function processRefund(PluginPurchase $purchase): bool
    {
        if ($purchase->payment_id === null || $purchase->payment_id === '') {
            return false;
        }

        try {
            $this->client->refunds->create([
                'payment_intent' => $purchase->payment_id,
            ]);

            return true;
        } catch (ApiErrorException $e) {
            Log::warning('Stripe refund failed', [
                'purchase_id' => $purchase->id,
                'payment_id' => $purchase->payment_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

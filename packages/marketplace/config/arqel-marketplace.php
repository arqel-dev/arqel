<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitação global
    |--------------------------------------------------------------------------
    |
    | Quando `false`, o `MarketplaceServiceProvider` ainda é booteado, mas
    | as rotas REST não são registradas. Útil para apps que querem só
    | consumir os models (e.g., scripts CLI ou jobs internos).
    */
    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Prefixo de rotas REST
    |--------------------------------------------------------------------------
    |
    | Prefixo aplicado a todos os endpoints do marketplace. Exemplo:
    | `api/marketplace/plugins` → lista de plugins published.
    */
    'route_prefix' => 'api/marketplace',

    /*
    |--------------------------------------------------------------------------
    | Paginação default
    |--------------------------------------------------------------------------
    |
    | Number of items per page when `?per_page` não é passado pelo client.
    | O list controller clampa o valor recebido em [1, 100].
    */
    'pagination' => 20,

    /*
    |--------------------------------------------------------------------------
    | Submission review obrigatório
    |--------------------------------------------------------------------------
    |
    | Quando `true` (default), plugins entram com status `pending` e
    | precisam de aprovação manual antes de virar `published`. MKTPLC-002
    | implementa o submission workflow que consome esta flag.
    */
    'submission_review_required' => true,

    /*
    |--------------------------------------------------------------------------
    | Payment gateway
    |--------------------------------------------------------------------------
    |
    | `mock` (default) usa `MockPaymentGateway` — checkout stub, ideal para dev/test.
    | `stripe` ativa `StripeConnectGateway` real — exige `composer require stripe/stripe-php`
    | + env vars `STRIPE_SECRET` e `STRIPE_PLATFORM_ACCOUNT_ID`.
    |
    | Se `stripe` for selecionado mas o SDK não estiver instalado, o provider
    | faz fallback para `MockPaymentGateway` e loga um warning.
    */
    'payment_gateway' => env('ARQEL_MARKETPLACE_PAYMENT_GATEWAY', 'mock'),

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'platform_account_id' => env('STRIPE_PLATFORM_ACCOUNT_ID'),
        'platform_fee_percent' => (int) env('STRIPE_PLATFORM_FEE_PERCENT', 20),
        'success_url' => env(
            'STRIPE_SUCCESS_URL',
            'https://arqel.dev/marketplace/checkout/success?session_id={CHECKOUT_SESSION_ID}',
        ),
        'cancel_url' => env('STRIPE_CANCEL_URL', 'https://arqel.dev/marketplace/checkout/cancel'),
    ],
];

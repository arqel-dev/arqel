# Payments & licensing

> How to price plugins, generate license keys, process payouts, and handle refunds in the Arqel Marketplace.

This page covers the full financial cycle of a paid plugin — from `price_cents` on the submission row to the monthly payout to the publisher. It documents what is delivered (MKTPLC-008) and what remains a placeholder until the real Stripe Connect follow-up.

## Current state

| Component | Status |
|---|---|
| Purchase + license + payout schema | ✅ Delivered (MKTPLC-008) |
| `MockPaymentGateway` (default for dev/test) | ✅ Delivered |
| `LicenseKeyGenerator` format `ARQ-XXXX-XXXX-XXXX-XXXX` | ✅ Delivered |
| `purchase` / `confirm` / `download` / `refund` endpoints | ✅ Delivered |
| Real `StripeConnectGateway` | ✅ Delivered — opt-in via `composer require stripe/stripe-php` + config `payment_gateway=stripe` |
| Stripe webhooks (idempotency, refund.updated) | ⏳ Next follow-up |
| Payouts cron job (monthly, $50 threshold) | ⏳ Schema ready, scheduler is TODO follow-up |
| Tax invoicing | ⏳ Out of Arqel's scope — publisher is responsible |

By default the package binds `PaymentGateway => MockPaymentGateway`. Host apps (including `arqel.dev/marketplace` in production) rebind to a real gateway when available:

```php
// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->bind(
        \Arqel\Marketplace\Contracts\PaymentGateway::class,
        \App\Marketplace\StripeConnectGateway::class,
    );
}
```

## Configuring Stripe Connect

The real `StripeConnectGateway` is opt-in — apps that want to charge for real plugins activate the gateway via an extra dependency + config. The `stripe/stripe-php` SDK adds ~200KB to vendor, so the `arqel-dev/marketplace` package declares it under `suggest` (not `require`). Apps that only consume free plugins do not pay the cost.

### Prerequisites

1. **Active Stripe account** with Connect enabled. Visit [dashboard.stripe.com/connect](https://dashboard.stripe.com/connect/overview) and follow the platform onboarding. Coverage: USD, BRL, EUR, GBP and 130+ more currencies.
2. **Connect plan** — Standard, Express, or Custom. For the standard Arqel marketplace, we recommend **Express** (lowest onboarding friction for publishers; Stripe hosts KYC).
3. **Public webhook endpoint** — Stripe delivers events over HTTPS. In dev, use the [Stripe CLI](https://stripe.com/docs/stripe-cli) (`stripe listen --forward-to localhost:8000/stripe/webhook`).

### Installation

```bash
composer require stripe/stripe-php
```

Verify the install:

```bash
composer show stripe/stripe-php
# stripe/stripe-php  v16.x.x  Stripe PHP Library
```

### Config + env vars

In the app's `.env`:

```env
# Driver de gateway
ARQEL_MARKETPLACE_PAYMENT_GATEWAY=stripe

# Credenciais Stripe (use sk_test_... em dev)
STRIPE_SECRET=sk_live_51xxxxx
STRIPE_PLATFORM_ACCOUNT_ID=acct_platformxxxxxx
STRIPE_PLATFORM_FEE_PERCENT=20

# URLs de retorno (Stripe Checkout redireciona pra cá após pagamento)
STRIPE_SUCCESS_URL=https://arqel.dev/marketplace/checkout/success?session_id={CHECKOUT_SESSION_ID}
STRIPE_CANCEL_URL=https://arqel.dev/marketplace/checkout/cancel
```

`MarketplaceServiceProvider` reads these values in `register()` and instantiates `StripeConnectGateway` automatically. If `ARQEL_MARKETPLACE_PAYMENT_GATEWAY=stripe` but the SDK is not installed, the provider falls back to `MockPaymentGateway` and logs a warning — that way the app does not break on CI/dev without the SDK.

### Publisher onboarding (Connect)

Each publisher who wants to sell plugins needs a **Connect account** linked to your platform account. The recommended flow is Express (Stripe-hosted):

1. Publisher clicks "Set up payouts" in the Arqel admin.
2. The app creates a Connect Express account through the Stripe API:

   ```php
   use Stripe\StripeClient;

   $stripe = new StripeClient(config('arqel-marketplace.stripe.secret'));
   $account = $stripe->accounts->create([
       'type' => 'express',
       'country' => 'BR',
       'email' => $publisher->email,
       'capabilities' => [
           'card_payments' => ['requested' => true],
           'transfers' => ['requested' => true],
       ],
   ]);
   ```

3. The app creates an account link and redirects the publisher to Stripe-hosted onboarding:

   ```php
   $link = $stripe->accountLinks->create([
       'account' => $account->id,
       'refresh_url' => 'https://arqel.dev/publisher/stripe/refresh',
       'return_url' => 'https://arqel.dev/publisher/stripe/return',
       'type' => 'account_onboarding',
   ]);

   return redirect($link->url);
   ```

4. After onboarding, persist `account->id` in `arqel_plugins.publisher_stripe_account_id` (column added by the migration `2026_05_07_000000_add_publisher_stripe_to_arqel_plugins.php`). From then on, every plugin checkout dispatches `application_fee_amount` (Arqel cut, default 20%) + `transfer_data.destination` (publisher account).

> Plugins **without** a populated `publisher_stripe_account_id` keep working — the entire payment stays on the platform. Useful for Arqel's own plugins or while the publisher has not finished onboarding.

### Testing with test cards

In dev/staging use `STRIPE_SECRET=sk_test_...`. Stripe offers predictable test cards to simulate scenarios:

| Number | Result |
|---|---|
| `4242 4242 4242 4242` | Success |
| `4000 0000 0000 0002` | Generic decline |
| `4000 0025 0000 3155` | Authentication required (3D Secure) |
| `4000 0000 0000 9995` | Insufficient funds |

Use any 3-digit CVC, any 5-digit ZIP, any future date. Full list at [stripe.com/docs/testing](https://stripe.com/docs/testing#cards).

End-to-end flow in dev:

```bash
# Terminal 1: forward webhooks
stripe listen --forward-to localhost:8000/stripe/webhook

# Terminal 2: app rodando
php artisan serve

# No app:
# 1. Inicie purchase (POST /api/marketplace/plugins/{slug}/purchase)
# 2. Use a checkout.url retornada — Stripe vai mostrar form de teste
# 3. Cole 4242 4242 4242 4242 + qualquer CVC/ZIP/data futura
# 4. Stripe redireciona para STRIPE_SUCCESS_URL com session_id
# 5. App chama POST /api/marketplace/plugins/{slug}/purchase/confirm
```

### Common troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `RuntimeException: stripe/stripe-php SDK not installed` | SDK not installed but gateway activated | `composer require stripe/stripe-php` |
| Provider bind falls back to Mock + log warning | `payment_gateway=stripe` but `class_exists(StripeClient)` false | Same as above — check `composer show stripe/stripe-php` |
| `MarketplaceException: Failed to create Stripe checkout session` | Upstream Stripe error (auth, invalid currency, etc.) | Check `storage/logs/laravel.log` — original exception is attached as `previous` |
| Checkout does not redirect to publisher account | `publisher_stripe_account_id` null on the plugin | Complete Connect onboarding; persist the column |
| Application fee looks off | `STRIPE_PLATFORM_FEE_PERCENT` does not match expectation | Cast is `(int)`; the value is a percentage of `price_cents`. For 15%, set `STRIPE_PLATFORM_FEE_PERCENT=15` |

## Plugin pricing

Each row in `arqel_plugins` carries four columns relevant for pricing:

| Column | Type | Default | Meaning |
|---|---|---|---|
| `price_cents` | int | `0` | Price in cents of `currency`. `0` = free. |
| `currency` | string(3) | `USD` | ISO 4217. Use `EUR`, `BRL`, `GBP`, etc. for localized prices. |
| `publisher_user_id` | int nullable | populated at submission | FK to `users` — payout recipient. |
| `revenue_share_percent` | int | `80` | % of `price_cents` that goes to the publisher; the rest (`100 - revenue_share_percent`) stays with Arqel. |

The `Plugin::isPremium()` accessor returns `price_cents > 0`. That boolean triggers the checkout pipeline instead of a direct download.

### How to price

Recommendations for setting `price_cents`:

| Range | Plugin type | Example |
|---|---|---|
| Free (`0`) | Generic helpers, basic fields, pure OSS | `arqel-dev/fields-markdown` |
| `$5-$15` | Field-pack with third-party SDK integration | Stripe Card, Mapbox Address |
| `$20-$50` | Widget-pack or critical integration (Slack, Sentry) | Sentry dashboards widget |
| `$100+` | White-label themes or enterprise plugins (custom auth, multi-tenant SSO) | Corporate themes |

Subscription pricing (monthly/yearly) is **not implemented** in MKTPLC-008 — only one-time purchase. The subscription model lands in a future follow-up.

### Price updates

Price can be updated via the publisher admin endpoint (future `PATCH /publisher/plugins/{slug}` with Gate `marketplace.update`). Price increases **do not retroact** — existing purchases keep permanent access. Price reductions also do not generate automatic refunds.

## License keys

Every completed purchase generates a unique license key in the format:

```
ARQ-XXXX-XXXX-XXXX-XXXX
```

Where each `XXXX` is a 4-char hex group. Total: 16 hex chars = 64 bits of entropy (generated via `random_bytes(8)` in `LicenseKeyGenerator::generate()`).

### Verification

```php
use Arqel\Marketplace\Services\LicenseKeyGenerator;

$generator = app(LicenseKeyGenerator::class);

if ($generator->verify($licenseKey, $plugin)) {
    // license válida + status completed
}
```

`verify()` runs three checks:

1. Format regex `/^ARQ-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}$/`.
2. Match against `arqel_plugin_purchases.license_key` via `hash_equals` (timing-safe).
3. `status === 'completed'` (refunded or pending blocks).

### Distribution

The license key is returned **only once** in the `confirm` payload:

```http
POST /api/marketplace/plugins/{slug}/purchase/confirm
{ "paymentId": "mock_abc123" }

# response
{
  "purchase": {
    "id": 42,
    "status": "completed",
    "license_key": "ARQ-A1B2-C3D4-E5F6-7890",
    "purchased_at": "2026-05-01T14:23:00Z"
  }
}
```

The marketplace client (`arqel:install` CLI) saves the key to `~/.arqel/credentials.json` (TODO of the `arqel-dev/cli` delivery). To fetch the key again, hit `GET /publisher/purchases/{id}` (authenticated).

## Full purchase flow

### 1. Initiate

Authenticated user starts a purchase:

```http
POST /api/marketplace/plugins/acme-stripe-card/purchase
Authorization: Bearer <user_token>
```

Possible outcomes:

- **422** if the plugin is free (`price_cents === 0`).
- **401** if not authenticated.
- **404** if the plugin is not published.
- **200 with `already_owned: true`** if the user already has a completed purchase.
- **200 with `purchase + checkout`** otherwise:

```json
{
  "purchase": { "id": 42, "status": "pending" },
  "checkout": {
    "url": "https://arqel.dev/marketplace/mock-checkout/acme-stripe-card",
    "session_id": "mock_abc123"
  }
}
```

The `checkout.url` redirects the user to the gateway. In `MockPaymentGateway`, the URL is a stub that simply echoes back the `session_id`. In production with Stripe Connect, it will be a real Stripe Checkout URL.

### 2. Confirm (gateway callback)

After successful payment, the gateway calls (or the frontend calls with the returned session id):

```http
POST /api/marketplace/plugins/acme-stripe-card/purchase/confirm
{ "paymentId": "mock_abc123" }
```

`PluginPurchaseController::confirm`:

1. Verifies via `PaymentGateway::verifyPayment($paymentId)` — returns `PaymentResult` (`success`, `amount`, `currency`).
2. On success, marks the purchase as `completed`, persists `payment_id`, generates a license key via `LicenseKeyGenerator`.
3. Idempotent on re-confirm — if the purchase is already `completed`, returns the same license key.

### 3. Download

With a completed purchase, the user can download:

```http
GET /api/marketplace/plugins/acme-stripe-card/download
Authorization: Bearer <user_token>
```

Free plugins release the download without checks; premium ones require a completed purchase. Without a valid purchase → **403 Forbidden**.

Response:

```json
{
  "download_url": "https://arqel.dev/marketplace/download/acme-stripe-card/latest.zip",
  "license_key": "ARQ-A1B2-C3D4-E5F6-7890"
}
```

## Refunds

Arqel policy: **refund within 14 days** of the purchase, no questions asked (consumer protection aligned with EU + the Brazilian Código de Defesa do Consumidor).

After 14 days, refunds are evaluated case by case by the admin via Gate `marketplace.refund`:

```http
POST /api/marketplace/admin/plugins/{slug}/refund/{purchaseId}
Authorization: Bearer <admin_token>
```

`AdminRefundController`:

1. Validates that the purchase is `completed` (422 if already refunded or pending).
2. Calls `PaymentGateway::processRefund($purchase->payment_id, $purchase->amount_cents)`.
3. Marks the purchase as `refunded` + populates `refunded_at`.
4. The license key is invalidated — `LicenseKeyGenerator::verify()` now returns false.

Refund chargebacks on `MockPaymentGateway` only succeed for `completed` purchases — `pending` or `failed` states return `false`.

## Payouts

Schema delivered: the `arqel_plugin_payouts` table with `plugin_id`, `publisher_user_id`, `amount_cents`, `currency`, `status` (`pending|paid|failed`), `period_start`, `period_end`.

### How it works (future)

The monthly cron job (TODO `arqel:marketplace:payouts` follow-up) runs:

1. For each active `publisher_user_id`, computes the sum of `purchases.amount_cents * (revenue_share_percent / 100)` for the period (current month minus a 14-day refund hold window).
2. If the sum ≥ threshold (`$50` USD or equivalent in other currencies), creates a `arqel_plugin_payouts` row with `status=pending`.
3. Dispatches a job that calls Stripe Connect transfer (or equivalent).
4. If the transfer succeeds → `status=paid`. If it fails → `status=failed`, retried next month.

### Minimum threshold

Payments below **$50 USD** (or equivalent) accumulate to the next period. This prevents transfer fees from eating the payout.

### Listing for publishers

```http
GET /api/marketplace/publisher/payouts?per_page=20
Authorization: Bearer <publisher_token>
```

Returns paginated, filtered by `publisher_user_id = auth()->id()`. Each row includes `period_start`, `period_end`, `amount_cents`, `currency`, `status`.

## Revenue share

Default: **80% publisher / 20% Arqel**.

Arqel takes 20% to cover:

- Marketplace hosting + CDN for downloads.
- Security scanning (`SecurityScanner` + future integration with the GitHub Advisory Database).
- Manual review by human curators.
- Payment gateway fees (Stripe Connect charges ~2.9% + $0.30 per transaction; ~17% remains for Arqel).
- Customer support (refund disputes, fraud detection).

### Customization

Per plugin, `revenue_share_percent` can be adjusted individually via admin (Gate `marketplace.update-revenue-share`). Typical cases:

- **Founding publishers** (first 50 to publish premium): 90/10.
- **Enterprise plugins** with price_cents > $500: 85/15 (volume justifies the discount).
- **Charity plugins** (publisher donates 100% to an NGO): 100/0 with manual approval + due diligence.

## Tax & legal disclaimer

**Publishers are responsible** for:

- Reporting revenue to tax authorities (BR: Receita Federal; EU: VAT MOSS; US: 1099-K if you cross the threshold).
- Issuing invoices/notas fiscais to customers when required by jurisdiction.
- Compliance with the gateway's terms of service (Stripe Connect ToS).

Arqel **does not** issue invoices on the publisher's behalf and **does not** withhold taxes automatically (except in jurisdictions where marketplace aggregator tax rules apply — e.g., EU VAT for B2C, US states with marketplace facilitator laws).

The Stripe Connect Express setup during onboarding forces the publisher to provide:

- Legal name or CPF/CNPJ (BR), SSN/EIN (US), VAT ID (EU).
- Tax address.
- Bank account for payout.

Without this data, the publisher's plugins are restricted to `price_cents = 0` (free).

## Quick comparison

| Scenario | Free plugin | Premium plugin |
|---|---|---|
| Submission | Same | Requires Stripe Connect onboarding |
| `price_cents` | `0` | `>= 100` (min. $1) |
| License key | Not issued | Issued at confirm |
| Download | Direct | Requires valid license |
| Reviews | Allowed | Allowed + `verified_purchaser` flag populated |
| Auto-delist on critical scan | Same | Same |
| Refund | N/A | Within 14 days, automatic |
| Payout | N/A | Monthly, $50 threshold |

## Premium publisher checklist

- [ ] Stripe Connect onboarding complete (TODO follow-up).
- [ ] `price_cents` set (min. $1 = `100`).
- [ ] `currency` correct for your market.
- [ ] `LICENSE.md` in the repo if using `Proprietary`.
- [ ] Refund policy documented in the plugin's README.
- [ ] Tax setup done with your accountant.
- [ ] CHANGELOG.md keeps a consistent semver version for future license validation by version range.

## Next steps

- Submitting a premium plugin for the first time? Review [Publishing plugins](./publishing.md) — the pipeline is identical to free, except for the Stripe Connect onboarding.
- Plugin rejected for security? See [Security best practices](./security-best-practices.md).
- Want to understand the payment backend? See section MKTPLC-008 in `packages/marketplace/SKILL.md`.

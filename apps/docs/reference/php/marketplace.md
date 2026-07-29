# `arqel-dev/marketplace` — API Reference

Namespace `Arqel\Marketplace\`. Backend for the Arqel plugin marketplace: relational schema, Eloquent models and a REST API for discovering and publishing community plugins (`field`, `widget`, `integration`, `theme`), plus reviews, categorization, trending, security scanning and paid plugins.

It ships as an **embeddable package** rather than a monolithic Laravel app, so the public marketplace at `arqel.dev/marketplace` and a self-hosted private marketplace both consume the same code.

## Models

All models are `final`.

### `Models\Plugin`

Table `arqel_plugins`. Casts include `screenshots => array`, `submission_metadata => array`, `submitted_at`/`reviewed_at`/`featured_at` as datetime, `featured => bool`, `price_cents` and `revenue_share_percent` as int.

| Member | Type | Description |
|---|---|---|
| `isPremium()` | `bool` | `price_cents > 0` |
| `publisher()` | `BelongsTo` | Via `publisher_id` |
| `versions()` / `installations()` / `reviews()` / `purchases()` / `payouts()` | `HasMany` | |
| `categories()` | `BelongsToMany` | Through `arqel_plugin_category_assignments` |
| `scopePublished()` | `Builder` | `status = published` — the only public status |
| `scopeOfType(string $type)` | `Builder` | |
| `scopeSearch(string $term)` | `Builder` | Name + description |
| `scopeFeatured()` / `scopeTrending()` / `scopeNewThisWeek()` | `Builder` | |
| `scopeMostPopular()` | `Builder` | Via `withCount('installations')` |

Status is a hard-coded enum on the migration: `draft` / `pending` / `published` / `archived`.

### `Models\PluginVersion`

`plugin()`, `installations()`; cast `released_at => datetime`. Unique on `(plugin_id, version)`.

### `Models\PluginInstallation`

`$timestamps = false` (append-only). `plugin()`, `version()`; casts `installed_at => datetime`, `context => array`.

### `Models\PluginReview`

`votes()` (`HasMany`), plus scopes `scopePublished`, `scopePending`, `scopeHidden`, `scopeMostHelpful` (helpful_count desc, then score desc), `scopeMostRecent`, `scopeHighestRated`, `scopePositive` (≥4 stars). Columns include `verified_purchaser`, `helpful_count`, `unhelpful_count`, `status` (`pending`/`published`/`hidden`, default `pending`) and `moderation_reason`.

### `Models\PluginReviewVote`

Fillable `review_id`, `user_id`, `vote`. Relations `review()` and a defensive `user()` resolved through `auth.providers.users.model`. Unique on `(review_id, user_id)`.

### `Models\PluginCategory`

`plugins()`, `parent()`, `children()`; scopes `scopeRoot`, `scopeOrdered`. Five categories are seeded by default: `fields`, `widgets`, `themes`, `integrations`, `utilities`.

### `Models\PluginPurchase`

`plugin()` plus a defensive `buyer()`; scopes `scopeCompleted`, `scopePending`, `scopeRefunded`. Carries `license_key` (unique), `amount_cents`, `currency`, `payment_id`, `status` (`pending`/`completed`/`refunded`/`failed`), `purchased_at`, `refunded_at`.

### `Models\PluginPayout`

`plugin()`, `publisher()`. Carries `amount_cents`, `currency`, `status` (`pending`/`paid`/`failed`), `period_start`, `period_end`.

### `Models\SecurityScan`

`plugin()`; casts `scan_started_at`/`scan_completed_at` to datetime and `findings` to array. `status` is `pending|running|passed|flagged|failed`.

### `Models\Publisher`

| Member | Type | Description |
|---|---|---|
| `plugins()` | `HasMany` | Via `publisher_id` |
| `scopeVerified()` | `Builder` | |
| `scopeWithPlugins()` | `Builder` | Publishers with at least one published plugin |
| `aggregateStats()` | `array` | `{plugins_count, total_downloads, avg_rating}` over published plugins, installations and published reviews |

## Services

### `Services\PluginAutoChecker` (final readonly)

`check(Plugin $plugin): array` runs five network-free checks and returns `{checks: list<{name, status, message}>, passed: bool}`: `composer_package_format` (fail on an invalid `vendor/package` regex), `github_url_format` (fail when the host is not github.com), `description_length` (warn under 50 chars), `screenshots_count` (warn at zero), `name_uniqueness` (warn on a duplicate).

### `Services\PluginConventionValidator` (final readonly)

Validates already-decoded arrays — it performs no I/O.

| Method | Type |
|---|---|
| `validateComposerJson(array $composerData)` | `ConventionValidationResult` |
| `validateNpmPackageJson(array $packageData)` | `ConventionValidationResult` |

On `composer.json` it checks `type=arqel-plugin` (fail), `extra.arqel.plugin-type` within the enum `field-pack`, `widget-pack`, `theme`, `integration`, `language-pack`, `tool` (fail), `extra.arqel.compat.arqel` as a valid semver constraint (fail), a non-empty `extra.arqel.category` (fail), `extra.arqel.installation-instructions` (warn when absent) and `keywords` containing `arqel` + `plugin` (warn). On `package.json` it accepts `arqel.plugin-type` at the root **or** a `peerDependencies."@arqel-dev/types"` entry.

### `Services\ConventionValidationResult` (final readonly)

Value object with `checks`, `passed`, `warnings`, `errors`. Factories `ConventionValidationResult::success(array $checks): self` and `::failed(array $checks): self`; `toArray(): array` for serialization.

### `Services\TrendingScoreCalculator` (final readonly)

`calculate(Plugin $plugin): float` returns `installations_last_7d * 1.0 + recent_positive_reviews * 5.0` (positive = ≥4 stars in the last 30 days), rounded to two decimals. `recalculateAll(): int` iterates `Plugin::published()` and persists `trending_score` plus `trending_score_updated_at`, returning the number of plugins updated.

The 5× weight on reviews reflects that social signal outweighs raw install counts; the 7-day install window lets fresh entries surface, while the 30-day review window avoids an instant drop after a spike.

### `Services\SecurityScanner` (final readonly)

Constructor `(VulnerabilityDatabase $vulnDb)`. `scan(Plugin $plugin): SecurityScan`:

1. Creates a `SecurityScan` in `running`.
2. Looks up vulnerabilities for the composer and npm packages via the injected database.
3. Checks the license against the allowlist (`MIT`, `Apache-2.0`, `BSD-2-Clause`, `BSD-3-Clause`) — anything else is a `low` warning.
4. Rolls severity up to the maximum found. `critical` → `failed` plus auto-delist (`status = archived`) plus a `PluginAutoDelistedEvent`; `high`/`medium` → `flagged`; `low` or none → `passed`.

Only currently `published` plugins are auto-delisted, so already-archived or draft plugins never spam the event.

### `Services\StaticVulnerabilityDatabase` (final readonly)

Default `VulnerabilityDatabase` binding. `lookup(string $package, string $ecosystem): array` returns an empty list — host apps rebind the contract to a real provider.

### `Services\VersionMatcher` (final)

`VersionMatcher::isAffected(?string $installed, string $affectedConstraint): bool` — whether an installed version satisfies an advisory's affected-version constraint.

### `Services\LicenseKeyGenerator` (final readonly)

`generate(): string` returns `ARQ-XXXX-XXXX-XXXX-XXXX` (four hex groups of four chars from `random_bytes(8)`). `verify(string $key, PluginPurchase $purchase): bool` validates the format, the match and a `completed` status, comparing with `hash_equals` for timing safety.

## Contracts

### `Contracts\VulnerabilityDatabase`

```php
public function lookup(string $package, string $ecosystem): array; // array<int, Advisory>
```

### `Contracts\Advisory` (final readonly)

```php
public function __construct(
    public string $id,
    public string $severity,
    public string $summary,
    public string $affectedVersions,  // composer constraint, e.g. '<2.0'
) {}
```

### `Contracts\PaymentGateway`

```php
public function createCheckoutSession(Plugin $plugin, int $userId): CheckoutSession;
public function verifyPayment(string $paymentId): PaymentResult;
public function processRefund(PluginPurchase $purchase): bool;
```

### `Contracts\CheckoutSession` / `Contracts\PaymentResult` (final readonly DTOs)

```php
new CheckoutSession(url: '...', sessionId: '...');
new PaymentResult(status: 'completed', amountCents: 2900, paymentId: '...');
```

## Payment gateways

| Class | Notes |
|---|---|
| `Services\Payments\MockPaymentGateway` (final readonly) | The default binding. Stub URL `/marketplace/mock-checkout/{slug}`, `sessionId` prefixed `mock_`. Refunds only succeed for `completed` purchases |
| `Services\Payments\StripeConnectGateway` (final readonly) | Real Stripe Connect via `stripe/stripe-php` (declared in `suggest`, not `require`). Instantiates `\Stripe\StripeClient` when the SDK is present and throws an actionable `RuntimeException` otherwise. `createCheckoutSession` adds `application_fee_amount` + `transfer_data.destination` when the plugin has a `publisher_stripe_account_id`. Stripe API errors are wrapped in `Exceptions\MarketplaceException` (checkout/verify) or return `false` with a warning log (refund) |

When `payment_gateway=stripe` but the SDK is missing, the provider falls back to `MockPaymentGateway` and logs a warning rather than breaking boot. Default revenue share is 80% publisher / 20% platform, configurable per plugin via `revenue_share_percent`.

## HTTP

All routes live under a configurable prefix — `config('arqel-marketplace.route_prefix')`, default `api/marketplace`. Setting `arqel-marketplace.enabled` to `false` registers no routes at all. Public endpoints use the `api` middleware; the authenticated group adds `auth:sanctum` when that guard exists, falling back to `auth`.

### Public (`api`)

| Verb | Route | Name |
|---|---|---|
| GET | `plugins` | `arqel.marketplace.plugins.index` |
| GET | `plugins/{slug}` | `arqel.marketplace.plugins.show` |
| GET | `plugins/{slug}/reviews` | `arqel.marketplace.plugins.reviews.index` |
| GET | `categories` | `arqel.marketplace.categories.index` |
| GET | `categories/{slug}/plugins` | `arqel.marketplace.categories.plugins` |
| GET | `featured` | `arqel.marketplace.featured` |
| GET | `trending` | `arqel.marketplace.trending` |
| GET | `new` | `arqel.marketplace.new` |
| GET | `popular` | `arqel.marketplace.popular` |

`PluginListController` accepts `type`, `search`, `page` and `per_page` (clamped to `[1, 100]`) and restricts to `status=published`. `PluginDetailController` returns `{plugin, reviews, versions}` with only `published` reviews ordered by `mostHelpful`, and `404`s on a draft, pending or archived plugin. `PluginReviewListController` takes `?sort=helpful|recent|rating` (default `helpful`). `NewPluginsController` takes `?days=` (default 7, clamped `[1, 90]`); `TrendingPluginsController` and `MostPopularPluginsController` return the top 20. `CategoryListController` accepts `?root=1`.

### Authenticated

| Verb | Route | Name |
|---|---|---|
| POST | `plugins/{slug}/reviews` | `arqel.marketplace.plugins.reviews.store` |
| POST | `plugins/{slug}/reviews/{reviewId}/vote` | `…reviews.vote.store` |
| DELETE | `plugins/{slug}/reviews/{reviewId}/vote` | `…reviews.vote.destroy` |
| POST | `plugins/submit` | `arqel.marketplace.submit` |
| POST | `plugins/{slug}/purchase` | `…plugins.purchase.initiate` |
| POST | `plugins/{slug}/purchase/confirm` | `…plugins.purchase.confirm` |
| GET | `plugins/{slug}/download` | `arqel.marketplace.plugins.download` |
| GET | `publisher/payouts` | `arqel.marketplace.publisher.payouts` |

`PluginReviewController` validates `stars` (1–5) and `comment` (≤5000), creates the review with `status=pending`, and is idempotent through `firstOrCreate(user_id + plugin_id)`. `PluginSubmissionController` creates the plugin with `status=pending`, records `submitted_by_user_id` / `submitted_at`, runs `PluginAutoChecker` into `submission_metadata` and dispatches `PluginSubmitted` — the `201` response is `{plugin, checks}`. `PluginPurchaseController::initiate` reuses a pending purchase and returns `already_owned: true` when one is already completed (`422` for a free plugin); `::confirm` verifies through the gateway, marks the purchase `completed` and generates the license key, and is idempotent on re-confirm. `PluginDownloadController` releases free plugins and requires a completed purchase for premium ones (`403` otherwise). `PublisherPayoutsController` is filtered to `publisher_user_id = auth()->id()` with `per_page` clamped `[1, 100]`.

### Admin (Gate-protected)

| Verb | Route | Gate |
|---|---|---|
| GET | `admin/plugins` | `marketplace.review` |
| POST | `admin/plugins/{slug}/review` | `marketplace.review` |
| GET | `admin/reviews` | `marketplace.moderate-reviews` |
| POST | `admin/reviews/{reviewId}/moderate` | `marketplace.moderate-reviews` |
| POST | `admin/plugins/{slug}/feature` | `marketplace.feature` |
| GET | `admin/security-scans` | `marketplace.security-scans` |
| POST | `admin/plugins/{slug}/refund/{purchaseId}` | `marketplace.refund` |

`PluginAdminReviewController` takes `action=approve` (→ `published`, dispatches `PluginApproved`) or `action=reject` (→ `archived` with `rejection_reason`, dispatches `PluginRejected`). `PluginReviewModerationController::moderate` applies `publish` or `hide` (the latter requiring a reason). `PluginFeatureController` takes `{featured: bool}`. `AdminRefundController` returns `422` when the purchase is already refunded or was never completed. Every Gate denial is a `403`.

## Form requests

`Http\Requests\SubmitPluginRequest` validates `composer_package` against a `vendor/package` regex, `github_url` as a URL, `type` within the enum, `name` 3–100 chars, `description` 20–2000 chars, and `screenshots[]` as URLs. When `slug` is absent it is derived from the name via `Str::slug` and checked for uniqueness against `arqel_plugins`.

## Events

All `final`, `Dispatchable` + `SerializesModels`: `Events\PluginSubmitted`, `Events\PluginApproved`, `Events\PluginRejected`, `Events\PluginPurchased` (carries `Plugin` + `PluginPurchase`), and `Events\PluginAutoDelistedEvent` (carries `Plugin` + `SecurityScan`).

## Exceptions

`Exceptions\MarketplaceException` is the package's base exception, used to wrap gateway API errors.

## Artisan commands

| Command | Function |
|---|---|
| `arqel:plugin:list {--validate}` | Discovers installed plugins via `Composer\InstalledVersions::getInstalledPackagesByType('arqel-plugin')`, reads each install path's `composer.json` and prints `Name \| Version \| Plugin Type \| Category \| Status`. With `--validate` it also runs `PluginConventionValidator` and prints the detailed checks |
| `arqel:marketplace:trending` | Recalculates cached trending scores; logs `Updated N plugins.` |
| `arqel:marketplace:scan {--plugin=} {--dry-run}` | Scans all `published` plugins (or one). Outputs `Scanned N plugins. Findings: X critical, Y high, Z medium, W low.` |

Both `arqel:marketplace:trending` and `arqel:marketplace:scan` are meant to be scheduled daily by the host app.

## Configuration

`config/arqel-marketplace.php` exposes `enabled` (default `true`), `route_prefix` (default `api/marketplace`), `pagination` (default `20`) and `submission_review_required` (default `true`).

## Example

```php
// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->bind(
        \Arqel\Marketplace\Contracts\VulnerabilityDatabase::class,
        \App\Security\GitHubAdvisoryDatabase::class,
    );

    $this->app->bind(
        \Arqel\Marketplace\Contracts\PaymentGateway::class,
        \App\Marketplace\CustomGateway::class,
    );
}
```

```php
// routes/console.php
Schedule::command('arqel:marketplace:trending')->daily();
Schedule::command('arqel:marketplace:scan')->daily();
```

```php
use Arqel\Marketplace\Services\PluginConventionValidator;

$validator = new PluginConventionValidator;
$result = $validator->validateComposerJson(
    json_decode(file_get_contents('composer.json'), true),
);

if (! $result->passed) {
    foreach ($result->errors as $error) {
        echo "ERROR: {$error}\n";
    }
}
```

```php
// Purchase flow
$response = Http::withToken($token)
    ->post("https://arqel.dev/api/marketplace/plugins/{$slug}/purchase");
// → { purchase: {...}, checkout: { url, session_id } }

$response = Http::withToken($token)
    ->post("https://arqel.dev/api/marketplace/plugins/{$slug}/purchase/confirm", [
        'paymentId' => $sessionId,
    ]);
// → { purchase: { status: 'completed', license_key: 'ARQ-...' } }
```

## Related

- SKILL: [`packages/marketplace/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/marketplace/SKILL.md)
- Source: [`packages/marketplace/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/marketplace/src/)

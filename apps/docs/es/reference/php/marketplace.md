# `arqel-dev/marketplace` — Referencia de API

Namespace `Arqel\Marketplace\`. Backend del marketplace de plugins de Arqel: esquema relacional, modelos de Eloquent y una API REST para descubrir y publicar plugins de la comunidad (`field`, `widget`, `integration`, `theme`), además de reseñas, categorización, tendencias, escaneo de seguridad y plugins de pago.

Se distribuye como un **paquete embebible** en lugar de una app Laravel monolítica, de modo que el marketplace público en `arqel.dev/marketplace` y un marketplace privado autoalojado consumen el mismo código.

## Modelos

Todos los modelos son `final`.

### `Models\Plugin`

Tabla `arqel_plugins`. Los casts incluyen `screenshots => array`, `submission_metadata => array`, `submitted_at`/`reviewed_at`/`featured_at` como datetime, `featured => bool`, y `price_cents` y `revenue_share_percent` como int.

| Miembro | Tipo | Descripción |
|---|---|---|
| `isPremium()` | `bool` | `price_cents > 0` |
| `publisher()` | `BelongsTo` | Vía `publisher_id` |
| `versions()` / `installations()` / `reviews()` / `purchases()` / `payouts()` | `HasMany` | |
| `categories()` | `BelongsToMany` | A través de `arqel_plugin_category_assignments` |
| `scopePublished()` | `Builder` | `status = published` — el único estado público |
| `scopeOfType(string $type)` | `Builder` | |
| `scopeSearch(string $term)` | `Builder` | Nombre + descripción |
| `scopeFeatured()` / `scopeTrending()` / `scopeNewThisWeek()` | `Builder` | |
| `scopeMostPopular()` | `Builder` | Vía `withCount('installations')` |

El estado es un enum fijo en la migración: `draft` / `pending` / `published` / `archived`.

### `Models\PluginVersion`

`plugin()`, `installations()`; cast `released_at => datetime`. Único en `(plugin_id, version)`.

### `Models\PluginInstallation`

`$timestamps = false` (append-only). `plugin()`, `version()`; casts `installed_at => datetime`, `context => array`.

### `Models\PluginReview`

`votes()` (`HasMany`), más los scopes `scopePublished`, `scopePending`, `scopeHidden`, `scopeMostHelpful` (helpful_count desc, luego score desc), `scopeMostRecent`, `scopeHighestRated`, `scopePositive` (≥4 estrellas). Las columnas incluyen `verified_purchaser`, `helpful_count`, `unhelpful_count`, `status` (`pending`/`published`/`hidden`, por defecto `pending`) y `moderation_reason`.

### `Models\PluginReviewVote`

Fillable `review_id`, `user_id`, `vote`. Relaciones `review()` y un `user()` defensivo resuelto a través de `auth.providers.users.model`. Único en `(review_id, user_id)`.

### `Models\PluginCategory`

`plugins()`, `parent()`, `children()`; scopes `scopeRoot`, `scopeOrdered`. Se siembran cinco categorías por defecto: `fields`, `widgets`, `themes`, `integrations`, `utilities`.

### `Models\PluginPurchase`

`plugin()` más un `buyer()` defensivo; scopes `scopeCompleted`, `scopePending`, `scopeRefunded`. Lleva `license_key` (único), `amount_cents`, `currency`, `payment_id`, `status` (`pending`/`completed`/`refunded`/`failed`), `purchased_at`, `refunded_at`.

### `Models\PluginPayout`

`plugin()`, `publisher()`. Lleva `amount_cents`, `currency`, `status` (`pending`/`paid`/`failed`), `period_start`, `period_end`.

### `Models\SecurityScan`

`plugin()`; castea `scan_started_at`/`scan_completed_at` a datetime y `findings` a array. `status` es `pending|running|passed|flagged|failed`.

### `Models\Publisher`

| Miembro | Tipo | Descripción |
|---|---|---|
| `plugins()` | `HasMany` | Vía `publisher_id` |
| `scopeVerified()` | `Builder` | |
| `scopeWithPlugins()` | `Builder` | Publishers con al menos un plugin publicado |
| `aggregateStats()` | `array` | `{plugins_count, total_downloads, avg_rating}` sobre plugins publicados, instalaciones y reseñas publicadas |

## Servicios

### `Services\PluginAutoChecker` (final readonly)

`check(Plugin $plugin): array` ejecuta cinco verificaciones sin red y devuelve `{checks: list<{name, status, message}>, passed: bool}`: `composer_package_format` (falla ante una regex `vendor/package` inválida), `github_url_format` (falla cuando el host no es github.com), `description_length` (advierte por debajo de 50 caracteres), `screenshots_count` (advierte en cero), `name_uniqueness` (advierte ante un duplicado).

### `Services\PluginConventionValidator` (final readonly)

Valida arrays ya decodificados — no realiza E/S.

| Método | Tipo |
|---|---|
| `validateComposerJson(array $composerData)` | `ConventionValidationResult` |
| `validateNpmPackageJson(array $packageData)` | `ConventionValidationResult` |

Sobre `composer.json` verifica `type=arqel-plugin` (falla), `extra.arqel.plugin-type` dentro del enum `field-pack`, `widget-pack`, `theme`, `integration`, `language-pack`, `tool` (falla), `extra.arqel.compat.arqel` como restricción semver válida (falla), un `extra.arqel.category` no vacío (falla), `extra.arqel.installation-instructions` (advierte si falta) y `keywords` conteniendo `arqel` + `plugin` (advierte). Sobre `package.json` acepta `arqel.plugin-type` en la raíz **o** una entrada `peerDependencies."@arqel-dev/types"`.

### `Services\ConventionValidationResult` (final readonly)

Value object con `checks`, `passed`, `warnings`, `errors`. Factories `ConventionValidationResult::success(array $checks): self` y `::failed(array $checks): self`; `toArray(): array` para serialización.

### `Services\TrendingScoreCalculator` (final readonly)

`calculate(Plugin $plugin): float` devuelve `installations_last_7d * 1.0 + recent_positive_reviews * 5.0` (positiva = ≥4 estrellas en los últimos 30 días), redondeado a dos decimales. `recalculateAll(): int` itera `Plugin::published()` y persiste `trending_score` más `trending_score_updated_at`, devolviendo la cantidad de plugins actualizados.

El peso 5× sobre las reseñas refleja que la señal social pesa más que el conteo bruto de instalaciones; la ventana de instalaciones de 7 días permite que emerjan las novedades, mientras que la ventana de reseñas de 30 días evita una caída instantánea tras un pico.

### `Services\SecurityScanner` (final readonly)

Constructor `(VulnerabilityDatabase $vulnDb)`. `scan(Plugin $plugin): SecurityScan`:

1. Crea un `SecurityScan` en `running`.
2. Busca vulnerabilidades para los paquetes de composer y npm mediante la base de datos inyectada.
3. Verifica la licencia contra la allowlist (`MIT`, `Apache-2.0`, `BSD-2-Clause`, `BSD-3-Clause`) — cualquier otra es una advertencia `low`.
4. Acumula la severidad hasta el máximo encontrado. `critical` → `failed` más deslistado automático (`status = archived`) más un `PluginAutoDelistedEvent`; `high`/`medium` → `flagged`; `low` o ninguna → `passed`.

Solo se deslistan automáticamente los plugins actualmente `published`, de modo que los plugins ya archivados o en borrador nunca saturan el evento.

### `Services\StaticVulnerabilityDatabase` (final readonly)

Binding por defecto de `VulnerabilityDatabase`. `lookup(string $package, string $ecosystem): array` devuelve una lista vacía — las apps anfitrionas revinculan el contrato a un proveedor real.

### `Services\VersionMatcher` (final)

`VersionMatcher::isAffected(?string $installed, string $affectedConstraint): bool` — si una versión instalada satisface la restricción de versiones afectadas de un advisory.

### `Services\LicenseKeyGenerator` (final readonly)

`generate(): string` devuelve `ARQ-XXXX-XXXX-XXXX-XXXX` (cuatro grupos hexadecimales de cuatro caracteres a partir de `random_bytes(8)`). `verify(string $key, PluginPurchase $purchase): bool` valida el formato, la coincidencia y un estado `completed`, comparando con `hash_equals` por seguridad ante ataques de temporización.

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
    public string $affectedVersions,  // restricción de composer, p. ej. '<2.0'
) {}
```

### `Contracts\PaymentGateway`

```php
public function createCheckoutSession(Plugin $plugin, int $userId): CheckoutSession;
public function verifyPayment(string $paymentId): PaymentResult;
public function processRefund(PluginPurchase $purchase): bool;
```

### `Contracts\CheckoutSession` / `Contracts\PaymentResult` (DTOs final readonly)

```php
new CheckoutSession(url: '...', sessionId: '...');
new PaymentResult(status: 'completed', amountCents: 2900, paymentId: '...');
```

## Pasarelas de pago

| Clase | Notas |
|---|---|
| `Services\Payments\MockPaymentGateway` (final readonly) | El binding por defecto. URL stub `/marketplace/mock-checkout/{slug}`, `sessionId` con prefijo `mock_`. Los reembolsos solo tienen éxito para compras `completed` |
| `Services\Payments\StripeConnectGateway` (final readonly) | Stripe Connect real vía `stripe/stripe-php` (declarado en `suggest`, no en `require`). Instancia `\Stripe\StripeClient` cuando el SDK está presente y lanza una `RuntimeException` accionable en caso contrario. `createCheckoutSession` añade `application_fee_amount` + `transfer_data.destination` cuando el plugin tiene un `publisher_stripe_account_id`. Los errores de la API de Stripe se envuelven en `Exceptions\MarketplaceException` (checkout/verify) o devuelven `false` con un log de advertencia (refund) |

Cuando `payment_gateway=stripe` pero el SDK falta, el provider recae en `MockPaymentGateway` y registra una advertencia en lugar de romper el arranque. El reparto de ingresos por defecto es 80% publisher / 20% plataforma, configurable por plugin vía `revenue_share_percent`.

## HTTP

Todas las rutas viven bajo un prefijo configurable — `config('arqel-marketplace.route_prefix')`, por defecto `api/marketplace`. Poner `arqel-marketplace.enabled` en `false` no registra ninguna ruta. Los endpoints públicos usan el middleware `api`; el grupo autenticado añade `auth:sanctum` cuando ese guard existe, recayendo en `auth`.

### Públicas (`api`)

| Verbo | Ruta | Nombre |
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

`PluginListController` acepta `type`, `search`, `page` y `per_page` (acotado a `[1, 100]`) y se restringe a `status=published`. `PluginDetailController` devuelve `{plugin, reviews, versions}` con solo las reseñas `published` ordenadas por `mostHelpful`, y responde `404` ante un plugin en borrador, pendiente o archivado. `PluginReviewListController` toma `?sort=helpful|recent|rating` (por defecto `helpful`). `NewPluginsController` toma `?days=` (por defecto 7, acotado `[1, 90]`); `TrendingPluginsController` y `MostPopularPluginsController` devuelven el top 20. `CategoryListController` acepta `?root=1`.

### Autenticadas

| Verbo | Ruta | Nombre |
|---|---|---|
| POST | `plugins/{slug}/reviews` | `arqel.marketplace.plugins.reviews.store` |
| POST | `plugins/{slug}/reviews/{reviewId}/vote` | `…reviews.vote.store` |
| DELETE | `plugins/{slug}/reviews/{reviewId}/vote` | `…reviews.vote.destroy` |
| POST | `plugins/submit` | `arqel.marketplace.submit` |
| POST | `plugins/{slug}/purchase` | `…plugins.purchase.initiate` |
| POST | `plugins/{slug}/purchase/confirm` | `…plugins.purchase.confirm` |
| GET | `plugins/{slug}/download` | `arqel.marketplace.plugins.download` |
| GET | `publisher/payouts` | `arqel.marketplace.publisher.payouts` |

`PluginReviewController` valida `stars` (1–5) y `comment` (≤5000), crea la reseña con `status=pending`, y es idempotente gracias a `firstOrCreate(user_id + plugin_id)`. `PluginSubmissionController` crea el plugin con `status=pending`, registra `submitted_by_user_id` / `submitted_at`, ejecuta `PluginAutoChecker` hacia `submission_metadata` y despacha `PluginSubmitted` — la respuesta `201` es `{plugin, checks}`. `PluginPurchaseController::initiate` reutiliza una compra pendiente y devuelve `already_owned: true` cuando ya hay una completada (`422` para un plugin gratuito); `::confirm` verifica a través de la pasarela, marca la compra como `completed` y genera la clave de licencia, y es idempotente al reconfirmar. `PluginDownloadController` libera los plugins gratuitos y exige una compra completada para los premium (`403` en caso contrario). `PublisherPayoutsController` se filtra por `publisher_user_id = auth()->id()` con `per_page` acotado a `[1, 100]`.

### Admin (protegidas por Gate)

| Verbo | Ruta | Gate |
|---|---|---|
| GET | `admin/plugins` | `marketplace.review` |
| POST | `admin/plugins/{slug}/review` | `marketplace.review` |
| GET | `admin/reviews` | `marketplace.moderate-reviews` |
| POST | `admin/reviews/{reviewId}/moderate` | `marketplace.moderate-reviews` |
| POST | `admin/plugins/{slug}/feature` | `marketplace.feature` |
| GET | `admin/security-scans` | `marketplace.security-scans` |
| POST | `admin/plugins/{slug}/refund/{purchaseId}` | `marketplace.refund` |

`PluginAdminReviewController` toma `action=approve` (→ `published`, despacha `PluginApproved`) o `action=reject` (→ `archived` con `rejection_reason`, despacha `PluginRejected`). `PluginReviewModerationController::moderate` aplica `publish` o `hide` (este último exige un motivo). `PluginFeatureController` toma `{featured: bool}`. `AdminRefundController` devuelve `422` cuando la compra ya fue reembolsada o nunca se completó. Toda denegación de Gate es un `403`.

## Form requests

`Http\Requests\SubmitPluginRequest` valida `composer_package` contra una regex `vendor/package`, `github_url` como URL, `type` dentro del enum, `name` de 3–100 caracteres, `description` de 20–2000 caracteres, y `screenshots[]` como URLs. Cuando falta `slug` se deriva del nombre vía `Str::slug` y se comprueba su unicidad contra `arqel_plugins`.

## Eventos

Todos `final`, `Dispatchable` + `SerializesModels`: `Events\PluginSubmitted`, `Events\PluginApproved`, `Events\PluginRejected`, `Events\PluginPurchased` (lleva `Plugin` + `PluginPurchase`), y `Events\PluginAutoDelistedEvent` (lleva `Plugin` + `SecurityScan`).

## Excepciones

`Exceptions\MarketplaceException` es la excepción base del paquete, usada para envolver los errores de la API de la pasarela.

## Comandos de Artisan

| Comando | Función |
|---|---|
| `arqel:plugin:list {--validate}` | Descubre los plugins instalados vía `Composer\InstalledVersions::getInstalledPackagesByType('arqel-plugin')`, lee el `composer.json` de cada ruta de instalación e imprime `Name \| Version \| Plugin Type \| Category \| Status`. Con `--validate` ejecuta además `PluginConventionValidator` e imprime las verificaciones detalladas |
| `arqel:marketplace:trending` | Recalcula las puntuaciones de tendencia cacheadas; registra `Updated N plugins.` |
| `arqel:marketplace:scan {--plugin=} {--dry-run}` | Escanea todos los plugins `published` (o uno). Emite `Scanned N plugins. Findings: X critical, Y high, Z medium, W low.` |

Tanto `arqel:marketplace:trending` como `arqel:marketplace:scan` están pensados para ser programados a diario por la app anfitriona.

## Configuración

`config/arqel-marketplace.php` expone `enabled` (por defecto `true`), `route_prefix` (por defecto `api/marketplace`), `pagination` (por defecto `20`) y `submission_review_required` (por defecto `true`).

## Ejemplo

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
// Flujo de compra
$response = Http::withToken($token)
    ->post("https://arqel.dev/api/marketplace/plugins/{$slug}/purchase");
// → { purchase: {...}, checkout: { url, session_id } }

$response = Http::withToken($token)
    ->post("https://arqel.dev/api/marketplace/plugins/{$slug}/purchase/confirm", [
        'paymentId' => $sessionId,
    ]);
// → { purchase: { status: 'completed', license_key: 'ARQ-...' } }
```

## Relacionado

- SKILL: [`packages/marketplace/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/marketplace/SKILL.md)
- Código fuente: [`packages/marketplace/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/marketplace/src/)

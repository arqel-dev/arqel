# `arqel-dev/marketplace` — Referência de API

Namespace `Arqel\Marketplace\`. Backend do marketplace de plugins do Arqel: schema relacional, models Eloquent e uma API REST para descobrir e publicar plugins da comunidade (`field`, `widget`, `integration`, `theme`), mais reviews, categorização, trending, varredura de segurança e plugins pagos.

Ele é distribuído como um **pacote embutível**, em vez de uma aplicação Laravel monolítica, de modo que tanto o marketplace público em `arqel.dev/marketplace` quanto um marketplace privado self-hosted consomem o mesmo código.

## Models

Todos os models são `final`.

### `Models\Plugin`

Tabela `arqel_plugins`. Os casts incluem `screenshots => array`, `submission_metadata => array`, `submitted_at`/`reviewed_at`/`featured_at` como datetime, `featured => bool`, `price_cents` e `revenue_share_percent` como int.

| Membro | Tipo | Descrição |
|---|---|---|
| `isPremium()` | `bool` | `price_cents > 0` |
| `publisher()` | `BelongsTo` | Via `publisher_id` |
| `versions()` / `installations()` / `reviews()` / `purchases()` / `payouts()` | `HasMany` | |
| `categories()` | `BelongsToMany` | Através de `arqel_plugin_category_assignments` |
| `scopePublished()` | `Builder` | `status = published` — o único status público |
| `scopeOfType(string $type)` | `Builder` | |
| `scopeSearch(string $term)` | `Builder` | Nome + descrição |
| `scopeFeatured()` / `scopeTrending()` / `scopeNewThisWeek()` | `Builder` | |
| `scopeMostPopular()` | `Builder` | Via `withCount('installations')` |

O status é um enum hard-coded na migration: `draft` / `pending` / `published` / `archived`.

### `Models\PluginVersion`

`plugin()`, `installations()`; cast `released_at => datetime`. Único em `(plugin_id, version)`.

### `Models\PluginInstallation`

`$timestamps = false` (append-only). `plugin()`, `version()`; casts `installed_at => datetime`, `context => array`.

### `Models\PluginReview`

`votes()` (`HasMany`), mais os scopes `scopePublished`, `scopePending`, `scopeHidden`, `scopeMostHelpful` (helpful_count desc, depois score desc), `scopeMostRecent`, `scopeHighestRated`, `scopePositive` (≥4 estrelas). As colunas incluem `verified_purchaser`, `helpful_count`, `unhelpful_count`, `status` (`pending`/`published`/`hidden`, default `pending`) e `moderation_reason`.

### `Models\PluginReviewVote`

Fillable `review_id`, `user_id`, `vote`. Relações `review()` e um `user()` defensivo, resolvido através de `auth.providers.users.model`. Único em `(review_id, user_id)`.

### `Models\PluginCategory`

`plugins()`, `parent()`, `children()`; scopes `scopeRoot`, `scopeOrdered`. Cinco categorias são semeadas por padrão: `fields`, `widgets`, `themes`, `integrations`, `utilities`.

### `Models\PluginPurchase`

`plugin()` mais um `buyer()` defensivo; scopes `scopeCompleted`, `scopePending`, `scopeRefunded`. Carrega `license_key` (único), `amount_cents`, `currency`, `payment_id`, `status` (`pending`/`completed`/`refunded`/`failed`), `purchased_at`, `refunded_at`.

### `Models\PluginPayout`

`plugin()`, `publisher()`. Carrega `amount_cents`, `currency`, `status` (`pending`/`paid`/`failed`), `period_start`, `period_end`.

### `Models\SecurityScan`

`plugin()`; casts `scan_started_at`/`scan_completed_at` para datetime e `findings` para array. `status` é `pending|running|passed|flagged|failed`.

### `Models\Publisher`

| Membro | Tipo | Descrição |
|---|---|---|
| `plugins()` | `HasMany` | Via `publisher_id` |
| `scopeVerified()` | `Builder` | |
| `scopeWithPlugins()` | `Builder` | Publishers com ao menos um plugin publicado |
| `aggregateStats()` | `array` | `{plugins_count, total_downloads, avg_rating}` sobre plugins publicados, instalações e reviews publicadas |

## Services

### `Services\PluginAutoChecker` (final readonly)

`check(Plugin $plugin): array` roda cinco verificações sem acesso à rede e retorna `{checks: list<{name, status, message}>, passed: bool}`: `composer_package_format` (falha em um regex `vendor/package` inválido), `github_url_format` (falha quando o host não é github.com), `description_length` (aviso abaixo de 50 caracteres), `screenshots_count` (aviso quando zero), `name_uniqueness` (aviso em caso de duplicata).

### `Services\PluginConventionValidator` (final readonly)

Valida arrays já decodificados — não realiza nenhuma I/O.

| Método | Tipo |
|---|---|
| `validateComposerJson(array $composerData)` | `ConventionValidationResult` |
| `validateNpmPackageJson(array $packageData)` | `ConventionValidationResult` |

No `composer.json` ele verifica `type=arqel-plugin` (falha), `extra.arqel.plugin-type` dentro do enum `field-pack`, `widget-pack`, `theme`, `integration`, `language-pack`, `tool` (falha), `extra.arqel.compat.arqel` como um constraint semver válido (falha), um `extra.arqel.category` não vazio (falha), `extra.arqel.installation-instructions` (aviso quando ausente) e `keywords` contendo `arqel` + `plugin` (aviso). No `package.json` ele aceita `arqel.plugin-type` na raiz **ou** uma entrada `peerDependencies."@arqel-dev/types"`.

### `Services\ConventionValidationResult` (final readonly)

Value object com `checks`, `passed`, `warnings`, `errors`. Factories `ConventionValidationResult::success(array $checks): self` e `::failed(array $checks): self`; `toArray(): array` para serialização.

### `Services\TrendingScoreCalculator` (final readonly)

`calculate(Plugin $plugin): float` retorna `installations_last_7d * 1.0 + recent_positive_reviews * 5.0` (positiva = ≥4 estrelas nos últimos 30 dias), arredondado para duas casas decimais. `recalculateAll(): int` itera sobre `Plugin::published()` e persiste `trending_score` mais `trending_score_updated_at`, retornando o número de plugins atualizados.

O peso 5× sobre as reviews reflete que o sinal social supera as contagens brutas de instalação; a janela de 7 dias para instalações permite que novidades apareçam, enquanto a janela de 30 dias para reviews evita uma queda instantânea após um pico.

### `Services\SecurityScanner` (final readonly)

Construtor `(VulnerabilityDatabase $vulnDb)`. `scan(Plugin $plugin): SecurityScan`:

1. Cria um `SecurityScan` em `running`.
2. Busca vulnerabilidades para os pacotes composer e npm através da base injetada.
3. Verifica a licença contra a allowlist (`MIT`, `Apache-2.0`, `BSD-2-Clause`, `BSD-3-Clause`) — qualquer outra coisa é um aviso `low`.
4. Consolida a severidade no máximo encontrado. `critical` → `failed` mais auto-delist (`status = archived`) mais um `PluginAutoDelistedEvent`; `high`/`medium` → `flagged`; `low` ou nenhuma → `passed`.

Somente plugins atualmente `published` sofrem auto-delist, de modo que plugins já arquivados ou em draft nunca inundam o evento.

### `Services\StaticVulnerabilityDatabase` (final readonly)

Binding padrão de `VulnerabilityDatabase`. `lookup(string $package, string $ecosystem): array` retorna uma lista vazia — as aplicações hospedeiras religam o contrato a um provedor real.

### `Services\VersionMatcher` (final)

`VersionMatcher::isAffected(?string $installed, string $affectedConstraint): bool` — se uma versão instalada satisfaz o constraint de versões afetadas de um advisory.

### `Services\LicenseKeyGenerator` (final readonly)

`generate(): string` retorna `ARQ-XXXX-XXXX-XXXX-XXXX` (quatro grupos hex de quatro caracteres a partir de `random_bytes(8)`). `verify(string $key, PluginPurchase $purchase): bool` valida o formato, a correspondência e o status `completed`, comparando com `hash_equals` por segurança de timing.

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
    public string $affectedVersions,  // constraint composer, ex.: '<2.0'
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

## Gateways de pagamento

| Class | Notas |
|---|---|
| `Services\Payments\MockPaymentGateway` (final readonly) | O binding padrão. URL stub `/marketplace/mock-checkout/{slug}`, `sessionId` prefixado com `mock_`. Reembolsos só têm sucesso para compras `completed` |
| `Services\Payments\StripeConnectGateway` (final readonly) | Stripe Connect real via `stripe/stripe-php` (declarado em `suggest`, não em `require`). Instancia `\Stripe\StripeClient` quando o SDK está presente e lança uma `RuntimeException` acionável caso contrário. `createCheckoutSession` adiciona `application_fee_amount` + `transfer_data.destination` quando o plugin tem um `publisher_stripe_account_id`. Erros da API do Stripe são encapsulados em `Exceptions\MarketplaceException` (checkout/verify) ou retornam `false` com um log de aviso (refund) |

Quando `payment_gateway=stripe` mas o SDK está ausente, o provider cai no `MockPaymentGateway` e loga um aviso, em vez de quebrar o boot. A divisão de receita padrão é 80% publisher / 20% plataforma, configurável por plugin via `revenue_share_percent`.

## HTTP

Todas as rotas ficam sob um prefixo configurável — `config('arqel-marketplace.route_prefix')`, default `api/marketplace`. Definir `arqel-marketplace.enabled` como `false` não registra rota alguma. Os endpoints públicos usam o middleware `api`; o grupo autenticado adiciona `auth:sanctum` quando esse guard existe, caindo em `auth` como fallback.

### Públicos (`api`)

| Verbo | Route | Nome |
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

`PluginListController` aceita `type`, `search`, `page` e `per_page` (limitado a `[1, 100]`) e restringe a `status=published`. `PluginDetailController` retorna `{plugin, reviews, versions}` apenas com reviews `published` ordenadas por `mostHelpful`, e responde `404` para um plugin em draft, pending ou archived. `PluginReviewListController` recebe `?sort=helpful|recent|rating` (default `helpful`). `NewPluginsController` recebe `?days=` (default 7, limitado a `[1, 90]`); `TrendingPluginsController` e `MostPopularPluginsController` retornam os 20 primeiros. `CategoryListController` aceita `?root=1`.

### Autenticados

| Verbo | Route | Nome |
|---|---|---|
| POST | `plugins/{slug}/reviews` | `arqel.marketplace.plugins.reviews.store` |
| POST | `plugins/{slug}/reviews/{reviewId}/vote` | `…reviews.vote.store` |
| DELETE | `plugins/{slug}/reviews/{reviewId}/vote` | `…reviews.vote.destroy` |
| POST | `plugins/submit` | `arqel.marketplace.submit` |
| POST | `plugins/{slug}/purchase` | `…plugins.purchase.initiate` |
| POST | `plugins/{slug}/purchase/confirm` | `…plugins.purchase.confirm` |
| GET | `plugins/{slug}/download` | `arqel.marketplace.plugins.download` |
| GET | `publisher/payouts` | `arqel.marketplace.publisher.payouts` |

`PluginReviewController` valida `stars` (1–5) e `comment` (≤5000), cria a review com `status=pending` e é idempotente através de `firstOrCreate(user_id + plugin_id)`. `PluginSubmissionController` cria o plugin com `status=pending`, registra `submitted_by_user_id` / `submitted_at`, roda o `PluginAutoChecker` gravando em `submission_metadata` e dispara `PluginSubmitted` — a resposta `201` é `{plugin, checks}`. `PluginPurchaseController::initiate` reaproveita uma compra pendente e retorna `already_owned: true` quando já existe uma concluída (`422` para um plugin gratuito); `::confirm` verifica através do gateway, marca a compra como `completed` e gera a license key, sendo idempotente em uma reconfirmação. `PluginDownloadController` libera plugins gratuitos e exige uma compra concluída para os premium (`403` caso contrário). `PublisherPayoutsController` é filtrado por `publisher_user_id = auth()->id()` com `per_page` limitado a `[1, 100]`.

### Admin (protegido por Gate)

| Verbo | Route | Gate |
|---|---|---|
| GET | `admin/plugins` | `marketplace.review` |
| POST | `admin/plugins/{slug}/review` | `marketplace.review` |
| GET | `admin/reviews` | `marketplace.moderate-reviews` |
| POST | `admin/reviews/{reviewId}/moderate` | `marketplace.moderate-reviews` |
| POST | `admin/plugins/{slug}/feature` | `marketplace.feature` |
| GET | `admin/security-scans` | `marketplace.security-scans` |
| POST | `admin/plugins/{slug}/refund/{purchaseId}` | `marketplace.refund` |

`PluginAdminReviewController` recebe `action=approve` (→ `published`, dispara `PluginApproved`) ou `action=reject` (→ `archived` com `rejection_reason`, dispara `PluginRejected`). `PluginReviewModerationController::moderate` aplica `publish` ou `hide` (este último exigindo um motivo). `PluginFeatureController` recebe `{featured: bool}`. `AdminRefundController` retorna `422` quando a compra já foi reembolsada ou nunca foi concluída. Toda negação de Gate resulta em `403`.

## Form requests

`Http\Requests\SubmitPluginRequest` valida `composer_package` contra um regex `vendor/package`, `github_url` como URL, `type` dentro do enum, `name` com 3–100 caracteres, `description` com 20–2000 caracteres, e `screenshots[]` como URLs. Quando `slug` está ausente, ele é derivado do nome via `Str::slug` e verificado quanto à unicidade em `arqel_plugins`.

## Eventos

Todos `final`, `Dispatchable` + `SerializesModels`: `Events\PluginSubmitted`, `Events\PluginApproved`, `Events\PluginRejected`, `Events\PluginPurchased` (carrega `Plugin` + `PluginPurchase`) e `Events\PluginAutoDelistedEvent` (carrega `Plugin` + `SecurityScan`).

## Exceções

`Exceptions\MarketplaceException` é a exceção base do pacote, usada para encapsular erros da API dos gateways.

## Comandos Artisan

| Comando | Função |
|---|---|
| `arqel:plugin:list {--validate}` | Descobre plugins instalados via `Composer\InstalledVersions::getInstalledPackagesByType('arqel-plugin')`, lê o `composer.json` do caminho de instalação de cada um e imprime `Name \| Version \| Plugin Type \| Category \| Status`. Com `--validate` também roda o `PluginConventionValidator` e imprime as verificações detalhadas |
| `arqel:marketplace:trending` | Recalcula os trending scores em cache; loga `Updated N plugins.` |
| `arqel:marketplace:scan {--plugin=} {--dry-run}` | Varre todos os plugins `published` (ou apenas um). Emite `Scanned N plugins. Findings: X critical, Y high, Z medium, W low.` |

Tanto `arqel:marketplace:trending` quanto `arqel:marketplace:scan` foram pensados para serem agendados diariamente pela aplicação hospedeira.

## Configuração

`config/arqel-marketplace.php` expõe `enabled` (default `true`), `route_prefix` (default `api/marketplace`), `pagination` (default `20`) e `submission_review_required` (default `true`).

## Exemplo

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
// Fluxo de compra
$response = Http::withToken($token)
    ->post("https://arqel.dev/api/marketplace/plugins/{$slug}/purchase");
// → { purchase: {...}, checkout: { url, session_id } }

$response = Http::withToken($token)
    ->post("https://arqel.dev/api/marketplace/plugins/{$slug}/purchase/confirm", [
        'paymentId' => $sessionId,
    ]);
// → { purchase: { status: 'completed', license_key: 'ARQ-...' } }
```

## Relacionados

- SKILL: [`packages/marketplace/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/marketplace/SKILL.md)
- Código-fonte: [`packages/marketplace/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/marketplace/src/)

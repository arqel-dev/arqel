# SKILL.md — arqel/marketplace

> Contexto canônico para AI agents.

## Purpose

`arqel/marketplace` é o backend do plugin marketplace do Arqel — schema relacional, models Eloquent e API REST para descoberta + publicação de plugins community (`field`, `widget`, `integration`, `theme`). Cobre RF-IN-09.

A decisão arquitetural é entregar como **pacote embeddable** em vez de app Laravel monolítico:

- **Dogfood**: o marketplace público em `arqel.dev/marketplace` será uma app Arqel-powered consumindo este pacote.
- **Self-hosted**: tier enterprise pode rodar marketplace privado (plugins internos, white-label).
- **Testabilidade**: testes de integração rodam no mesmo runner Pest do resto do monorepo.

## Status

**Entregue (MKTPLC-001):**

- Esqueleto `arqel/marketplace` com PSR-4 `Arqel\Marketplace\` → `src/`, autoload-dev `Arqel\Marketplace\Tests\` → `tests/`.
- **Migration única** `2026_05_01_000000_create_arqel_marketplace_tables.php` cria 4 tabelas:
  - `arqel_plugins` — slug unique, name, description, type enum, author_id nullable, composer/npm packages, github_url, license, screenshots JSON, latest_version, status enum (draft/pending/published/archived), timestamps. Índice em `(type, status)`.
  - `arqel_plugin_versions` — plugin_id FK cascade, version, changelog, released_at, timestamps. Unique `(plugin_id, version)`.
  - `arqel_plugin_installations` — plugin_id FK cascade, plugin_version_id FK setNull, installed_at, anonymized_user_hash, context JSON. Sem timestamps automáticos. Índice `(plugin_id, installed_at)`.
  - `arqel_plugin_reviews` — plugin_id FK cascade, user_id, stars 1-5, comment, timestamps.
- **Models** (todos `final`):
  - `Plugin` — fillable + casts (`screenshots` array, `status` string). Relations `versions()`, `installations()`, `reviews()`. Scopes `scopePublished`, `scopeOfType`, `scopeSearch`.
  - `PluginVersion` — `plugin()`, `installations()`, cast `released_at => datetime`.
  - `PluginInstallation` — `$timestamps = false`, `plugin()`, `version()`, casts `installed_at => datetime`, `context => array`.
  - `PluginReview` — `plugin()`, scope `scopePositive` (≥4 stars).
- **API REST** com 3 controllers single-action `final`:
  - `PluginListController` — `GET {prefix}/plugins` paginado, query params `type`, `search`, `per_page` (clamp [1, 100]), `page`. Restringe a `status=published`.
  - `PluginDetailController` — `GET {prefix}/plugins/{slug}` retorna `{plugin, reviews(latest 5), versions}`. 404 quando draft/pending/archived.
  - `PluginReviewController` — `POST {prefix}/plugins/{slug}/reviews` valida `stars` (1-5) + `comment` (≤5000), idempotente via `firstOrCreate(user_id+plugin_id)`. 401 sem auth, 422 validation, 404 plugin não publicado.
- **Routes** `routes/api.php` com prefixo configurável (`config('arqel-marketplace.route_prefix')`, default `'api/marketplace'`). Endpoints públicos sob middleware `api`; review com `auth:sanctum` + fallback `auth` quando sanctum não disponível. `enabled=false` desativa todas as rotas.
- **Config** `config/arqel-marketplace.php`:
  - `enabled` (default `true`)
  - `route_prefix` (default `'api/marketplace'`)
  - `pagination` (default `20`)
  - `submission_review_required` (default `true`) — flag consumida pelo MKTPLC-002.
- **Testes Pest 3 + Orchestra Testbench** (18 testes mínimos):
  - `Feature/PluginListControllerTest` (5): published-only, filter por type, search name+description, paginação, JSON shape.
  - `Feature/PluginDetailControllerTest` (3): happy + 404 inexistente + 404 draft.
  - `Feature/PluginReviewControllerTest` (4): 201 happy + 422 stars inválidas + 401 sem auth + idempotência.
  - `Unit/PluginScopeTest` (4): scopePublished, scopeOfType, scopeSearch, scopePositive.
  - `Feature/MarketplaceServiceProviderTest` (2): boot do provider + config publishable.

**Entregue (MKTPLC-002):**

- **Migration** `2026_05_02_000000_add_submission_columns_to_arqel_plugins.php` adiciona `submission_metadata` (JSON), `submitted_by_user_id`, `submitted_at`, `reviewed_by_user_id`, `reviewed_at`, `rejection_reason` na tabela `arqel_plugins` (com índices em `submitted_by_user_id` e `reviewed_by_user_id`).
- **Plugin model** estendido com fillable + casts (`submission_metadata => array`, `submitted_at|reviewed_at => datetime`).
- **`SubmitPluginRequest`** (`Http/Requests/`) — FormRequest com regras: `composer_package` regex `vendor/package`, `github_url` URL, `type` in enum, `name` 3-100, `description` 20-2000, `screenshots[]` URLs, `slug` derivado de name via `Str::slug` quando ausente + uniqueness check em `arqel_plugins`.
- **`PluginAutoChecker`** (`Services/`, `final readonly`) — 5 checks defensivos (sem rede): `composer_package_format` (fail se regex inválida), `github_url_format` (fail se host != github.com), `description_length` (warn se < 50), `screenshots_count` (warn se 0), `name_uniqueness` (warn se duplicate). Retorna `{checks: [...], passed: bool}`.
- **`PluginSubmissionController`** (`POST {prefix}/plugins/submit`, single-action `__invoke`) — cria Plugin com `status=pending`, `submitted_by_user_id=auth()->id()`, `submitted_at=now()`, roda `PluginAutoChecker` e popula `submission_metadata`. Dispara `PluginSubmitted`.
- **`PluginAdminReviewController`** (`POST {prefix}/admin/plugins/{slug}/review`) — Gate `marketplace.review` (403 sem ability). Action `approve` → `published` + `PluginApproved` event. Action `reject` → `archived` + `rejection_reason` + `PluginRejected` event.
- **`PluginAdminListController`** (`GET {prefix}/admin/plugins?status=pending`) — Gate-protected admin queue com paginação `per_page` clamp [1, 100].
- **Events** (`Events/`): `PluginSubmitted`, `PluginApproved`, `PluginRejected` (todos final + Dispatchable + SerializesModels). Email notifications reais ficam para integration posterior.
- **19 testes Pest novos** (37 totais no pacote): Feature submission (6), Feature admin review (5), Feature admin list (3), Unit AutoChecker (5).

### Submission workflow (MKTPLC-002)

Fluxo state-machine: `pending` (após `POST /plugins/submit`) → `published` (após `approve`) ou `archived` (após `reject` com `rejection_reason`).

Exemplo de payload de submissão:

```php
Http::withToken($token)
    ->post('https://arqel.dev/api/marketplace/plugins/submit', [
        'composer_package' => 'acme/awesome-plugin',
        'github_url' => 'https://github.com/acme/awesome-plugin',
        'type' => 'widget',
        'name' => 'Awesome Plugin',
        'description' => 'Plugin que resolve X de forma elegante para admin panels Arqel.',
        'screenshots' => ['https://example.com/screen-1.png'],
    ]);
```

Resposta `201` traz `{plugin: {...}, checks: {checks: [...], passed: bool}}`. Para aprovação admin (Gate `marketplace.review`):

```php
Http::withToken($adminToken)
    ->post('https://arqel.dev/api/marketplace/admin/plugins/awesome-plugin/review', [
        'action' => 'approve',
    ]);
```

**Entregue (MKTPLC-003):**

Plugin metadata convention + validator + comando Artisan `arqel:plugin:list`. Ver `docs/CONVENTION.md` para o schema completo.

- **`PluginConventionValidator`** (`Services/PluginConventionValidator.php`, `final readonly`) — valida arrays decodificados de `composer.json` e `package.json` contra o schema. Em `composer.json` checa `type=arqel-plugin` (fail), `extra.arqel.plugin-type` no enum (`field-pack`, `widget-pack`, `theme`, `integration`, `language-pack`, `tool`) (fail), `extra.arqel.compat.arqel` como constraint semver válida (fail), `extra.arqel.category` non-empty (fail), `extra.arqel.installation-instructions` (warn se ausente), `keywords` contém `arqel`+`plugin` (warn). Em `package.json` aceita `arqel.plugin-type` no root OU `peerDependencies."@arqel/types"`. Não faz I/O — recebe arrays prontos.
- **`ConventionValidationResult`** (`Services/ConventionValidationResult.php`, `final readonly`) — value-object com `checks`, `passed`, `warnings`, `errors`. Factories `success(checks)` e `failed(checks)`. Method `toArray()` para serialização.
- **`PluginListCommand`** (`Console/PluginListCommand.php`, `final extends Command`) — signature `arqel:plugin:list {--validate}`. Descobre plugins via `Composer\InstalledVersions::getInstalledPackagesByType('arqel-plugin')`, lê o `composer.json` de cada install path, imprime tabela `Name | Version | Plugin Type | Category | Status`. Com `--validate` roda o validator e imprime checks detalhados.
- **Service provider** — `MarketplaceServiceProvider::packageBooted()` registra o comando via `$this->commands(...)` quando `runningInConsole()`.
- **Documentação** — `docs/CONVENTION.md` PT-BR com schema completo, plugin types, exemplo full de field-pack, e como usar `arqel:plugin:list --validate`.
- **20 testes Pest novos**: Unit `Services/PluginConventionValidatorTest` (12), Unit `ConventionValidationResultTest` (4), Feature `PluginListCommandTest` (4).

Exemplo de uso:

```bash
php artisan arqel:plugin:list           # tabela de plugins instalados
php artisan arqel:plugin:list --validate # roda PluginConventionValidator em cada
```

```php
use Arqel\Marketplace\Services\PluginConventionValidator;

$validator = new PluginConventionValidator;
$composer = json_decode(file_get_contents('composer.json'), true);
$result = $validator->validateComposerJson($composer);

if (! $result->passed) {
    foreach ($result->errors as $error) {
        echo "ERROR: {$error}\n";
    }
}
```

**Entregue (MKTPLC-006):** Reviews + ratings system com helpful votes, sort options, verified-purchaser flag e moderation queue.

- **Migration** `2026_05_03_000000_extend_plugin_reviews.php` — adiciona `verified_purchaser`, `helpful_count`, `unhelpful_count`, `status` (`pending`/`published`/`hidden`, default `pending`), `moderation_reason` na tabela `arqel_plugin_reviews`. Cria nova tabela `arqel_plugin_review_votes` com unique `(review_id, user_id)`.
- **`PluginReview` model** — fillable + casts estendidos; relação `votes()` HasMany; scopes `scopePublished`, `scopePending`, `scopeHidden`, `scopeMostHelpful` (helpful_count desc → score desc), `scopeMostRecent`, `scopeHighestRated`.
- **`PluginReviewVote` model** (`final`) — fillable `review_id, user_id, vote`; relações `review()` BelongsTo + `user()` BelongsTo defensiva (resolve via `auth.providers.users.model`).
- **`PluginReviewVoteController`** — `store()` cria/atualiza voto via lookup `(review_id, user_id)` em transaction, recalcula counters; `destroy()` remove voto e decrementa.
- **`PluginReviewListController`** single-action — `GET {prefix}/plugins/{slug}/reviews?sort=helpful|recent|rating` lista apenas reviews `published` com sort default `helpful`.
- **`PluginReviewModerationController`** — `index()` lista reviews por status (Gate `marketplace.moderate-reviews`); `moderate()` aplica `publish` ou `hide` (com reason obrigatória).
- **`PluginReviewController`** — agora seta `status=pending` ao criar review (idempotência preservada).
- **`PluginDetailController`** — agora retorna apenas reviews `published` ordenadas por `mostHelpful`.
- **Routes novas**: 5 endpoints (`GET .../reviews`, `POST/DELETE .../reviews/{id}/vote`, `GET admin/reviews`, `POST admin/reviews/{id}/moderate`).
- **22 testes Pest novos**: Feature vote (6) + Feature list (4) + Feature moderation (5) + Unit scopes (4) + Unit vote model (2) + 1 atualização do detail test.

### Reviews + ratings (MKTPLC-006)

Sort options expostos via query string `?sort=`:

- `helpful` (default) — ordena por `helpful_count` desc, score (`helpful − unhelpful`) desc.
- `recent` — `created_at` desc.
- `rating` — `stars` desc.

Exemplo de payload de voto:

```php
Http::withToken($token)
    ->post("https://arqel.dev/api/marketplace/plugins/{$slug}/reviews/{$reviewId}/vote", [
        'vote' => 'helpful', // ou 'unhelpful'
    ]);

Http::withToken($token)
    ->delete("https://arqel.dev/api/marketplace/plugins/{$slug}/reviews/{$reviewId}/vote");
```

Moderation (Gate `marketplace.moderate-reviews`):

```php
Http::withToken($adminToken)
    ->post("https://arqel.dev/api/marketplace/admin/reviews/{$reviewId}/moderate", [
        'action' => 'publish',
    ]);

Http::withToken($adminToken)
    ->post("https://arqel.dev/api/marketplace/admin/reviews/{$reviewId}/moderate", [
        'action' => 'hide',
        'reason' => 'Spam content',
    ]);
```

`verified_purchaser` é column placeholder (default `false`) — preparação para paid plugins; ainda não populada por nenhum fluxo.

**Entregue (MKTPLC-007):** Categorization + trending + featured (editor's picks).

- **Migration** `2026_05_04_000000_add_categories_and_trending.php` cria:
  - `arqel_plugin_categories` — slug unique, name, description, sort_order, parent_id self-referencing.
  - Pivot `arqel_plugin_category_assignments` (`plugin_id`+`category_id` PK composto, FK cascade).
  - Colunas em `arqel_plugins`: `featured` (bool), `featured_at`, `trending_score` (float, cached), `trending_score_updated_at`.
  - Seed default de 5 categorias (`fields`, `widgets`, `themes`, `integrations`, `utilities`).
- **`PluginCategory` model** (`final`) — fillable + casts; relations `plugins()`, `parent()`, `children()`; scopes `scopeRoot`, `scopeOrdered`.
- **Plugin model estendido** — fillable + casts para `featured`/`featured_at`/`trending_score`; relação `categories()`; scopes `scopeFeatured`, `scopeTrending`, `scopeNewThisWeek`, `scopeMostPopular` (via `withCount('installations')`).
- **`TrendingScoreCalculator`** (`Services/`, `final readonly`) — `calculate(Plugin)` retorna `installations_last_7d * 1.0 + recent_positive_reviews(≥4 stars, últimos 30d) * 5.0`, arredondado em 2 casas. `recalculateAll()` itera `Plugin::published()` e persiste `trending_score` + `trending_score_updated_at`.
- **`RecalculateTrendingScoresCommand`** — `arqel:marketplace:trending`, log `Updated N plugins.`. Apps host devem agendar via `Schedule::command('arqel:marketplace:trending')->daily()`.
- **Controllers REST novos** (todos single-action `final`):
  - `CategoryListController` — `GET {prefix}/categories[?root=1]` lista categorias (raiz + children eager-loaded).
  - `PluginsByCategoryController` — `GET {prefix}/categories/{slug}/plugins` paginado, 404 quando categoria inexistente.
  - `FeaturedPluginsController` — `GET {prefix}/featured` lista featured published ordenados por `featured_at` desc.
  - `TrendingPluginsController` — `GET {prefix}/trending` ordenado por `trending_score` desc, limit 20.
  - `NewPluginsController` — `GET {prefix}/new?days=7` (default 7, clamp [1, 90]).
  - `MostPopularPluginsController` — `GET {prefix}/popular` por contagem all-time de instalações, limit 20.
  - `PluginFeatureController` — `POST {prefix}/admin/plugins/{slug}/feature` body `{featured: bool}`, Gate `marketplace.feature` (403/422/404).
- **28 testes Pest novos** (106 totais no pacote): Feature CategoryList (3), PluginsByCategory (3), Featured (2), Trending (2), New (3), MostPopular (2), Feature toggle (4), Recalculate command (2), Unit TrendingScoreCalculator (4), PluginCategoryScopes (3).

### Categorization + trending (MKTPLC-007)

Heurística de score: o peso `5x` em reviews positivas reflete que sinal social vale mais que install count cru. Janela `7d` para installations ajuda categorias frescas a aparecer no trending; `30d` para reviews evita drop instantâneo após picos.

Schedule do recálculo deve ser **diário** — recalcular em cada request seria custoso e o trending por natureza é caching-friendly:

```php
// In your application's app/Console/Kernel.php or routes/console.php
Schedule::command('arqel:marketplace:trending')->daily();
```

Exemplo de discovery:

```php
Http::get('https://arqel.dev/api/marketplace/categories?root=1');
Http::get('https://arqel.dev/api/marketplace/categories/widgets/plugins');
Http::get('https://arqel.dev/api/marketplace/featured');
Http::get('https://arqel.dev/api/marketplace/trending');
Http::get('https://arqel.dev/api/marketplace/new?days=14');
Http::get('https://arqel.dev/api/marketplace/popular');

// Admin (Gate marketplace.feature):
Http::withToken($adminToken)
    ->post("https://arqel.dev/api/marketplace/admin/plugins/{$slug}/feature", [
        'featured' => true,
    ]);
```

**Por chegar:**

- **MKTPLC-004** — Stats/analytics (instalações por dia, top plugins, trending, search analytics).
- **MKTPLC-005+** — Admin panel (Arqel-powered) para publishers + moderação.

## Conventions

- Models são `final` — Laravel 12 permite porque `lazyHydrate` não é mais usado em hydrate paths críticos.
- Controllers single-action `__invoke` para alinhamento com padrão `arqel/audit`/`arqel/versioning`.
- Status enum hard-coded na migration (`draft`/`pending`/`published`/`archived`) — futuras transições adicionais exigem migration nova.
- `published` é o ÚNICO status público; outros são opacos (404 explícito) para evitar leak de plugins em moderação.
- Idempotência de review usa `firstOrCreate(user_id+plugin_id)` — não há unique index em DB porque `user_id` é nullable (futuras reviews anônimas).

## Anti-patterns

- ❌ Não expor plugins não-`published` na API pública — moderação é privada.
- ❌ Não usar `updated_at` em `arqel_plugin_installations` — tracking é append-only.
- ❌ Não fazer `Plugin::all()` no listing — sempre via `scopePublished` + paginação.
- ❌ Não confiar em `user_id` raw vindo do client no review — sempre derivar de `$request->user()`.

## Examples

```php
// Listar plugins do tipo widget contendo "calendar"
$response = Http::get('https://arqel.dev/api/marketplace/plugins', [
    'type' => 'widget',
    'search' => 'calendar',
    'per_page' => 30,
]);

// Submit de review (precisa Sanctum token)
$response = Http::withToken($token)
    ->post('https://arqel.dev/api/marketplace/plugins/acme-stripe/reviews', [
        'stars' => 5,
        'comment' => 'Saved me a week of work.',
    ]);
```

## Related

- `arqel/core` — `Resource` API que MKTPLC-005 vai consumir para Arqel-powered admin do marketplace.
- `arqel/auth` — futuras integrações para OAuth de publishers.
- RF-IN-09 — requirement origem em `PLANNING/01-spec-tecnica.md`.
- ADR-008 — testes obrigatórios.

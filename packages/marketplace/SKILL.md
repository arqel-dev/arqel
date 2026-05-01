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

**Por chegar:**

- **MKTPLC-003** — Ratings/reviews avançado (média ponderada de stars, update permitido, anti-spam, helpful votes).
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

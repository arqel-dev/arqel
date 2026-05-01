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

**Por chegar:**

- **MKTPLC-002** — Plugin submission workflow (state machine draft → pending → published, validation no GitHub URL, screenshots upload, review queue).
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

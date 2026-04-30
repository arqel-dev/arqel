# SKILL.md — arqel/tenant

> Contexto canónico para AI agents a trabalhar no pacote `arqel/tenant`.

## Purpose

`arqel/tenant` fornece primitivas de multi-tenancy para o stack Arqel: um `TenantManager` singleton, contract `TenantResolver` com 5 implementações concretas, middleware de boot, trait Eloquent `BelongsToTenant` + global scope, regra `ScopedUnique`, adapters opt-in para `stancl/tenancy` e `spatie/laravel-multitenancy`, switching multi-tenant, scaffolders (registration / profile / billing), white-labeling theme value-object e feature gates.

A escolha é **não reinventar**: oferece uma abstração leve que cobre 80% dos casos (single-DB, tenant-per-row via global scope) e integra elegantemente com soluções multi-DB já maduras via adapters.

## Status

**Manager + events + middleware (TENANT-001..005):**

- `Arqel\Tenant\TenantManager` (final, singleton): `resolve(Request)` memoiza per-request, `set(?Model)`/`forget()` despacham `TenantResolved`/`TenantForgotten`, `runFor(Model, Closure)` faz swap+restore via try/finally, `current/currentOrFail/hasCurrent/id/identifier/resolved`. Construtor aceita `?TenantResolver` + `?Dispatcher` — apps sem tenancy ainda recebem manager funcional.
- Contract `Arqel\Tenant\Contracts\TenantResolver` (`resolve(Request): ?Model` + `identifierFor(Model): string`).
- `AbstractTenantResolver` — valida `is_subclass_of(Model::class)`, `identifierFor` default (coluna configurada → `getKey()`), helper `findByIdentifier`.
- 5 resolvers concretos (não-final, extensão explícita): `SubdomainResolver`, `PathResolver`, `HeaderResolver`, `SessionResolver`, `AuthUserResolver` (Jetstream-style `currentTeam`).
- `Arqel\Tenant\Middleware\ResolveTenantMiddleware` (alias `arqel.tenant`) com modes `required` (default — `TenantNotFoundException`) e `optional` (case-insensitive, trim-tolerant; valor desconhecido → `required`).
- `TenantNotFoundException::render()` retorna JSON 404, Inertia view `arqel::errors.tenant-not-found` ou Symfony 404 fallback.
- Events `TenantResolved` / `TenantForgotten` (final, readonly `Model $tenant`).

**Eloquent integration (TENANT-005..006):**

- `Scopes\TenantScope` (final, `implements Scope<Model>`) — global scope no-op gracioso quando não há tenant ou container não bind manager.
- `Concerns\BelongsToTenant` — registra `TenantScope` + auto-fill na criação. Foreign key resolve via `$tenantForeignKey` → `config('arqel.tenancy.foreign_key')` → `'tenant_id'`. Scopes `withoutTenant()` / `forTenant(Model|int|string)`.
- `Rules\ScopedUnique` — substituto tenant-aware da `unique` Laravel; aplica `where(<tenant_fk>, <id>)` quando há current tenant; fallback global quando não há.

**Adapters (TENANT-007..008):**

- `Integrations\StanclAdapter` — lê `Stancl\Tenancy\Tenancy::tenant`; honra `getTenantKey()` com fallback `getKey()`. Sem hard dep — gate via `class_exists`.
- `Integrations\SpatieAdapter` — chama `current()` static do Spatie; `modelClass` vazio → canonical `Spatie\Multitenancy\Models\Tenant`. Sem hard dep.

**Switching (TENANT-009):**

- Contract `Contracts\SupportsTenantSwitching`: `availableFor(Authenticatable)` / `canSwitchTo` / `switchTo`.
- `AbstractTenantResolver` implementa-o com defaults sensatos (relação `tenants`, coluna `current_tenant_id`); `AuthUserResolver` aceita `availableRelation` + `foreignKeyColumn`.
- `TenantManager` ganha `availableFor`/`canSwitchTo`/`switchTo` (delega; lança `LogicException` se resolver não suporta).
- Event `TenantSwitched` (final, readonly `?Model $from`, `Model $to`, `Authenticatable $user`).
- `Http\Controllers\TenantSwitcherController`: `POST /admin/tenants/{tenantId}/switch` (404/403/dispatch+redirect intended) + `GET /admin/tenants/available` (`{current, available[]}`). Rotas registadas via `$package->hasRoute('admin')`.

**Scaffolders (TENANT-010, 011, 013):**

- `arqel:tenant:scaffold-registration`, `arqel:tenant:scaffold-profile`, `arqel:tenant:scaffold-billing`. Cada um gera 3 stubs opt-in (Controller + routes snippet + Inertia page). Idempotentes: skip+exit 0 sem `--force`, overwrite com `--force`. Append único em `routes/web.php` guarded por marker. Honram `config('arqel.tenancy.model')`. Test hook `setBasePath(string)`.

**Theming (TENANT-012):**

- `Theming\TenantTheme` — readonly value-object com 5 props nullable (`primaryColor`, `logoUrl`, `fontFamily`, `secondaryColor`, `faviconUrl`). Factory `fromTenant(?Model)` lê atributos canônicos defensivamente; `toArray()` + `isEmpty()`.
- `Theming\TenantThemeResolver` (singleton) — `resolve(): TenantTheme` via `TenantManager::current()`.
- `Theming\CssVarsRenderer::renderInlineStyle(TenantTheme)` — emite `<style>:root { --color-primary: …; }`. Sanitização anti-injeção: drop silencioso de `<`, `>`, `"`; valores válidos passam por `htmlspecialchars`.
- **Wiring cross-package deferido**: consumers chamam `app(TenantThemeResolver::class)->resolve()->toArray()` no próprio `HandleInertiaRequests::share()`.

**Feature gates (TENANT-013):**

- `Concerns\HasFeatures` — `hasFeature/enableFeature/disableFeature/getFeatures` (defensive non-array, dedup; recomenda `$casts = ['features' => 'array']`).
- `Middleware\RequireTenantFeature` (alias `arqel.tenant.feature`) — uso `'arqel.tenant.feature:analytics'`. 404 sem tenant, 500 actionable se model não tem `hasFeature`, 402 JSON `{error: 'feature_not_available', feature, message}` se disabled.

**Coverage (TENANT-014):**

- 9 testes novos em `tests/Unit/Coverage/` cobrindo (1) `runFor` exception path + nested LIFO, (2) MODE_OPTIONAL flow + case-insensitive parsing, (3) `availableFor` defaults (non-Model auth, missing relation, Collection mixed-type, plain array property).
- **Total: 157 testes Pest passando.**

**Por chegar:**

- Cross-tenant data-leak E2E (precisa DB schema setup mais robusto que testbench resolve cleanly).
- Stancl integration tests reais (atualmente cobertos via mocks `class_exists`).
- Wiring cross-package do theme em `HandleArqelInertiaRequests::share()` + aplicação React em `ArqelProvider`.
- Cashier-Stripe wiring real no scaffolder de billing.

## Conventions

- `declare(strict_types=1)` obrigatório.
- Classes `final` por defeito; **resolvers em `src/Resolvers/` são `class` (não-final)** — extensibilidade explícita: apps reais customizam parsing de host/header (subdomain regex específica, `currentOrganization` em vez de `currentTeam`, etc.).
- **Sem hard dep** em `stancl/tenancy` ou `spatie/laravel-multitenancy`: ficam em `suggest`/integrations opt-in. Cada adapter tem o seu próprio gate `class_exists`.
- Multi-DB queries fora do scope nativo — pacote sempre integra via adapter, nunca implementa migration/seed isolation por conta.
- Foreign key resolve consistente: model property → `config('arqel.tenancy.foreign_key')` → `'tenant_id'`.
- Mode strings (middleware/feature) degradam silenciosamente para o default — typo não deve crashar Inertia render.

## Anti-patterns

- ❌ **Setar `current` direto via singleton em userland** — `TenantManager::set` deve passar pelo middleware/resolver chain (audit trail + lifecycle hooks).
- ❌ **Trait `BelongsToTenant` sem `tenant_id` na migration** — o trait assume coluna existente; sem ela o global scope quebra `where`.
- ❌ **Bypass do `TenantScope` com `withoutGlobalScope` no controller** — para "admin override" use `TenantManager::runFor(null, fn () => ...)` que persiste auditoria.
- ❌ **Renderizar CSS vars de tema sem `CssVarsRenderer`** — concatenar atributos do tenant direto no HTML abre vetor de injeção; o renderer faz sanitização defensiva.

## Examples

Configuração mínima (resolver + middleware + trait):

```php
// config/arqel.php
return [
    'tenancy' => [
        'resolver' => Arqel\Tenant\Resolvers\SubdomainResolver::class,
        'model' => App\Models\Tenant::class,
        'identifier_column' => 'slug',
        'foreign_key' => 'tenant_id',
    ],
];

// routes/web.php
Route::middleware(['web', 'auth', 'arqel.tenant'])->group(function () {
    Route::get('/admin', AdminController::class);
});
```

Model multi-tenant via `BelongsToTenant`:

```php
use Arqel\Tenant\Concerns\BelongsToTenant;

final class Project extends Model
{
    use BelongsToTenant;

    // Override opcional:
    // protected string $tenantForeignKey = 'organization_id';
}

// Auto-scoped por tenant atual:
Project::all();

// Cross-tenant (admin override) — preserva auditoria:
app(TenantManager::class)->runFor($otherTenant, fn () => Project::all());
```

Theme injection no `HandleInertiaRequests::share()`:

```php
use Arqel\Tenant\Theming\TenantThemeResolver;

public function share(Request $request): array
{
    $theme = app(TenantThemeResolver::class)->resolve();

    return [
        ...parent::share($request),
        'tenant' => [
            'theme' => $theme->isEmpty() ? null : $theme->toArray(),
        ],
    ];
}
```

## Cross-tenant leakage checklist

- [ ] Todo model com `tenant_id` usa o trait `BelongsToTenant` (global scope ativo).
- [ ] Migrations declaram `tenant_id` com FK + index composto onde fizer sentido (`['tenant_id', 'slug']`).
- [ ] Validation `unique` substituída por `Rules\ScopedUnique` quando o constraint é por-tenant.
- [ ] Background jobs hidratados via `TenantManager::runFor($job->tenant, fn () => ...)` antes de tocar Eloquent.
- [ ] Queries cross-tenant explícitas usam `forTenant($id)` ou `withoutTenant()` — nunca `withoutGlobalScope` sem comentário justificando.
- [ ] Switcher endpoints chamam `canSwitchTo` antes de `switchTo` (controller já faz, mas não bypass).
- [ ] Stubs de scaffolders persistem via `currentOrFail()->update(...)` — nunca aceitam `tenant_id` do request body.
- [ ] CSS vars de tema sempre por `CssVarsRenderer::renderInlineStyle()`.

## Related

- Tickets: [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §TENANT-001..015
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §Tenant
- Source: [`packages/tenant/src/`](./src/)
- Tests: [`packages/tenant/tests/`](./tests/)
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3
- Externos: [`stancl/tenancy`](https://tenancyforlaravel.com), [`spatie/laravel-multitenancy`](https://spatie.be/docs/laravel-multitenancy)

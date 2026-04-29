# SKILL.md — arqel/tenant

> Contexto canónico para AI agents.

## Purpose

`arqel/tenant` fornece primitivas de multi-tenancy para o stack Arqel: `TenantManager` (singleton), `TenantResolver` (interface + base implementations), trait Eloquent `BelongsToTenant`, global scope `TenantScope`, middleware `ResolveTenantMiddleware`, regra `ScopedUnique` e adapters opcionais para `stancl/tenancy` e `spatie/laravel-multitenancy`.

A escolha é **não reinventar**: oferecer uma abstração leve que cobre 80% dos casos (tenant-per-model via Eloquent global scope) e integra elegantemente com soluções multi-DB já maduras via adapters.

## Status

**Entregue (TENANT-001..003):**

- Esqueleto do pacote (`composer.json`, PSR-4 `Arqel\Tenant\` → `src/`, dep em `arqel/core` via path repo)
- `TenantServiceProvider` registado via auto-discovery (`extra.laravel.providers`)
- **`TenantManager` (final)** singleton com API completa: `resolve(Request)` (memoiza per-request), `set(?Model)` (override programático + dispatch `TenantResolved`/`TenantForgotten`), `forget()`, `runFor(Model, Closure)` (swap+restore mesmo em throw via try/finally), `current()`/`currentOrFail()` (lança `LogicException`)/`hasCurrent()`/`id()`/`identifier()` (delega ao resolver quando bound, fallback `(string) id()`)/`resolved()`. Construtor aceita `?TenantResolver` + `?Dispatcher` — apps sem tenancy ainda recebem manager funcional
- **Events** `Arqel\Tenant\Events\TenantResolved` + `TenantForgotten` (final, propriedade `readonly Model $tenant`)
- **`TenantServiceProvider::packageRegistered`** binda 2 singletons: `TenantResolver` (lê `arqel.tenancy.resolver` + `model` + `identifier_column` do config; valida `class_exists` + `is_subclass_of`; null se config ausente) e `TenantManager` (resolve resolver + Dispatcher do container)
- **`Arqel\Tenant\Contracts\TenantResolver`** — interface `resolve(Request): ?Model` + `identifierFor(Model): string`
- **`AbstractTenantResolver`** — base com validação `is_subclass_of(Model::class)` (lança `InvalidArgumentException`), `identifierFor` default (coluna configurada → fallback `getKey()`), `findByIdentifier(string)` protected helper
- **5 resolvers concretos** (não-final — extensão explícita): `SubdomainResolver` (centralDomain + heurística leftmost label, www rejeitado), `PathResolver` (primeiro segmento + `ignoreSegments` case-insensitive), `HeaderResolver` (X-Tenant-ID configurável), `SessionResolver` (`hasSession()` guard + coerção scalar→string), `AuthUserResolver` (`currentTeam` Jetstream-style: aceita `BelongsTo`/`Model`/property)
- Pest 3 + Orchestra Testbench setup com `defineEnvironment` SQLite in-memory
- **54 testes Pest passando** (era 4 → 32 → 54): 6 ServiceProvider Feature (boot, autoload, singleton, no-resolver default, configured resolver build, null on missing/invalid config) + 9 SubdomainResolver + 5 PathResolver + 4 HeaderResolver + 5 SessionResolver + 5 AuthUserResolver + **20 TenantManager** (init state, no-resolver resolve, resolver delegation+memoise, TenantResolved emit/non-emit, set override+events, set(null) clears+emits Forgotten, idempotent set, forget+events, no-op forget, runFor swap+restore, runFor restore on throw, currentOrFail throws/returns, id() int+string keyType, identifier() resolver delegate/fallback/empty)
- Estratégia DB-less: subclasses anônimas dos resolvers sobrescrevem `findByIdentifier` retornando fixture; tenant manager testado com fakeResolver inline + recordingDispatcher (Dispatcher anônimo que captura events em array)

**Por chegar (TENANT-004..015):**

- `ResolveTenantMiddleware` integrado com `HandleArqelInertiaRequests` — TENANT-004
- Trait `BelongsToTenant` + global scope `TenantScope` — TENANT-005
- `Rules\ScopedUnique` (validation rule respeitando tenant) — TENANT-006
- Adapter `stancl/tenancy` — TENANT-007
- Adapter `spatie/laravel-multitenancy` — TENANT-008
- Tenant switcher panel UI + flow de registro + profile + white-labeling — TENANT-009..012
- Integração opcional com `Laravel Cashier` para billing — TENANT-013
- Suite completa de testes + SKILL.md final — TENANT-014/015

## Conventions

- `declare(strict_types=1)` obrigatório
- Classes `final` por defeito; **resolvers em `src/Resolvers/` são `class` (não-final)** — extensibilidade explícita: apps reais frequentemente customizam parsing de host/header (ex: subdomain regex específica, fallback a `currentTeam` ou `currentOrganization`)
- **Sem hard dep** em `stancl/tenancy` ou `spatie/laravel-multitenancy` no `composer.json`: estão como `suggest`/integrations opt-in; cada adapter tem seu próprio gate de classe (`class_exists` antes de bind)
- Multi-DB queries fora de scope nativo — pacote sempre integra via adapter, nunca implementa migration/seed isolation por conta

## Anti-patterns

- ❌ **Setar `current` direto via singleton em userland** — `TenantManager::setCurrent` sempre passa pelo middleware/resolver chain (audit trail + lifecycle hooks)
- ❌ **Trait `BelongsToTenant` sem `tenant_id` na migration** — o trait assume coluna existente; sem ela o global scope quebra `where`
- ❌ **Bypass do TenantScope com `withoutGlobalScope` no controller** — para "admin override" use `TenantManager::for(null, fn () => ...)` que persiste auditoria

## Related

- Tickets: [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §TENANT-001..015
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §Tenant
- Source: [`packages/tenant/src/`](./src/)
- Tests: [`packages/tenant/tests/`](./tests/)
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3
- Externos: [`stancl/tenancy`](https://tenancyforlaravel.com), [`spatie/laravel-multitenancy`](https://spatie.be/docs/laravel-multitenancy)

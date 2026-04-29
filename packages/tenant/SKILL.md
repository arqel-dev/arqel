# SKILL.md — arqel/tenant

> Contexto canónico para AI agents.

## Purpose

`arqel/tenant` fornece primitivas de multi-tenancy para o stack Arqel: `TenantManager` (singleton), `TenantResolver` (interface + base implementations), trait Eloquent `BelongsToTenant`, global scope `TenantScope`, middleware `ResolveTenantMiddleware`, regra `ScopedUnique` e adapters opcionais para `stancl/tenancy` e `spatie/laravel-multitenancy`.

A escolha é **não reinventar**: oferecer uma abstração leve que cobre 80% dos casos (tenant-per-model via Eloquent global scope) e integra elegantemente com soluções multi-DB já maduras via adapters.

## Status

**Entregue (TENANT-001):**

- Esqueleto do pacote (`composer.json`, PSR-4 `Arqel\Tenant\` → `src/`, dep em `arqel/core` via path repo)
- `TenantServiceProvider` registado via auto-discovery (`extra.laravel.providers`)
- `TenantManager` (final) registado como singleton — stub com `current(): mixed` e `hasCurrent(): bool` retornando null/false até TENANT-003 entregar a implementação real
- Pest 3 + Orchestra Testbench setup com `defineEnvironment` SQLite in-memory
- 4 testes Feature smoke passando: boot OK, autoload do namespace, singleton binding, stub reporta no-current

**Por chegar (TENANT-002..015):**

- `TenantResolver` (interface + implementações `Subdomain`, `Path`, `Header`, `Session`) — TENANT-002
- `TenantManager::setCurrent`/`forget`/`forUser`/`for` (com closure scoping) — TENANT-003
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
- Classes `final` por defeito; abstratas com `__construct` final
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

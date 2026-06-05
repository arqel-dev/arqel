# Arqel Tenant Demo

Reference Laravel app demonstrating **multi-tenancy** with `arqel-dev/tenant`
using the **`AuthUserResolver`** pattern (the canonical "user belongs to many
tenants" setup, à la Jetstream/Spark).

## What it shows

- A `Project` model scoped per-tenant via the `BelongsToTenant` trait — every
  query is automatically filtered to the active tenant, so `ProjectResource`
  contains no tenant-aware code.
- A `<TenantSwitcher>` in the Topbar that lists the tenants the logged-in user
  can access and switches between them (`POST /admin/tenants/{id}/switch`).
- The active tenant persists in `users.current_tenant_id`; the resolver reads
  it from the authenticated user on every request.

## Wiring (see `config/arqel.php`)

Everything is config-driven — no custom resolver bind, Inertia share, or
bootstrap middleware append is needed.

- `arqel.tenancy.resolver` + `model` + `identifier_column` + `relation`
  (`currentTenant`) configure the `AuthUserResolver`; `TenantServiceProvider`
  builds it from these keys (the package default `relation` is `currentTeam`).
- `arqel.tenancy.switch_column` (`current_tenant_id`) is the USER column the
  resolver writes when switching tenants. It is distinct from
  `arqel.tenancy.foreign_key` (`tenant_id`), the `BelongsToTenant` FK on
  tenant-owned rows like `projects`.
- `arqel.middleware` (`['web', 'auth', 'arqel.tenant:optional']`) is applied
  to the admin resource routes by the core, so tenant resolution runs there.
- The Arqel core natively populates the `tenant` Inertia prop
  (`{current, available}`), which `<TenantSwitcher>` consumes.

## Run it

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed --force   # 2 tenants (Acme, Globex) × 5 projects + admin
pnpm install && pnpm --filter @arqel-dev/tenant-demo build
php artisan serve --port=8001
```

Login at `http://localhost:8001/admin/login` with `admin@arqel.test` / `password`.
The admin belongs to both tenants; switch between them from the Topbar and watch
`/admin/projects` re-scope.

## Tests

- `php artisan test` — feature test asserting the scoped list + shared tenant prop.
- `pnpm --filter @arqel-dev/tenant-demo test:e2e` — Playwright spec covering the
  full switching flow (login → Acme list → switch → Globex list).

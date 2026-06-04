# Tenant integration gaps — fix at the package level

> **Goal:** Make multi-tenancy with `arqel-dev/tenant` work zero-config, by closing the four integration gaps that `apps/tenant-demo` (v0.12.0) had to work around in app-land. After this, a consuming app needs only config + models, not provider hacks.

**Affected packages:** `arqel-dev/core`, `arqel-dev/tenant`. Reference consumer cleaned up: `apps/tenant-demo`.

**Non-goals:** No changes to the React `<TenantSwitcher>` component API. No new public PHP classes. No breaking changes to existing behavior — every fix is additive or opt-in.

---

## Context

Building `apps/tenant-demo` surfaced four gaps where the framework forced app-level workarounds. Each is a real friction point for any consumer wiring the `AuthUserResolver` pattern. This spec fixes all four at the package level and removes the corresponding workarounds from the reference app, with the existing E2E (`01-tenant-switching`) proving the integration still works end-to-end.

---

## Gap 1 — `AuthUserResolver` is not config-driven

**Today:** `Arqel\Tenant\TenantServiceProvider::buildConfiguredResolver()` instantiates the resolver with only `(modelClass, identifierColumn)`. `AuthUserResolver`'s other constructor params (`relation` default `currentTeam`, `availableRelation` default `tenants`, `foreignKeyColumn` default `current_tenant_id`) can't be set from config — an app whose "current tenant" relation isn't `currentTeam` must bind `TenantResolver` itself.

**Fix:** Make `buildConfiguredResolver` pass extra constructor arguments read from config, matched to the resolver's constructor signature via reflection (so it works for any `TenantResolver`, not just `AuthUserResolver`). New optional config keys under `arqel.tenancy`:

- `relation` → constructor param `relation`
- `available_relation` → constructor param `availableRelation`
- `foreign_key` → constructor param `foreignKeyColumn`

Resolution: read the constructor's parameter list via `ReflectionMethod`, then build the call's positional argument array by walking those parameters in order. For each parameter, use the matching config value when present (config key → param name: `model`→`modelClass`, `identifier_column`→`identifierColumn`, `relation`→`relation`, `available_relation`→`availableRelation`, `foreign_key`→`foreignKeyColumn`); otherwise fall back to the parameter's declared default. This handles the positional constructor correctly — e.g. to set `relation` (3rd param) it also supplies `identifierColumn` (2nd) from config or its default. Params with no matching config key and no default are left to fail loudly (a misconfigured custom resolver). Existing apps (no new keys) get identical behavior because every param falls back to its default.

**Interface:** `buildConfiguredResolver(Container $app): ?TenantResolver` — signature unchanged; only its internals and the set of honored config keys change.

---

## Gap 2 — panel middleware is not reliably applied to resource routes

**Today:** `Arqel\Core\ArqelServiceProvider::registerResourceRoutes()` runs in `packageBooted()`, BEFORE the app's `ServiceProvider::boot()` configures the panel. So `$panel?->getMiddleware()` returns the default `['web', HandleArqelInertiaRequests]`, and a `Panel::middleware([...])` call in the app is ignored. The tenant-demo worked around this by appending `arqel.tenant:optional` to the `web` group in `bootstrap/app.php`.

**Fix:** Add a config-driven middleware layer. Config is known before any provider boots, so it sidesteps the ordering problem. New optional config key `arqel.middleware` (array). Resolution order in `registerResourceRoutes`:

1. `$panel?->getMiddleware()` when the panel declares a non-default stack, else
2. `config('arqel.middleware')` when set (array of strings), else
3. the current fallback `['web', HandleArqelInertiaRequests::class]`.

`HandleArqelInertiaRequests::class` is still force-appended if absent (existing line). An app enables tenant resolution panel-wide by declaring `'middleware' => ['web', 'auth', 'arqel.tenant:optional']` in `config/arqel.php`.

**Interface:** `registerResourceRoutes(): void` — unchanged signature; adds one config read. The `Panel::middleware()` path is preserved for apps that hit the boot order correctly.

---

## Gap 3 — core reserves a `tenant` Inertia prop as a null stub

**Today:** `Arqel\Core\Http\Middleware\HandleArqelInertiaRequests::currentTenant()` returns `null` hardcoded ("Phase 1 stub"). Because `share()` merges `'tenant' => fn() => $this->currentTenant($request)` AFTER `parent::share()`, an app's `Inertia::share('tenant', ...)` is clobbered to null. The tenant-demo worked around this by sharing under a different key (`tenantContext`).

**Fix:** `currentTenant()` resolves `Arqel\Tenant\TenantManager` from the container via its string class-name (duck-typing — `arqel-dev/core` keeps NO hard dependency on `arqel-dev/tenant`). When bound, it emits `{ current, available }`:

- `current`: `serialise($manager->current())` — `{id, name, slug, logo}` or `null`
- `available`: the authenticated user's switchable set via `$manager->availableFor($user)`, each serialised, or `[]` when no user

When `TenantManager` is not bound (tenant package absent), returns `null` — identical to today. Serialisation reads attributes defensively (`getAttribute('name')` etc.) so it works with any tenant model.

This makes the canonical `tenant` prop work natively; the `<TenantSwitcher>` consumes `props.tenant` directly.

**Interface:** `currentTenant(Request): mixed` — unchanged signature; returns the structured array (or null) instead of always null. The class stays `final`.

---

## Gap 4 — `arqel:make-user` exits non-zero on duplicate email

**Today:** `Arqel\Core\Commands\MakeUserCommand` returns `FAILURE` when the email already exists. This breaks idempotent flows (E2E `setup.ts`, re-seeds) that call it via `execFileSync`.

**Fix:** Add a `--force` flag. With `--force`, the command does `updateOrCreate` on the email (updating name + password), reports the update, and returns `SUCCESS`. Without `--force`, behavior is unchanged (duplicate → error → `FAILURE`), so no existing usage breaks.

**Interface:** `MakeUserCommand` signature gains `{--force : Update the user if the email already exists}`.

---

## Reference app cleanup (`apps/tenant-demo`)

With the package fixes in place, remove the three workarounds:

1. **Resolver binding** → replace the explicit `TenantResolver` bind in `ArqelServiceProvider::register()` with config keys in `config/arqel.php`: `tenancy.relation = 'currentTenant'`.
2. **Tenant share** → remove the `Inertia::share('tenantContext', ...)` + `tenantContext()` helper from `ArqelServiceProvider`. The `<TenantSwitcherSlot>` in `app.tsx` reads `props.tenant` (the native key) again; `SharedTenantProps` uses `tenant?: TenantContextProps`. Update `TenantShareTest` to assert `tenant.current`.
3. **Middleware** → remove `appendToGroup('web', 'arqel.tenant:optional')` from `bootstrap/app.php`; declare `'middleware' => ['web', 'auth', 'arqel.tenant:optional']` in `config/arqel.php` instead.

Keep: the `currentTenant` relation on `User`, the `BelongsToTenant` on `Project`, the seeder, the E2E spec selectors. The `01-tenant-switching` E2E spec is the integration gate — it must still pass unchanged (it already reads `[data-testid="tenant-switcher-trigger"]`, agnostic to the prop key).

---

## Testing

- **tenant**: unit test that `buildConfiguredResolver` honors `arqel.tenancy.relation`/`available_relation`/`foreign_key` and that absent keys keep defaults.
- **core (middleware config)**: test that `registerResourceRoutes` applies `config('arqel.middleware')` to the resource route group.
- **core (tenant share)**: test that `HandleArqelInertiaRequests` emits `{current, available}` when a `TenantManager`-like binding is present, and `null` when absent (bind a fake to avoid a hard dep).
- **core (make-user)**: test `--force` updates an existing user and returns success; without `--force`, a duplicate still fails.
- **integration**: `apps/tenant-demo` feature test (`TenantShareTest`, updated to `tenant.*`) + the Playwright `01-tenant-switching` E2E, both green.

All existing package suites (core, tenant) must stay green — the changes are additive, so no existing test should need to change except `apps/tenant-demo/TenantShareTest` (prop key rename).

---

## Risk & rollout

- Gaps 1, 3, 4 are additive/opt-in — lowest risk.
- Gap 2 adds a config read in `registerResourceRoutes`; the `Panel::middleware()` and hardcoded-fallback paths are preserved, so apps not setting `arqel.middleware` are unaffected.
- Gap 3 is the only change to a shipped prop's value (was always null). Since it was null before, no consumer could have relied on a non-null value; the new structured payload is strictly more information.
- Ships in the next minor (v0.13.0). `arqel-dev/core` gains no new composer dependency.

# Multi-tenancy

> Package: [`arqel-dev/tenant`](../../packages/tenant/) · Tickets: TENANT-001..015

## Purpose

`arqel-dev/tenant` provides multi-tenancy primitives for the Arqel stack covering two main modes:

- **Single-DB scoped** (default) — all tenants share the same schema; isolation via Eloquent global scope `tenant_id`. 80% of cases. Zero operational overhead.
- **Multi-DB** (opt-in) — each tenant has its own database. Integrates with `stancl/tenancy` or `spatie/laravel-multitenancy` via adapters; doesn't reinvent isolated migrations/seeders.

The choice is **don't reinvent**: the package offers a `TenantManager` singleton + `TenantResolver` contract with 5 concrete implementations, and delegates multi-DB to already-mature solutions.

## Quick start

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

Each model with a `tenant_id` column adds the trait:

```php
use Arqel\Tenant\Concerns\BelongsToTenant;

final class Project extends Model
{
    use BelongsToTenant;
}

// Auto-scoped:
Project::all();
```

## Key concepts

### `TenantManager` (singleton)

Runtime source of truth. Main APIs:

- `resolve(Request)` — memoizes per-request; calls the configured resolver.
- `set(?Model)` / `forget()` — dispatches `TenantResolved` / `TenantForgotten` events.
- `runFor(Model, Closure)` — swap+restore via try/finally; used for jobs and admin override.
- `current` / `currentOrFail` / `hasCurrent` / `id` / `identifier`.

### `TenantResolver` contract

Defines how to discover the tenant from the `Request`. Five resolvers shipped:

| Resolver | Strategy |
|---|---|
| `SubdomainResolver` | `acme.app.com` → tenant `acme` |
| `PathResolver` | `app.com/acme/...` |
| `HeaderResolver` | `X-Tenant: acme` (APIs) |
| `SessionResolver` | choice persisted in session |
| `AuthUserResolver` | Jetstream-style `currentTeam` |

Resolvers in `src/Resolvers/` are intentionally `class` (non-final): apps customize host parsing, subdomain regex, or swap `currentTeam` for `currentOrganization`.

### Eloquent integration

- `BelongsToTenant` trait — registers the global `TenantScope` + auto-fills `tenant_id` on `creating`. Foreign key resolves by: `$tenantForeignKey` on the model → `config('arqel.tenancy.foreign_key')` → `'tenant_id'`.
- `withoutTenant()` / `forTenant($id)` — explicit escapes.
- `Rules\ScopedUnique` — tenant-aware substitute for Laravel's `unique` rule; applies `where(<tenant_fk>, <id>)` when there is a current tenant.

### Multi-DB adapters

No hard dep — gated via `class_exists`:

- `Integrations\StanclAdapter` — reads `Stancl\Tenancy\Tenancy::tenant`; honors `getTenantKey()` with `getKey()` fallback.
- `Integrations\SpatieAdapter` — calls Spatie's static `current()`; empty `modelClass` falls back to `Spatie\Multitenancy\Models\Tenant`.

### Tenant switching

Endpoint shipped:

- `POST /admin/tenants/{tenantId}/switch` — `TenantSwitcherController` calls `canSwitchTo` → `switchTo` → dispatches `TenantSwitched`.
- `GET /admin/tenants/available` — returns `{current, available[]}`.

Resolvers gain the `SupportsTenantSwitching` contract (`availableFor` / `canSwitchTo` / `switchTo`).

### Theming

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

`CssVarsRenderer::renderInlineStyle()` performs defensive sanitization (drops `<`, `>`, `"` + `htmlspecialchars`) — never concatenate tenant attributes directly into HTML.

## Examples

### Cross-tenant query (admin override)

```php
app(TenantManager::class)->runFor($otherTenant, fn () => Project::all());
```

### Hydrated job

```php
public function handle(): void
{
    app(TenantManager::class)->runFor($this->tenant, function () {
        // Everything here is scoped to the right tenant, even on the queue worker.
        Order::pending()->each->process();
    });
}
```

### Feature gate

```php
Route::middleware('arqel.tenant.feature:analytics')->group(function () {
    Route::get('/analytics', AnalyticsController::class);
});
```

A tenant without `analytics` in the `features` array → 402 `{error: 'feature_not_available', feature, message}`.

## Anti-patterns

- ❌ **Setting `current` directly via the singleton in userland** — use the middleware/resolver chain.
- ❌ **`BelongsToTenant` trait without `tenant_id` in the migration** — the global scope breaks `where`.
- ❌ **Bypassing `TenantScope` with `withoutGlobalScope` in the controller** — use `TenantManager::runFor(null, fn () => ...)` to preserve auditing.
- ❌ **Rendering theme CSS vars without `CssVarsRenderer`**.

## Cross-tenant leakage checklist

- [ ] Every model with `tenant_id` uses `BelongsToTenant`.
- [ ] Migrations declare `tenant_id` with FK + composite index where it makes sense.
- [ ] Validation `unique` replaced with `ScopedUnique` when the constraint is per-tenant.
- [ ] Background jobs hydrated via `runFor($job->tenant, ...)`.
- [ ] Switcher endpoints call `canSwitchTo` before `switchTo`.
- [ ] Theme CSS vars always go through `CssVarsRenderer::renderInlineStyle()`.

## Related

- [`packages/tenant/SKILL.md`](../../packages/tenant/SKILL.md) — canonical source
- [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §TENANT-001..015
- [`stancl/tenancy`](https://tenancyforlaravel.com), [`spatie/laravel-multitenancy`](https://spatie.be/docs/laravel-multitenancy)

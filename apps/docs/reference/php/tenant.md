# `arqel-dev/tenant` — API Reference

Namespace `Arqel\Tenant\`. Multi-tenancy primitives: a `TenantManager` singleton, a `TenantResolver` contract with five concrete implementations, boot middleware, an Eloquent trait plus global scope, a tenant-aware uniqueness rule, opt-in adapters for `stancl/tenancy` and `spatie/laravel-multitenancy`, tenant switching, white-label theming and feature gates.

The package covers the single-database, tenant-per-row case natively and defers multi-database isolation to mature third-party solutions through adapters. Neither `stancl/tenancy` nor `spatie/laravel-multitenancy` is a hard dependency — each adapter gates on `class_exists`.

## `Arqel\Tenant\TenantManager` (final, singleton)

Constructor takes `(?TenantResolver $resolver = null, ?Dispatcher $events = null)` — an app without tenancy still gets a working manager.

| Method | Type | Description |
|---|---|---|
| `resolve(Request $request)` | `?Model` | Resolves via the configured resolver, memoized per request |
| `set(?Model $tenant)` | `void` | Sets the current tenant; dispatches `TenantResolved` / `TenantForgotten` |
| `forget()` | `void` | Clears the current tenant and dispatches `TenantForgotten` |
| `runFor(Model $tenant, Closure $callback)` | `mixed` | Swap-and-restore via `try/finally`. Requires a `Model` — `null` is not accepted |
| `current()` | `?Model` | |
| `currentOrFail()` | `Model` | |
| `hasCurrent()` | `bool` | |
| `id()` | `int\|string\|null` | Primary key of the current tenant |
| `identifier()` | `string` | Identifier column value, via the resolver's `identifierFor()` |
| `resolved()` | `bool` | Whether resolution already ran for this request |
| `availableFor(Authenticatable $user)` | `array` | Delegates to the resolver; throws `LogicException` when it doesn't implement `SupportsTenantSwitching` |
| `canSwitchTo(Authenticatable $user, Model $tenant)` | `bool` | Same delegation |
| `switchTo(Authenticatable $user, Model $tenant)` | `void` | Same delegation; dispatches `TenantSwitched` |

## Contracts

### `Arqel\Tenant\Contracts\TenantResolver`

```php
public function resolve(Request $request): ?Model;
public function identifierFor(Model $tenant): string;
```

### `Arqel\Tenant\Contracts\SupportsTenantSwitching`

```php
public function availableFor(Authenticatable $user): array;
public function canSwitchTo(Authenticatable $user, Model $tenant): bool;
public function switchTo(Authenticatable $user, Model $tenant): void;
```

## Resolvers

`Arqel\Tenant\Resolvers\AbstractTenantResolver` (abstract) implements both contracts. Constructor `(string $modelClass, string $identifierColumn = 'id')` — throws `InvalidArgumentException` when `$modelClass` is not an Eloquent `Model` subclass. `identifierFor()` reads the configured column and falls back to `getKey()`.

Five concrete resolvers, all in `Arqel\Tenant\Resolvers\` and intentionally **not** `final` so apps can override host/header parsing:

| Class | Resolution source |
|---|---|
| `SubdomainResolver` | First host label |
| `PathResolver` | A URL path segment |
| `HeaderResolver` | A request header |
| `SessionResolver` | Session key. Overrides `switchTo()` to persist the identifier-column value into the **same** session key its `resolve()` reads |
| `AuthUserResolver` | Jetstream-style `currentTeam` on the authenticated user; accepts `availableRelation` + `foreignKeyColumn` |

## Middleware

### `Middleware\ResolveTenantMiddleware` (final, alias `arqel.tenant`)

`handle(Request $request, Closure $next, string $mode = self::MODE_REQUIRED)`. Two constants: `MODE_REQUIRED` (`'required'`) and `MODE_OPTIONAL` (`'optional'`). Mode parsing is case-insensitive and trim-tolerant; an unknown value degrades to `required`. In required mode an unresolved tenant throws `Exceptions\TenantNotFoundException`, whose `render()` returns a JSON 404, the Inertia view `arqel::errors.tenant-not-found`, or a Symfony 404 fallback.

### `Middleware\RequireTenantFeature` (final, alias `arqel.tenant.feature`)

`handle(Request $request, Closure $next, string $feature)` — used as `'arqel.tenant.feature:analytics'`. Returns 404 without a tenant, 500 with an actionable message when the tenant model has no `hasFeature`, and `402` JSON `{error: 'feature_not_available', feature, message}` when the feature is disabled.

## Eloquent integration

### `Concerns\BelongsToTenant` (trait)

Registers `Scopes\TenantScope` and auto-fills the foreign key on create. The key resolves as `$tenantForeignKey` property → `config('arqel.tenancy.foreign_key')` → `'tenant_id'`.

| Method | Type | Description |
|---|---|---|
| `getTenantKeyName()` / `getQualifiedTenantKeyName()` | `string` | Resolved foreign key |
| `tenant()` | `BelongsTo` | |
| `scopeWithoutTenant(Builder $query)` | `Builder` | Removes the global scope |
| `scopeForTenant(Builder $query, Model\|int\|string $tenant)` | `Builder` | Explicit cross-tenant query |

### `Scopes\TenantScope` (final, `implements Scope`)

`apply(Builder $builder, Model $model): void`. Graceful no-op when there is no current tenant or the manager is not bound in the container.

### `Rules\ScopedUnique` (final, `implements ValidationRule`)

Tenant-aware replacement for Laravel's `unique`. Constructor:

```php
new ScopedUnique(
    table: 'projects',
    column: 'slug',
    ignore: $project->id,        // default null
    ignoreColumn: 'id',          // default 'id'
    tenantForeignKey: null,      // default: resolved from config
    connection: null,
);
```

Applies `where(<tenant_fk>, <id>)` when a tenant is current, falling back to a global uniqueness check otherwise. Before applying the filter it checks `hasColumn` on the target table — if the tenant foreign key column doesn't exist the filter is **omitted** rather than producing an "Unknown column" error. Any failure inspecting the schema treats the column as present (keeping the tenant-scoped behavior).

### `Concerns\HasFeatures` (trait)

`hasFeature(string): bool`, `enableFeature(string): void`, `disableFeature(string): void`, `getFeatures(): array`. Defensive against a non-array `features` attribute and deduplicates; declare `$casts = ['features' => 'array']` on the tenant model.

## Theming

### `Theming\TenantTheme` (final, readonly value object)

Five nullable properties: `primaryColor`, `logoUrl`, `fontFamily`, `secondaryColor`, `faviconUrl`. Factory `TenantTheme::fromTenant(?Model $tenant): self` reads the canonical attributes defensively. `toArray(): array` and `isEmpty(): bool`.

### `Theming\TenantThemeResolver` (final, singleton)

`resolve(): TenantTheme` — builds the theme from `TenantManager::current()`.

### `Theming\CssVarsRenderer` (final)

`CssVarsRenderer::renderInlineStyle(TenantTheme $theme): string` emits `<style>:root { --color-primary: …; }`. Each slot is validated against an allowlist for its CSS context (colors accept hex / `rgb()` / `hsl()` / named colors; `font_family` accepts letters, digits, space, comma, hyphen and single quotes; URLs must be `http(s)` or root-relative and are emitted as an escaped `url('…')`). Values that fail the allowlist are **omitted** — never emitted unescaped — which neutralizes CSS injection payloads containing `}`.

## Adapters

Both live in `Arqel\Tenant\Integrations\` and implement `TenantResolver`; each is gated by `class_exists` so neither third-party package becomes a hard dependency.

| Class | Behavior |
|---|---|
| `StanclAdapter` (final) | Reads `Stancl\Tenancy\Tenancy::tenant` (binding name in the `TENANCY_BINDING` constant); honors `getTenantKey()` with a `getKey()` fallback |
| `SpatieAdapter` (final) | Calls Spatie's static `current()`; an empty `modelClass` falls back to the `SPATIE_TENANT_CLASS` constant (`Spatie\Multitenancy\Models\Tenant`) |

## Events

All `final` with readonly promoted properties:

| Event | Payload |
|---|---|
| `Events\TenantResolved` | `Model $tenant` |
| `Events\TenantForgotten` | `Model $tenant` |
| `Events\TenantSwitched` | `?Model $from`, `Model $to`, `Authenticatable $user` |

## HTTP

Registered under `web` + `auth` with the `admin/tenants` prefix:

| Verb | Route | Name | Action |
|---|---|---|---|
| POST | `admin/tenants/{tenantId}/switch` | `arqel.tenant.switch` | `TenantSwitcherController::switch` — 404/403, then dispatch + redirect |
| GET | `admin/tenants/available` | `arqel.tenant.available` | `TenantSwitcherController::list` — `{current, available[]}` |

## Artisan commands

Each scaffolder writes three opt-in stubs (controller + routes snippet + Inertia page), is idempotent (skips with exit 0 unless `--force`), and appends to `routes/web.php` exactly once behind a marker.

| Command | Function |
|---|---|
| `arqel:tenant:scaffold-registration {--force}` | Tenant sign-up flow |
| `arqel:tenant:scaffold-profile {--force}` | Tenant profile settings |
| `arqel:tenant:scaffold-billing {--force}` | Billing page skeleton |

## Example

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
Route::middleware(['web', 'auth', 'arqel.tenant'])->group(function (): void {
    Route::get('/admin', AdminController::class);
});
```

```php
use Arqel\Tenant\Concerns\BelongsToTenant;

final class Project extends Model
{
    use BelongsToTenant;

    // protected string $tenantForeignKey = 'organization_id';
}

Project::all();                     // auto-scoped to the current tenant
Project::withoutTenant()->get();    // explicit escape hatch
Project::forTenant($otherId)->get();

// Admin override that preserves the lifecycle hooks / audit trail:
app(TenantManager::class)->runFor($otherTenant, fn () => Project::all());
```

```php
use Arqel\Tenant\Theming\TenantThemeResolver;

public function share(Request $request): array
{
    $theme = app(TenantThemeResolver::class)->resolve();

    return [
        ...parent::share($request),
        'tenant' => ['theme' => $theme->isEmpty() ? null : $theme->toArray()],
    ];
}
```

## Related

- SKILL: [`packages/tenant/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/tenant/SKILL.md)
- Source: [`packages/tenant/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/tenant/src/)

# Tenant integration gap fixes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the four tenant integration gaps at the package level (core + tenant) so multi-tenancy works zero-config, then remove the corresponding workarounds from `apps/tenant-demo`.

**Architecture:** Four independent, additive/opt-in changes — a config-driven resolver (tenant), config-driven route middleware + a native `tenant` Inertia share + a `--force` flag on make-user (core) — followed by reverting the three app-land workarounds, with the existing Playwright `01-tenant-switching` E2E as the integration gate.

**Tech Stack:** PHP 8.3+, Laravel 12/13, Pest 3, Spatie laravel-package-tools, Inertia 3, Playwright.

**Spec:** [`docs/superpowers/specs/2026-06-04-tenant-integration-gaps-design.md`](../specs/2026-06-04-tenant-integration-gaps-design.md)

---

## Conventions

- **Commits:** Conventional Commits + DCO (`git commit --signoff`). Allowed scopes include `core`, `tenant`, `demo`, `docs`. Use `tenant` for the resolver change, `core` for the core changes, `demo` for the app cleanup.
- **Per-package tests:** `cd packages/<pkg> && vendor/bin/pest` (each package vendors its own pest). Never run the global `pest`.
- **Use `git -C /home/diogo/PhpstormProjects/arqel`** for git; never `git add -A`.
- **Branch:** `feat/tenant-integration-gaps` (already created).

---

## File Structure

- **Modify:** `packages/tenant/src/TenantServiceProvider.php` — `buildConfiguredResolver` reads extra config keys (Gap 1).
- **Modify:** `packages/tenant/tests/Feature/TenantServiceProviderTest.php` — resolver-config tests (Gap 1).
- **Modify:** `packages/core/src/ArqelServiceProvider.php` — `registerResourceRoutes` reads `arqel.middleware` (Gap 2).
- **Create:** `packages/core/tests/Feature/ResourceRouteMiddlewareTest.php` — middleware-config test (Gap 2).
- **Modify:** `packages/core/src/Http/Middleware/HandleArqelInertiaRequests.php` — `currentTenant` resolves TenantManager (Gap 3).
- **Create:** `packages/core/tests/Feature/TenantSharePropTest.php` — tenant-share test (Gap 3).
- **Modify:** `packages/core/src/Commands/MakeUserCommand.php` — `--force` flag (Gap 4).
- **Create:** `packages/core/tests/Feature/MakeUserCommandTest.php` — make-user tests (Gap 4).
- **Modify (cleanup):** `apps/tenant-demo/config/arqel.php`, `apps/tenant-demo/app/Providers/ArqelServiceProvider.php`, `apps/tenant-demo/app/Http/Middleware/HandleInertiaRequests.php`, `apps/tenant-demo/bootstrap/app.php`, `apps/tenant-demo/resources/js/app.tsx`, `apps/tenant-demo/tests/Feature/TenantShareTest.php`.

---

## Task 1: Gap 1 — config-driven resolver (tenant)

**Files:**
- Modify: `packages/tenant/src/TenantServiceProvider.php` (method `buildConfiguredResolver`, ~lines 86-109)
- Test: `packages/tenant/tests/Feature/TenantServiceProviderTest.php`

- [ ] **Step 1: Write the failing test**

Append to `packages/tenant/tests/Feature/TenantServiceProviderTest.php` (add `use Arqel\Tenant\Resolvers\AuthUserResolver;` and `use ReflectionClass;` to the top `use` block first):

```php
it('forwards arqel.tenancy.relation/available_relation/foreign_key to the resolver', function (): void {
    config([
        'arqel.tenancy.resolver' => AuthUserResolver::class,
        'arqel.tenancy.model' => Tenant::class,
        'arqel.tenancy.identifier_column' => 'slug',
        'arqel.tenancy.relation' => 'currentTenant',
        'arqel.tenancy.available_relation' => 'workspaces',
        'arqel.tenancy.foreign_key' => 'active_tenant_id',
    ]);

    app()->forgetInstance(TenantResolver::class);
    app()->forgetInstance(TenantManager::class);

    $resolver = app(TenantResolver::class);
    expect($resolver)->toBeInstanceOf(AuthUserResolver::class);

    $ref = new ReflectionClass($resolver);
    $relation = $ref->getProperty('relation');
    $relation->setAccessible(true);
    $available = $ref->getProperty('availableRelation');
    $available->setAccessible(true);
    $fk = $ref->getProperty('foreignKeyColumn');
    $fk->setAccessible(true);

    expect($relation->getValue($resolver))->toBe('currentTenant')
        ->and($available->getValue($resolver))->toBe('workspaces')
        ->and($fk->getValue($resolver))->toBe('active_tenant_id');
});

it('keeps resolver constructor defaults when the optional config keys are absent', function (): void {
    config([
        'arqel.tenancy.resolver' => AuthUserResolver::class,
        'arqel.tenancy.model' => Tenant::class,
        'arqel.tenancy.identifier_column' => 'id',
    ]);

    app()->forgetInstance(TenantResolver::class);
    app()->forgetInstance(TenantManager::class);

    $resolver = app(TenantResolver::class);
    $ref = new ReflectionClass($resolver);
    $relation = $ref->getProperty('relation');
    $relation->setAccessible(true);

    expect($relation->getValue($resolver))->toBe('currentTeam');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd packages/tenant && vendor/bin/pest tests/Feature/TenantServiceProviderTest.php`
Expected: FAIL — the first new test fails because `relation` is still `currentTeam` (config ignored).

- [ ] **Step 3: Rewrite `buildConfiguredResolver`**

In `packages/tenant/src/TenantServiceProvider.php`, replace the body of `buildConfiguredResolver` after the `class_exists`/`is_subclass_of` guard. Add `use ReflectionMethod;` and `use ReflectionNamedType;` to the top `use` block. Replace:

```php
        $identifierColumn = $config->get('arqel.tenancy.identifier_column', 'id');
        $args = is_string($identifierColumn) ? [$modelClass, $identifierColumn] : [$modelClass];

        /** @var TenantResolver $instance */
        $instance = new $resolverClass(...$args);

        return $instance;
```

with:

```php
        // Map config keys -> constructor parameter names. Build the
        // positional argument list by walking the constructor params in
        // order, using config when present, else the param's default.
        $configByParam = [
            'modelClass' => $modelClass,
            'identifierColumn' => $config->get('arqel.tenancy.identifier_column'),
            'relation' => $config->get('arqel.tenancy.relation'),
            'availableRelation' => $config->get('arqel.tenancy.available_relation'),
            'foreignKeyColumn' => $config->get('arqel.tenancy.foreign_key'),
        ];

        $constructor = (new ReflectionMethod($resolverClass, '__construct'));
        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            $value = $configByParam[$name] ?? null;

            if ($value !== null) {
                $args[] = $value;

                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();

                continue;
            }

            // No config + no default: stop and let the constructor decide
            // (e.g. a required custom param we cannot supply).
            break;
        }

        /** @var TenantResolver $instance */
        $instance = new $resolverClass(...$args);

        return $instance;
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd packages/tenant && vendor/bin/pest tests/Feature/TenantServiceProviderTest.php`
Expected: PASS (both new tests + the existing resolver tests).

- [ ] **Step 5: Run the full tenant suite + lint**

Run: `cd packages/tenant && vendor/bin/pest`
Expected: all green (157+ tests).
Run: `cd /home/diogo/PhpstormProjects/arqel && ./packages/versioning/vendor/bin/pint packages/tenant/src/TenantServiceProvider.php packages/tenant/tests/Feature/TenantServiceProviderTest.php`
Expected: fixed/passed.

- [ ] **Step 6: Commit**

```bash
git -C /home/diogo/PhpstormProjects/arqel add packages/tenant/src/TenantServiceProvider.php packages/tenant/tests/Feature/TenantServiceProviderTest.php
git -C /home/diogo/PhpstormProjects/arqel commit --signoff -m "feat(tenant): make the configured resolver config-driven

buildConfiguredResolver now forwards arqel.tenancy.relation,
available_relation and foreign_key to the resolver constructor, matched
positionally via reflection. Absent keys keep constructor defaults, so
existing apps are unaffected. Closes tenant integration gap 1."
```

---

## Task 2: Gap 2 — config-driven route middleware (core)

**Files:**
- Modify: `packages/core/src/ArqelServiceProvider.php` (method `registerResourceRoutes`, ~lines 294-309)
- Test: `packages/core/tests/Feature/ResourceRouteMiddlewareTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `packages/core/tests/Feature/ResourceRouteMiddlewareTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('applies config(arqel.middleware) to the resource route group', function (): void {
    config(['arqel.middleware' => ['web', 'auth', 'arqel-probe-mw']]);

    // Re-run route registration the way the provider does.
    $provider = app()->getProvider(\Arqel\Core\ArqelServiceProvider::class);
    $method = new ReflectionMethod($provider, 'registerResourceRoutes');
    $method->setAccessible(true);
    $method->invoke($provider);

    $route = collect(Route::getRoutes())->first(
        fn ($r) => $r->uri() === 'admin/{resource}' && in_array('GET', $r->methods(), true),
    );

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('arqel-probe-mw');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd packages/core && vendor/bin/pest tests/Feature/ResourceRouteMiddlewareTest.php`
Expected: FAIL — `arqel-probe-mw` is not in the route's middleware (config ignored today).

- [ ] **Step 3: Modify `registerResourceRoutes`**

In `packages/core/src/ArqelServiceProvider.php`, replace:

```php
        $middleware = $panel?->getMiddleware() ?? ['web', HandleArqelInertiaRequests::class];
```

with:

```php
        $panelMiddleware = $panel?->getMiddleware();
        $configMiddleware = config('arqel.middleware');

        if (is_array($panelMiddleware) && $panelMiddleware !== ['web']) {
            // Panel declared a non-default stack — honour it.
            $middleware = $panelMiddleware;
        } elseif (is_array($configMiddleware) && $configMiddleware !== []) {
            // Config-driven stack (known before boot, so it always applies).
            $middleware = array_values(array_filter($configMiddleware, 'is_string'));
        } else {
            $middleware = ['web', HandleArqelInertiaRequests::class];
        }
```

(Leave the following `if (! in_array(HandleArqelInertiaRequests::class, $middleware, true))` block and the `Route::prefix(...)` call unchanged.)

- [ ] **Step 4: Run test to verify it passes**

Run: `cd packages/core && vendor/bin/pest tests/Feature/ResourceRouteMiddlewareTest.php`
Expected: PASS.

- [ ] **Step 5: Run the full core suite + lint**

Run: `cd packages/core && vendor/bin/pest`
Expected: all green (286+ tests; the existing route tests still pass because the default-panel path is preserved).
Run: `cd /home/diogo/PhpstormProjects/arqel && ./packages/versioning/vendor/bin/pint packages/core/src/ArqelServiceProvider.php packages/core/tests/Feature/ResourceRouteMiddlewareTest.php`
Expected: fixed/passed.

- [ ] **Step 6: Commit**

```bash
git -C /home/diogo/PhpstormProjects/arqel add packages/core/src/ArqelServiceProvider.php packages/core/tests/Feature/ResourceRouteMiddlewareTest.php
git -C /home/diogo/PhpstormProjects/arqel commit --signoff -m "feat(core): honour config(arqel.middleware) on resource routes

registerResourceRoutes now applies an app-declared arqel.middleware
stack, which is known before any provider boots and so sidesteps the
panel boot-order problem. Panel::middleware() and the hardcoded fallback
are preserved. Closes tenant integration gap 2."
```

---

## Task 3: Gap 3 — native `tenant` Inertia share (core)

**Files:**
- Modify: `packages/core/src/Http/Middleware/HandleArqelInertiaRequests.php` (method `currentTenant`, ~line 239)
- Test: `packages/core/tests/Feature/TenantSharePropTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `packages/core/tests/Feature/TenantSharePropTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Core\Http\Middleware\HandleArqelInertiaRequests;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Fake tenant manager: duck-typed to the subset HandleArqelInertiaRequests
 * needs (current() + availableFor()). Bound under the real class name so
 * the middleware resolves it without core depending on arqel-dev/tenant.
 */
final class FakeTenantForShare extends Model
{
    protected $guarded = [];
}

it('emits null tenant when no TenantManager is bound', function (): void {
    $mw = new HandleArqelInertiaRequests;
    $ref = new ReflectionMethod($mw, 'currentTenant');
    $ref->setAccessible(true);

    expect($ref->invoke($mw, Request::create('/admin')))->toBeNull();
});

it('emits {current, available} when a TenantManager is bound', function (): void {
    $acme = (new FakeTenantForShare)->forceFill(['id' => 1, 'name' => 'Acme', 'slug' => 'acme']);
    $globex = (new FakeTenantForShare)->forceFill(['id' => 2, 'name' => 'Globex', 'slug' => 'globex']);

    $manager = new class($acme, [$acme, $globex])
    {
        public function __construct(private Model $current, private array $available) {}

        public function current(): ?Model
        {
            return $this->current;
        }

        public function availableFor($user): array
        {
            return $this->available;
        }
    };

    app()->instance('Arqel\\Tenant\\TenantManager', $manager);

    $request = Request::create('/admin');
    $request->setUserResolver(fn () => (object) ['id' => 99]);

    $mw = new HandleArqelInertiaRequests;
    $ref = new ReflectionMethod($mw, 'currentTenant');
    $ref->setAccessible(true);

    $payload = $ref->invoke($mw, $request);

    expect($payload)->toMatchArray([
        'current' => ['id' => 1, 'name' => 'Acme', 'slug' => 'acme', 'logo' => null],
    ])->and($payload['available'])->toHaveCount(2)
        ->and($payload['available'][1]['name'])->toBe('Globex');

    app()->forgetInstance('Arqel\\Tenant\\TenantManager');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd packages/core && vendor/bin/pest tests/Feature/TenantSharePropTest.php`
Expected: FAIL — the second test fails because `currentTenant` always returns null.

- [ ] **Step 3: Implement `currentTenant`**

In `packages/core/src/Http/Middleware/HandleArqelInertiaRequests.php`, replace:

```php
    private function currentTenant(Request $request): mixed
    {
        // Tenant scaffold for Phase 2 — stays null in Phase 1.
        return null;
    }
```

with:

```php
    /**
     * Tenant context for the shared `tenant` Inertia prop.
     *
     * Resolves `Arqel\Tenant\TenantManager` via the container by class
     * name (duck-typed) so `arqel-dev/core` keeps no hard dependency on
     * `arqel-dev/tenant`. Returns null when the package is not installed.
     *
     * @return array{current: array<string, mixed>|null, available: array<int, array<string, mixed>>}|null
     */
    private function currentTenant(Request $request): mixed
    {
        $managerClass = 'Arqel\\Tenant\\TenantManager';
        if (! app()->bound($managerClass)) {
            return null;
        }

        $manager = app($managerClass);
        if (! method_exists($manager, 'current') || ! method_exists($manager, 'availableFor')) {
            return null;
        }

        $user = $request->user();

        return [
            'current' => $this->serialiseTenant($manager->current()),
            'available' => $user !== null
                ? array_values(array_filter(array_map(
                    fn ($tenant): ?array => $this->serialiseTenant($tenant),
                    $manager->availableFor($user),
                )))
                : [],
        ];
    }

    /**
     * @return array{id: mixed, name: mixed, slug: mixed, logo: mixed}|null
     */
    private function serialiseTenant(mixed $tenant): ?array
    {
        if (! $tenant instanceof \Illuminate\Database\Eloquent\Model) {
            return null;
        }

        return [
            'id' => $tenant->getKey(),
            'name' => $tenant->getAttribute('name'),
            'slug' => $tenant->getAttribute('slug'),
            'logo' => $tenant->getAttribute('logo'),
        ];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd packages/core && vendor/bin/pest tests/Feature/TenantSharePropTest.php`
Expected: PASS (both tests).

- [ ] **Step 5: Run the full core suite + lint**

Run: `cd packages/core && vendor/bin/pest`
Expected: all green. The existing Inertia tests still pass because, with no TenantManager bound, `currentTenant` returns null as before.
Run: `cd /home/diogo/PhpstormProjects/arqel && ./packages/versioning/vendor/bin/pint packages/core/src/Http/Middleware/HandleArqelInertiaRequests.php packages/core/tests/Feature/TenantSharePropTest.php`
Expected: fixed/passed.

- [ ] **Step 6: Commit**

```bash
git -C /home/diogo/PhpstormProjects/arqel add packages/core/src/Http/Middleware/HandleArqelInertiaRequests.php packages/core/tests/Feature/TenantSharePropTest.php
git -C /home/diogo/PhpstormProjects/arqel commit --signoff -m "feat(core): populate the tenant Inertia prop from TenantManager

currentTenant() now resolves Arqel\\Tenant\\TenantManager from the
container (duck-typed, no hard dep) and emits {current, available} with
{id,name,slug,logo}; null when the tenant package is absent. Closes
tenant integration gap 3 — apps no longer need a tenantContext workaround."
```

---

## Task 4: Gap 4 — `arqel:make-user --force` (core)

**Files:**
- Modify: `packages/core/src/Commands/MakeUserCommand.php` (signature + `handle`)
- Test: `packages/core/tests/Feature/MakeUserCommandTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `packages/core/tests/Feature/MakeUserCommandTest.php`. The command resolves its model from `auth.guards.<guard>.provider` → `auth.providers.<provider>.model`, so the test points that at the existing `Arqel\Core\Tests\Fixtures\Models\User` fixture and creates its table:

```php
<?php

declare(strict_types=1);

use Arqel\Core\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config([
        'auth.defaults.guard' => 'web',
        'auth.guards.web.provider' => 'users',
        'auth.providers.users.model' => User::class,
    ]);

    Schema::create('users', function ($t): void {
        $t->id();
        $t->string('name')->nullable();
        $t->string('email')->unique();
        $t->string('password');
        $t->timestamp('email_verified_at')->nullable();
        $t->timestamps();
    });
});

it('creates a user on first run', function (): void {
    $this->artisan('arqel:make-user', [
        '--name' => 'Admin',
        '--email' => 'a@x.test',
        '--password' => 'secret',
    ])->assertExitCode(0);

    expect(\Illuminate\Support\Facades\DB::table('users')->count())->toBe(1);
});

it('fails on a duplicate email without --force', function (): void {
    \Illuminate\Support\Facades\DB::table('users')->insert([
        'email' => 'a@x.test', 'name' => 'Old', 'password' => 'x',
    ]);

    $this->artisan('arqel:make-user', [
        '--name' => 'Admin',
        '--email' => 'a@x.test',
        '--password' => 'secret',
    ])->assertExitCode(1);
});

it('updates an existing user with --force and exits 0', function (): void {
    \Illuminate\Support\Facades\DB::table('users')->insert([
        'email' => 'a@x.test', 'name' => 'Old', 'password' => 'x',
    ]);

    $this->artisan('arqel:make-user', [
        '--name' => 'New Name',
        '--email' => 'a@x.test',
        '--password' => 'secret',
        '--force' => true,
    ])->assertExitCode(0);

    $row = \Illuminate\Support\Facades\DB::table('users')->where('email', 'a@x.test')->first();
    expect($row->name)->toBe('New Name')
        ->and(\Illuminate\Support\Facades\DB::table('users')->count())->toBe(1);
});
```

(The `beforeEach` above wires `auth.providers.users.model` to the `User` fixture and creates the `users` table, so `resolveUserModel()` succeeds.)

- [ ] **Step 2: Run test to verify it fails**

Run: `cd packages/core && vendor/bin/pest tests/Feature/MakeUserCommandTest.php`
Expected: FAIL — the `--force` test fails because the flag does not exist (and the duplicate currently throws → FAILURE, which is fine for the no-force test).

- [ ] **Step 3: Add the `--force` flag to the signature**

In `packages/core/src/Commands/MakeUserCommand.php`, change `$signature` to add a `--force` line:

```php
    protected $signature = 'arqel:make-user
                            {--name= : Nome completo do usuário}
                            {--email= : E-mail (login)}
                            {--password= : Senha em texto puro (será passada por Hash::make)}
                            {--force : Update the user when the email already exists}';
```

- [ ] **Step 4: Branch the persistence on `--force`**

In `handle()`, replace the `try { ... } catch (Throwable $e) { ... }` persistence block:

```php
        try {
            /** @var Authenticatable&\Illuminate\Database\Eloquent\Model $user */
            $user = new $modelClass;
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($plainPassword),
                'email_verified_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $this->error('Failed to create user: '.$e->getMessage());

            return self::FAILURE;
        }
```

with:

```php
        try {
            if ($this->option('force')) {
                /** @var Authenticatable&\Illuminate\Database\Eloquent\Model $user */
                $user = $modelClass::query()->firstOrNew(['email' => $email]);
            } else {
                /** @var Authenticatable&\Illuminate\Database\Eloquent\Model $user */
                $user = new $modelClass;
                $user->setAttribute('email', $email);
            }

            // forceFill bypasses mass-assignment guards, matching the
            // original behaviour and working with any User model.
            $user->forceFill([
                'name' => $name,
                'password' => Hash::make($plainPassword),
                'email_verified_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $this->error('Failed to create user: '.$e->getMessage());

            return self::FAILURE;
        }
```

(`firstOrNew` returns the existing row when `--force` and the email exists, so `save()` is an UPDATE; otherwise a fresh model is INSERTed. Without `--force`, a fresh model with a duplicate email throws on `save()` → `FAILURE`, unchanged.)

- [ ] **Step 5: Run tests to verify they pass**

Run: `cd packages/core && vendor/bin/pest tests/Feature/MakeUserCommandTest.php`
Expected: PASS (all three).

- [ ] **Step 6: Run the full core suite + lint**

Run: `cd packages/core && vendor/bin/pest`
Expected: all green.
Run: `cd /home/diogo/PhpstormProjects/arqel && ./packages/versioning/vendor/bin/pint packages/core/src/Commands/MakeUserCommand.php packages/core/tests/Feature/MakeUserCommandTest.php`
Expected: fixed/passed.

- [ ] **Step 7: Commit**

```bash
git -C /home/diogo/PhpstormProjects/arqel add packages/core/src/Commands/MakeUserCommand.php packages/core/tests/Feature/MakeUserCommandTest.php
git -C /home/diogo/PhpstormProjects/arqel commit --signoff -m "feat(core): add --force to arqel:make-user (idempotent)

With --force the command updateOrCreates by email and exits 0; without
it the duplicate-email behaviour (error + FAILURE) is unchanged. Lets
E2E setup / re-seeds call it idempotently. Closes tenant integration gap 4."
```

---

## Task 5: Clean up `apps/tenant-demo` workarounds

**Files:**
- Modify: `apps/tenant-demo/config/arqel.php`
- Modify: `apps/tenant-demo/app/Providers/ArqelServiceProvider.php`
- Modify: `apps/tenant-demo/bootstrap/app.php`
- Modify: `apps/tenant-demo/resources/js/app.tsx`
- Modify: `apps/tenant-demo/tests/Feature/TenantShareTest.php`

- [ ] **Step 1: Move resolver config + middleware into config/arqel.php**

In `apps/tenant-demo/config/arqel.php`, update the `tenancy` block to add `relation` and add a top-level `middleware` key. The `tenancy` block becomes:

```php
    'tenancy' => [
        'enabled' => true,
        'resolver' => \Arqel\Tenant\Resolvers\AuthUserResolver::class,
        'model' => \App\Models\Tenant::class,
        'foreign_key' => 'tenant_id',
        'identifier_column' => 'slug',
        'relation' => 'currentTenant',
    ],

    // Panel-wide middleware. Resolved before boot, so it reliably applies
    // to the admin resource routes (unlike Panel::middleware()).
    'middleware' => ['web', 'auth', 'arqel.tenant:optional'],
```

- [ ] **Step 2: Strip the workarounds from ArqelServiceProvider**

In `apps/tenant-demo/app/Providers/ArqelServiceProvider.php`:
- Delete the entire `register()` method (the explicit `TenantResolver` bind — now driven by `tenancy.relation` config).
- Delete the `Inertia::share('tenantContext', ...)` line in `boot()` and the `tenantContext()` + `serialiseTenant()` private methods.
- Remove now-unused imports: `Arqel\Tenant\Contracts\TenantResolver`, `Arqel\Tenant\Resolvers\AuthUserResolver`, `Arqel\Tenant\TenantManager`, `App\Models\Tenant as TenantModel`, `Illuminate\Database\Eloquent\Model`, `Illuminate\Support\Facades\Auth`. Keep `Inertia` only if still used for `setRootView` (it is).

The class keeps: the middleware push, `Inertia::setRootView`, the panel declaration (`panel('admin')->path('admin')->brand()->login()->afterLoginRedirectTo('/admin/projects')->resources([ProjectResource::class])`), `setCurrent('admin')`, and the `Arqel\Auth\Routes::register` guard.

- [ ] **Step 3: Remove the bootstrap/app.php middleware append**

In `apps/tenant-demo/bootstrap/app.php`, change the `withMiddleware` closure back to empty (tenant resolution now comes from `config('arqel.middleware')`):

```php
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
```

- [ ] **Step 4: Point the React slot at the native `tenant` prop**

In `apps/tenant-demo/resources/js/app.tsx`, change the shared-props interface and the slot reader from `tenantContext` to `tenant`:

```tsx
interface SharedTenantProps {
  tenant?: TenantContextProps;
}

function TenantSwitcherSlot(): JSX.Element | null {
  const { props } = usePage<SharedTenantProps>();
  const tenant = props.tenant;
  if (!tenant?.current) {
    return null;
  }
  return <TenantSwitcher current={tenant.current} available={tenant.available} />;
}
```

- [ ] **Step 5: Update the backend feature test to the `tenant` key**

In `apps/tenant-demo/tests/Feature/TenantShareTest.php`, change the three `tenantContext.*` assertions to `tenant.*`:

```php
        $response->assertInertia(
            fn ($page) => $page
                ->has('tenant.current')
                ->where('tenant.current.name', 'Acme')
                ->has('tenant.available', 2),
        );
```

- [ ] **Step 6: Reinstall + migrate + run the app's PHP tests**

Run:
```bash
cd /home/diogo/PhpstormProjects/arqel/apps/tenant-demo
composer install --no-interaction --prefer-dist
cp .env.example .env || true
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed --force
php artisan test
```
Expected: 3 passed (TenantShareTest now asserts `tenant.*`; ExampleTest needs the built frontend — build in the next step if it 500s).

- [ ] **Step 7: Build the frontend + run the E2E gate**

Run:
```bash
cd /home/diogo/PhpstormProjects/arqel
pnpm install
pnpm --filter @arqel-dev/tenant-demo build
pkill -9 -f "artisan serve" 2>/dev/null || true
pnpm --filter @arqel-dev/tenant-demo test:e2e
```
Expected: `1 passed` — the switching flow still works end-to-end through the native `tenant` prop + config-driven middleware + config-driven resolver. If the webServer times out, a stray `artisan serve` is holding port 8001; re-run the `pkill` and retry.

- [ ] **Step 8: Commit**

```bash
git -C /home/diogo/PhpstormProjects/arqel add apps/tenant-demo/config/arqel.php apps/tenant-demo/app/Providers/ArqelServiceProvider.php apps/tenant-demo/bootstrap/app.php apps/tenant-demo/resources/js/app.tsx apps/tenant-demo/tests/Feature/TenantShareTest.php
git -C /home/diogo/PhpstormProjects/arqel commit --signoff -m "refactor(demo): drop tenant workarounds now fixed in core/tenant

apps/tenant-demo no longer needs the explicit resolver bind, the
tenantContext share, or the bootstrap/app.php middleware append: the
resolver relation comes from config (gap 1), the panel middleware from
config(arqel.middleware) (gap 2), and the native tenant Inertia prop
from core (gap 3). The 01-tenant-switching E2E still passes."
```

---

## Task 6: Final validation

**Files:** none (verification).

- [ ] **Step 1: Run the affected package suites**

Run:
```bash
cd /home/diogo/PhpstormProjects/arqel/packages/core && vendor/bin/pest
cd /home/diogo/PhpstormProjects/arqel/packages/tenant && vendor/bin/pest
```
Expected: both fully green.

- [ ] **Step 2: Run the tenant-demo E2E once more from a clean DB**

Run:
```bash
cd /home/diogo/PhpstormProjects/arqel/apps/tenant-demo && php artisan migrate:fresh --seed --force
cd /home/diogo/PhpstormProjects/arqel && pkill -9 -f "artisan serve" 2>/dev/null || true
pnpm --filter @arqel-dev/tenant-demo test:e2e
```
Expected: `1 passed`.

- [ ] **Step 3: Update the spec status (optional doc touch)**

No code change. Proceed to the finishing-a-development-branch skill to push + open a PR.

# `arqel-dev/auth` — API Reference

Namespace `Arqel\Auth\`. AbilityRegistry + PolicyDiscovery + middleware + traits.

## `Arqel\Auth\AbilityRegistry` (final)

Singleton. Coordinates global abilities between PHP and React (via the `auth.can` shared prop).

| Method | Type | Description |
|---|---|---|
| `registerGlobal(string)` | `void` | Adds an ability to the registry (resolved via `Gate::forUser`) |
| `registerGlobals(array<string>)` | `void` | Bulk with dedupe |
| `registerComputed(string, Closure)` | `void` | Closure invoked with `?Authenticatable` |
| `resolveForUser(?Authenticatable)` | `array<ability, bool>` | Per-request snapshot cached by `getAuthIdentifier()` |
| `clear()` | `void` | Clears registry and cache |

Globals are resolved via `Gate::forUser($user)->allows($ability)` (false for guests).

## `Arqel\Auth\PolicyDiscovery` (final)

| Method | Returns |
|---|---|
| `autoRegisterPoliciesFor(array<class-string>)` | `['registered' => array<modelClass, policyClass>, 'missing' => array<resourceClass>]` |

Heuristic: swaps `\Models\` for `\Policies\` in the namespace and adds the `Policy` suffix. Honors `Resource::$policy` override (checked with `property_exists` + `class_exists`). Resources that throw in `getModel()` or that don't exist are skipped gracefully. Emits `LoggerInterface::warning` for each missing Policy.

## `Arqel\Auth\ArqelGate` (final)

Facade integrated with `AbilityRegistry`.

| Method | Function |
|---|---|
| `register(string, Closure)` / `abilities(string ...)` | Registry proxies |
| `allows(string, $args = [])` / `denies(string, $args = [])` | Via `Gate::forUser(Auth::user())` |
| `snapshot()` | Alias for `resolveForUser(Auth::user())` |

## `Arqel\Auth\Concerns\AuthorizesRequests` trait

For Arqel controllers. 3 oracles:

```php
authorizeResource(class-string $resourceClass, string $action, ?Model $record = null): void
authorizeAction(object $action, ?Model $record = null): void
authorizeField(object $field, 'view'|'edit', ?Model $record = null): void
```

Each one aborts 403 if the predicate fails (silently allow when neither gate nor policy is registered — Resource Policies are user-owned).

## `Arqel\Auth\Http\Middleware\EnsureUserCanAccessPanel` (final)

Panel-level gate with a configurable ability. `DEFAULT_ABILITY = 'viewAdminPanel'`.

| Behavior | |
|---|---|
| Guest | aborts 401 |
| User with `Gate::denies` | aborts 403 |
| Ability not registered with Gate | allow-through (fresh install) |
| Custom ability via parameter | `->middleware(EnsureUserCanAccessPanel::class.':manageSettings')` |

## HTTP — Auth flow

### `Arqel\Auth\Http\Controllers\LoginController` (final)

Renders the login page via Inertia. Passes as props:

```php
[
  'loginUrl' => route('arqel.auth.login'),
  'registerUrl' => route('arqel.auth.register'),     // null if registration disabled
  'forgotPasswordUrl' => route('arqel.auth.password.request'),
  'canResetPassword' => bool,
]
```

The Inertia pages live in the `@arqel-dev/auth` package and are resolved by the `pages` registry of `createArqelApp`:

| Page name | Component |
|---|---|
| `arqel-dev/auth/Login` | Login form + hero (`/arqel/login-hero.svg`) |
| `arqel-dev/auth/Register` | Registration form |
| `arqel-dev/auth/ForgotPassword` | Reset request |
| `arqel-dev/auth/ResetPassword` | New password submission |
| `arqel-dev/auth/VerifyEmail` | Notice + resend |

Companions: `RegisterController`, `ForgotPasswordController`, `ResetPasswordController`, `VerifyEmailController`. They all follow the same pattern of passing URLs as Inertia props to keep React from hitting hardcoded routes.

## Global helper

```php
arqel_can(string $ability, mixed $arguments = null): bool
```

Lookup order: `AbilityRegistry` snapshot (when bound) first, fallback to `Gate::forUser($user)->allows`. Returns `false` for guests. Autoloaded via composer `files`.

## Related

- SKILL: [`packages/auth/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/auth/SKILL.md)
- Concepts: [`/guide/auth`](/guide/auth)
- ADR-017: Authorization is UX-only on the client
- Next: [`arqel-dev/nav`](/reference/php/nav)

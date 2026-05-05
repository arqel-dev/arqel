# `arqel-dev/auth` — Referencia de API

Namespace `Arqel\Auth\`. AbilityRegistry + PolicyDiscovery + middleware + traits.

## `Arqel\Auth\AbilityRegistry` (final)

Singleton. Coordina abilities globales entre PHP y React (vía el prop compartido `auth.can`).

| Método | Tipo | Descripción |
|---|---|---|
| `registerGlobal(string)` | `void` | Añade una ability al registry (resuelta vía `Gate::forUser`) |
| `registerGlobals(array<string>)` | `void` | En lote con dedupe |
| `registerComputed(string, Closure)` | `void` | Closure invocada con `?Authenticatable` |
| `resolveForUser(?Authenticatable)` | `array<ability, bool>` | Snapshot por request cacheado por `getAuthIdentifier()` |
| `clear()` | `void` | Limpia registry y caché |

Las globals se resuelven vía `Gate::forUser($user)->allows($ability)` (false para guests).

## `Arqel\Auth\PolicyDiscovery` (final)

| Método | Retorna |
|---|---|
| `autoRegisterPoliciesFor(array<class-string>)` | `['registered' => array<modelClass, policyClass>, 'missing' => array<resourceClass>]` |

Heurística: intercambia `\Models\` por `\Policies\` en el namespace y añade el sufijo `Policy`. Respeta el override `Resource::$policy` (verificado con `property_exists` + `class_exists`). Los Resources que lanzan en `getModel()` o que no existen se omiten de forma elegante. Emite `LoggerInterface::warning` por cada Policy faltante.

## `Arqel\Auth\ArqelGate` (final)

Facade integrada con `AbilityRegistry`.

| Método | Función |
|---|---|
| `register(string, Closure)` / `abilities(string ...)` | Proxies hacia el registry |
| `allows(string, $args = [])` / `denies(string, $args = [])` | Vía `Gate::forUser(Auth::user())` |
| `snapshot()` | Alias de `resolveForUser(Auth::user())` |

## Trait `Arqel\Auth\Concerns\AuthorizesRequests`

Para controllers de Arqel. 3 oráculos:

```php
authorizeResource(class-string $resourceClass, string $action, ?Model $record = null): void
authorizeAction(object $action, ?Model $record = null): void
authorizeField(object $field, 'view'|'edit', ?Model $record = null): void
```

Cada uno aborta con 403 si el predicado falla (permite silenciosamente cuando no hay gate ni policy registrada — las Policies de Resource pertenecen al usuario).

## `Arqel\Auth\Http\Middleware\EnsureUserCanAccessPanel` (final)

Gate a nivel de Panel con ability configurable. `DEFAULT_ABILITY = 'viewAdminPanel'`.

| Comportamiento | |
|---|---|
| Guest | aborta 401 |
| Usuario con `Gate::denies` | aborta 403 |
| Ability no registrada con Gate | allow-through (instalación recién creada) |
| Ability personalizada vía parámetro | `->middleware(EnsureUserCanAccessPanel::class.':manageSettings')` |

## HTTP — Flujo de Auth

### `Arqel\Auth\Http\Controllers\LoginController` (final)

Renderiza la página de login vía Inertia. Pasa como props:

```php
[
  'loginUrl' => route('arqel.auth.login'),
  'registerUrl' => route('arqel.auth.register'),     // null if registration disabled
  'forgotPasswordUrl' => route('arqel.auth.password.request'),
  'canResetPassword' => bool,
]
```

Las páginas Inertia viven en el paquete `@arqel-dev/auth` y se resuelven vía el registry `pages` de `createArqelApp`:

| Nombre de página | Componente |
|---|---|
| `arqel-dev/auth/Login` | Formulario de login + hero (`/arqel/login-hero.svg`) |
| `arqel-dev/auth/Register` | Formulario de registro |
| `arqel-dev/auth/ForgotPassword` | Solicitud de reset |
| `arqel-dev/auth/ResetPassword` | Envío de nueva contraseña |
| `arqel-dev/auth/VerifyEmail` | Aviso + reenvío |

Acompañantes: `RegisterController`, `ForgotPasswordController`, `ResetPasswordController`, `VerifyEmailController`. Todos siguen el mismo patrón de pasar URLs como props de Inertia para evitar que React pegue contra rutas hardcodeadas.

## Helper global

```php
arqel_can(string $ability, mixed $arguments = null): bool
```

Orden de búsqueda: snapshot de `AbilityRegistry` (cuando está vinculado) primero, fallback a `Gate::forUser($user)->allows`. Retorna `false` para guests. Autoload vía composer `files`.

## Relacionado

- SKILL: [`packages/auth/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/auth/SKILL.md)
- Conceptos: [`/es/guide/auth`](/es/guide/auth)
- ADR-017: La autorización es solo UX en el cliente
- Siguiente: [`arqel-dev/nav`](/es/reference/php/nav)

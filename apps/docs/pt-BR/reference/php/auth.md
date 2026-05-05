# `arqel-dev/auth` — API Reference

Namespace `Arqel\Auth\`. AbilityRegistry + PolicyDiscovery + middleware + traits.

## `Arqel\Auth\AbilityRegistry` (final)

Singleton. Coordena abilities globais entre PHP e React (via `auth.can` shared prop).

| Método | Tipo | Descrição |
|---|---|---|
| `registerGlobal(string)` | `void` | Adiciona uma ability ao registry (resolve via `Gate::forUser`) |
| `registerGlobals(array<string>)` | `void` | Bulk com dedupe |
| `registerComputed(string, Closure)` | `void` | Closure invocada com `?Authenticatable` |
| `resolveForUser(?Authenticatable)` | `array<ability, bool>` | Snapshot per-request cacheado por `getAuthIdentifier()` |
| `clear()` | `void` | Limpa registry e cache |

Globals são resolvidas via `Gate::forUser($user)->allows($ability)` (false para guests).

## `Arqel\Auth\PolicyDiscovery` (final)

| Método | Retorna |
|---|---|
| `autoRegisterPoliciesFor(array<class-string>)` | `['registered' => array<modelClass, policyClass>, 'missing' => array<resourceClass>]` |

Heurística: troca `\Models\` por `\Policies\` no namespace e adiciona sufixo `Policy`. Honra `Resource::$policy` override (verificado com `property_exists` + `class_exists`). Resources que lançam exceção em `getModel()` ou que não existem são skippados graciosamente. Emite `LoggerInterface::warning` por Policy ausente.

## `Arqel\Auth\ArqelGate` (final)

Facade integrada ao `AbilityRegistry`.

| Método | Função |
|---|---|
| `register(string, Closure)` / `abilities(string ...)` | Proxies do registry |
| `allows(string, $args = [])` / `denies(string, $args = [])` | Via `Gate::forUser(Auth::user())` |
| `snapshot()` | Alias para `resolveForUser(Auth::user())` |

## `Arqel\Auth\Concerns\AuthorizesRequests` trait

Para Arqel controllers. 3 oracles:

```php
authorizeResource(class-string $resourceClass, string $action, ?Model $record = null): void
authorizeAction(object $action, ?Model $record = null): void
authorizeField(object $field, 'view'|'edit', ?Model $record = null): void
```

Cada um aborta 403 se o predicado falhar (silently allow quando nem gate nem policy registada — Resource Policies são user-owned).

## `Arqel\Auth\Http\Middleware\EnsureUserCanAccessPanel` (final)

Gate de panel-level com ability configurável. `DEFAULT_ABILITY = 'viewAdminPanel'`.

| Comportamento | |
|---|---|
| Guest | aborta 401 |
| User com `Gate::denies` | aborta 403 |
| Ability não registada com Gate | allow-through (fresh install) |
| Custom ability via parâmetro | `->middleware(EnsureUserCanAccessPanel::class.':manageSettings')` |

## HTTP — Auth flow

### `Arqel\Auth\Http\Controllers\LoginController` (final)

Renderiza a página de login via Inertia. Passa como props:

```php
[
  'loginUrl' => route('arqel.auth.login'),
  'registerUrl' => route('arqel.auth.register'),     // null se registo desligado
  'forgotPasswordUrl' => route('arqel.auth.password.request'),
  'canResetPassword' => bool,
]
```

As páginas Inertia ficam no pacote `@arqel-dev/auth` e são resolvidas pelo `pages` registry de `createArqelApp`:

| Page name | Componente |
|---|---|
| `arqel-dev/auth/Login` | Form de login + hero (`/arqel/login-hero.svg`) |
| `arqel-dev/auth/Register` | Form de registo |
| `arqel-dev/auth/ForgotPassword` | Pedido de reset |
| `arqel-dev/auth/ResetPassword` | Submissão de novo password |
| `arqel-dev/auth/VerifyEmail` | Aviso + reenvio |

Companions: `RegisterController`, `ForgotPasswordController`, `ResetPasswordController`, `VerifyEmailController`. Todos seguem o mesmo padrão de passar URLs como Inertia props para evitar que o React bata em rotas hardcoded.

## Helper global

```php
arqel_can(string $ability, mixed $arguments = null): bool
```

Lookup order: `AbilityRegistry` snapshot (quando bound) primeiro, fallback para `Gate::forUser($user)->allows`. Retorna `false` para guests. Autoloaded via composer `files`.

## Related

- SKILL: [`packages/auth/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/auth/SKILL.md)
- Conceitos: [`/pt-BR/guide/auth`](/pt-BR/guide/auth)
- ADR-017: Authorization is UX-only on the client
- Próximo: [`arqel-dev/nav`](/pt-BR/reference/php/nav)

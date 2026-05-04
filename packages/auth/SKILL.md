# SKILL.md — arqel-dev/auth

> Contexto canónico para AI agents. Estrutura conforme `PLANNING/04-repo-structure.md` §11.

## Purpose

`arqel-dev/auth` é a fina camada de **authorization** (não authentication) que envolve Laravel Policies + Gate com conveniences específicas para Arqel:

- **`PolicyDiscovery`** — verifica/auto-registra Policies para Resources, com warning quando ausentes e suporte a override via `$policy` estático
- **`AbilityRegistry`** — catálogo de abilities globais (resolvidas via Gate) + computed (closures arbitrárias) que são serializadas em shared props (`auth.can.*`) para o lado React
- **`ArqelGate`** — facade conveniente sobre Laravel Gate integrada com o `AbilityRegistry`

User escreve as Policies (Laravel-native). Arqel apenas verifica existência, auto-registra com `Gate::policy(...)` e resolve abilities globais por user.

> **Authentication (login/registro/forgot-password) não está incluída neste pacote.** Decisão de design: Arqel hoje delega ao starter kit Laravel (Breeze/Jetstream/Fortify) — o `arqel new` CLI instala Breeze + React + Inertia por default. Para apps que rodaram só `composer require arqel-dev/arqel`, é necessário instalar manualmente um starter kit. Ver `apps/docs/guide/authentication.md`. _Tickets AUTH-006/007/008 (TBD) preveem shipar páginas Inertia-React opt-in dentro deste pacote, equivalente ao que Filament/Nova oferecem out-of-the-box._

## Status

**Entregue (AUTH-001..004):**

- `Arqel\Auth\AuthServiceProvider` — auto-discovery, regista `AbilityRegistry` e `PolicyDiscovery` como singletons
- `Arqel\Auth\AbilityRegistry` — `registerGlobal/Globals/Computed`, `resolveForUser` com cache per-request, `clear`
- `Arqel\Auth\PolicyDiscovery` — `autoRegisterPoliciesFor(array $resources)` retorna `{registered, missing}`. Heurística `\Models\` → `\Policies\`. Honra `Resource::$policy` override
- `Arqel\Auth\ArqelGate` — facade com `register/abilities/allows/denies/snapshot` integrada ao `AbilityRegistry`
- `Arqel\Auth\Concerns\AuthorizesRequests` trait com 3 oracles: `authorizeResource(class, action, ?record)`, `authorizeAction(action, ?record)`, `authorizeField(field, operation, ?record)`. Aborta 403 quando o gate denies; silently allow quando não há policy registada
- `Arqel\Auth\Http\Middleware\EnsureUserCanAccessPanel` — middleware com ability configurável (default `viewAdminPanel`). Aborta 401 para guests, 403 quando o gate denies, allow-through quando a ability não está registada
- `arqel_can(string, mixed)` global helper: `AbilityRegistry` snapshot first, Gate fallback. Retorna false para guests
- 28 testes Pest passando

**Entregue (AUTH-008):**

- `Arqel\Auth\Http\Controllers\ForgotPasswordController` — GET renderiza Inertia `arqel-dev/auth/ForgotPassword`; POST valida e-mail, dispara `Password::sendResetLink`, retorna flash `status` genérico (não revela se e-mail existe). Rate-limit 3/email+IP/hora
- `Arqel\Auth\Http\Controllers\ResetPasswordController` — GET renderiza `arqel-dev/auth/ResetPassword` com `{token, email}`; POST valida via `ResetPasswordRequest` e processa `Password::reset`. Sucesso redireciona para `Panel::getLoginUrl()`
- `Arqel\Auth\Http\Requests\ResetPasswordRequest` — rules `token+email+password(min:8 confirmed)+password_confirmation`; rate-limit 3/email+IP/hora
- `Routes::registerPasswordReset(?Panel)` — registra `password.request`, `password.email`, `password.reset`, `password.update` (idempotente; pula se host já tem `password.request`/`password.reset`)
- `Panel::passwordReset()/passwordResetEnabled()/passwordResetExpirationMinutes()/forgotPasswordUrl()` — fluent API opt-in. `passwordResetExpirationMinutes` ajusta `auth.passwords.users.expire` em runtime
- Pacote npm `@arqel-dev/auth` ganha `<ForgotPasswordPage />` e `<ResetPasswordPage />`

Exemplo:

```php
$panel
    ->login()
    ->passwordReset()
    ->passwordResetExpirationMinutes(120);
```

**Entregue (AUTH-006):**

- `Arqel\Auth\Http\Controllers\LoginController` — GET renderiza Inertia `arqel-dev/auth/Login`; POST autentica via `LoginRequest`, regenera sessão e redireciona para `Panel::getAfterLoginUrl()`
- `Arqel\Auth\Http\Controllers\LogoutController` — invalida sessão, rotaciona CSRF, redireciona para `Panel::getLoginUrl()`
- `Arqel\Auth\Http\Requests\LoginRequest` — rate-limit Laravel-native (5/min por email+IP), dispara `Lockout` event
- `Arqel\Auth\Routes::register(?Panel)` — registo idempotente; pula quando o host já tem rota `login` (Breeze/Jetstream/Fortify)
- `Panel::login()/loginUrl()/afterLoginRedirectTo()/registration()/withoutDefaultAuth()/loginEnabled()/registrationEnabled()` — fluent API opt-in
- Pacote npm `@arqel-dev/auth` com componente `<LoginPage />` Inertia-React

**Entregue (AUTH-007):**

- `Arqel\Auth\Http\Controllers\RegisterController` — GET renderiza Inertia `arqel-dev/auth/Register`; POST cria User via `config('auth.providers.users.model')`, dispara `Registered` event, faz auto-login e redireciona
- `Arqel\Auth\Http\Requests\RegisterRequest` — rules name/email/password com `confirmed`, rate-limit 3 registros/IP/hora
- `Arqel\Auth\Http\Controllers\EmailVerificationController` — `notice` (Inertia notice page), `verify` (signed URL handler que dispara `Verified`), `resend` (reenvio com flash status)
- `Arqel\Auth\Routes::registerRegistration()` e `registerEmailVerification()` — registos idempotentes, opt-in via `Panel::registration()` e `Panel::emailVerification()`
- `Panel::emailVerification()/emailVerificationEnabled()/registrationFields()/getRegistrationFields()` — fluent API opt-in
- Componentes npm `<RegisterPage />` e `<VerifyEmailNoticePage />` em `@arqel-dev/auth`
- Reservou `email/` no `routes/arqel.php` `$reservedSlugs` para não colidir com `{resource}` polymórfico

Exemplo de uso:

```php
$panel = Panel::configure()
    ->login()
    ->registration()
    ->emailVerification();
```

**Por chegar:**

- Integração com `arqel-dev/core` `ArqelServiceProvider::packageBooted` para chamar `PolicyDiscovery::autoRegisterPoliciesFor(ResourceRegistry::all())` automaticamente (AUTH-005 wrap-up)

## Key Contracts

### `AbilityRegistry`

```php
$registry = app(AbilityRegistry::class);

$registry
    ->registerGlobal('viewAdminPanel')             // resolvido via Gate::allows
    ->registerGlobals(['manageSettings', 'exportData'])
    ->registerComputed('isPremium', fn ($user) => $user?->subscription?->isPremium());

// Inertia middleware (CORE-007) chama:
$can = $registry->resolveForUser(auth()->user());
// → ['viewAdminPanel' => true, 'manageSettings' => false, 'exportData' => true, 'isPremium' => true]
```

Cache per-request por `getAuthIdentifier()` (`'guest'` para null) — múltiplas chamadas para o mesmo user dentro do mesmo request não re-executam Gate/closures.

### `PolicyDiscovery`

```php
$discovery = app(PolicyDiscovery::class);

$result = $discovery->autoRegisterPoliciesFor([
    UserResource::class,
    PostResource::class,
]);

// $result['registered']: ['App\Models\User' => 'App\Policies\UserPolicy', ...]
// $result['missing']: [class-string, ...] — emits log warning per resource
```

Heurística:
1. Se Resource tem `public static ?string $policy = X::class`, usa X (se existir).
2. Caso contrário, troca `\Models\` por `\Policies\` no namespace do model e adiciona sufixo `Policy`.
3. Se resultado não existe, adiciona ao `missing[]` e emite log warning.

Resources que lançam exceção ao chamar `getModel()` (i.e. `$model` não definido) são skippados graciosamente.

### `ArqelGate`

```php
$gate = app(ArqelGate::class);

$gate->register('isPremium', fn ($user) => /* ... */);
$gate->abilities('viewAdminPanel', 'manageSettings');

$gate->allows('viewAdminPanel');                   // current user via Auth::user()
$gate->denies('viewAdminPanel', $blogPost);        // com argumento
$can = $gate->snapshot();                          // === $registry->resolveForUser(Auth::user())
```

## Conventions

- `declare(strict_types=1)` obrigatório
- User escreve as Policies (Laravel `App\Policies\FooPolicy`); Arqel apenas verifica e regista
- Global abilities são para flags **panel-level** (e.g. "user pode entrar no admin", "user pode exportar"); per-record authorization sempre vai por Policies
- Computed abilities são para checks arbitrários (e.g. "user tem subscription paid") — closures recebem `?Authenticatable $user` e retornam bool
- Cache de abilities é per-request — não persiste entre requests; isto é deliberado para refletir mudanças em real-time

## Anti-patterns

- ❌ **DB queries pesadas em `registerComputed`** — closures correm por request por user. Se precisares de N+1 protection, faz eager-load no middleware antes de `resolveForUser`
- ❌ **Reescrever Policies como abilities** — Policies são canónicas para per-record (Filament-style `view($user, $post)`); abilities são para flags panel-level (`'viewAdminPanel'`)
- ❌ **`Gate::policy()` manual no AppServiceProvider** quando o Resource já está registado — deixa `PolicyDiscovery` cuidar; evita duplicação
- ❌ **Override `$policy` apontando para classe inexistente** — `PolicyDiscovery` faz `class_exists` e ignora silenciosamente; resulta em "missing policy" warnings sem causa óbvia

## Related

- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §AUTH-001..005
- ADRs: [ADR-001](../../PLANNING/03-adrs.md) Inertia-only · [ADR-008](../../PLANNING/03-adrs.md) Pest 3
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §Auth
- Source: [`packages/auth/src/`](src/)
- Tests: [`packages/auth/tests/`](tests/)

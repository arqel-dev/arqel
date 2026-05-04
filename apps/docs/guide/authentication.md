# Autenticação no Arqel

> **TL;DR:** Arqel **não publica** páginas de login/registro hoje. Você precisa instalar um starter kit Laravel (Breeze, Jetstream ou Fortify), ou rolar o seu próprio. Um fluxo Inertia-React **opt-in** dentro de `arqel-dev/auth` está planejado nos tickets **AUTH-006/007/008** — quando shipados, será equivalente ao que Filament/Nova oferecem nativamente.

## Por que Arqel não shipa autenticação hoje

A decisão original ficou em volta de duas regras de ouro do projeto:

1. **Laravel-native** — usar features do framework antes de reinventar (Policies, FormRequest, Eloquent, Gate).
2. **Não duplicar** — Laravel já tem starter kits oficiais (Breeze, Jetstream, Fortify) que cobrem login/register/password-reset.

Daí veio a lógica: o `arqel new` CLI instala Breeze + React + Inertia automaticamente, e quem usa Arqel "delega" auth ao starter kit.

Na prática, isso falha em três cenários:

- **`composer require arqel-dev/arqel` direto, sem CLI** — o usuário não sabe que precisa de starter kit. Resultado: app instalada sem `/login`/`/register`.
- **Comparação com competidores** — Filament e Nova shipam login pronto out-of-the-box; Arqel parece "mais incompleto" mesmo sendo a mesma quantidade de código no fim.
- **Acoplamento escondido a Breeze + React + Inertia** — apesar do nome "starter kit-agnóstico", na realidade só essa combinação casa visualmente com o painel Arqel.

Os tickets AUTH-006/007/008 (em revisão) propõem shipar páginas Inertia-React de auth dentro de `arqel-dev/auth`, configuráveis via `Panel::configure()->login()->registration()->passwordReset()` no estilo Filament.

## Como ter login/registro hoje

### Opção 1 — `arqel new` CLI (caminho recomendado)

Se está começando uma app nova:

```bash
composer global require arqel-dev/cli
arqel new my-admin --starter=breeze --tenancy=none --first-resource=Post
bash arqel-setup-my-admin.sh
```

O script gerado executa:

- `laravel new my-admin`
- `composer require arqel-dev/arqel`
- `composer require laravel/breeze --dev`
- `php artisan breeze:install react` (instala views Inertia/React de login, registro, forgot-password, profile)
- `php artisan migrate`
- `pnpm install && pnpm run build`

Resultado: app pronta com `/login`, `/register`, `/admin/{resource}`.

### Opção 2 — Breeze manualmente (mais leve)

Para apps existentes que pularam o CLI:

```bash
composer require laravel/breeze --dev
php artisan breeze:install react
php artisan migrate
pnpm install
pnpm run build
```

### Opção 3 — Jetstream (com teams + 2FA)

```bash
composer require laravel/jetstream
php artisan jetstream:install inertia
php artisan migrate
```

Note: Jetstream usa Livewire por default; o Inertia variant casa melhor com o painel React do Arqel mas o frontend de Jetstream tende a ser mais opinativo. Se quiser flexibilidade total, prefira Breeze.

### Opção 4 — Fortify (só backend)

Para apps que já têm frontend próprio:

```bash
composer require laravel/fortify
php artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider"
php artisan migrate
```

Você implementa as views React/Inertia manualmente. Útil para apps SaaS onboarding-heavy onde login é parte do funil de marketing.

## Comparação rápida

| Aspecto | Breeze + React | Jetstream Inertia | Fortify | Arqel-nativo (TBD) |
|---|---|---|---|---|
| Login | ✅ | ✅ | ✅ (backend) | ✅ (AUTH-006) |
| Register | ✅ | ✅ | ✅ (backend) | ✅ opt-in (AUTH-007) |
| Forgot password | ✅ | ✅ | ✅ (backend) | ✅ opt-in (planejado) |
| Email verification | ⚠️ opt-in | ✅ | ✅ opt-in | ✅ opt-in (AUTH-007) |
| 2FA | ❌ | ✅ | ✅ | ❌ (futuro) |
| Teams | ❌ | ✅ | ❌ | ❌ |
| Profile page | ✅ | ✅ | ❌ | ✅ (planejado) |
| Bundle size | Pequeno | Maior | Backend-only | TBD |
| Visual encaixa no painel Arqel | ✅ Inertia/React | ✅ (variant inertia) | depende | ✅ |

## Integração com `arqel-dev/auth` (authorization)

Independente do starter kit, o pacote `arqel-dev/auth` continua cuidando de **authorization** (Policies + Gate + abilities serializadas para Inertia). Após instalar o starter:

```php
// app/Providers/AppServiceProvider.php
use Arqel\Auth\AbilityRegistry;

public function boot(): void
{
    app(AbilityRegistry::class)
        ->registerGlobal('viewAdminPanel')
        ->registerGlobal('manageSettings');
}
```

E define a Gate normalmente:

```php
// app/Providers/AuthServiceProvider.php
Gate::define('viewAdminPanel', fn ($user) => $user?->isAdmin());
```

O middleware `EnsureUserCanAccessPanel` aborta 401 para guests e 403 quando a ability é registrada e nega — independente do starter kit que foi instalado.

## Como verificar se sua app está em ordem

Rode:

```bash
php artisan arqel:doctor
```

A partir de AUTH-005-doctor (planejado), o command vai detectar:

- Se `App\Models\User` existe e tem trait `Authenticatable`.
- Se há um starter kit instalado (Breeze, Jetstream ou Fortify) — warn quando ausente.
- Se as rotas `/login` e `/admin` estão registradas.

## Habilitando registration (AUTH-007)

Com AUTH-007 entregue, basta ativar o flag `registration()` no `Panel`:

```php
use Arqel\Core\Panel\Panel;

$panel = Panel::configure()
    ->login()
    ->registration();
```

Isso registra automaticamente:

- `GET /admin/register` — página Inertia `arqel-dev/auth/Register` (componente `<RegisterPage />` do pacote `@arqel-dev/auth`).
- `POST /admin/register` — cria o `User` via `config('auth.providers.users.model')`, dispara `Illuminate\Auth\Events\Registered` e faz auto-login.

Validação default: `name` (2–100 chars), `email` (único na tabela), `password` (mínimo 8 chars, com `password_confirmation`). Rate-limit: 3 registros por IP por hora.

Para customizar os campos de registro, use o builder `registrationFields()`:

```php
$panel->registration()->registrationFields(fn () => [
    ['name' => 'name', 'type' => 'text', 'label' => 'Nome completo', 'required' => true],
    ['name' => 'email', 'type' => 'email', 'label' => 'E-mail corporativo', 'required' => true],
    ['name' => 'password', 'type' => 'password', 'label' => 'Senha', 'required' => true],
    ['name' => 'password_confirmation', 'type' => 'password', 'label' => 'Confirmar senha', 'required' => true],
]);
```

## Email verification (AUTH-007)

Para habilitar verificação de e-mail (opt-in, segue o contrato `MustVerifyEmail` do Laravel):

```php
$panel = Panel::configure()
    ->login()
    ->registration()
    ->emailVerification();
```

Isso registra:

- `GET /admin/email/verify` — notice page Inertia `arqel-dev/auth/VerifyEmailNotice`.
- `GET /admin/email/verify/{id}/{hash}` — handler signed (com middleware `signed` + `throttle:6,1`), dispara `Verified` event ao confirmar.
- `POST /admin/email/verify/resend` — reenvia o link via `sendEmailVerificationNotification()`.

Pré-requisitos no model `User`:

```php
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;
    // ...
}
```

E proteja as rotas autenticadas com `verified` middleware quando quiser exigir verificação:

```php
$panel->middleware(['web', 'auth', 'verified']);
```

## Forgot password (AUTH-008)

A partir de AUTH-008, o pacote `arqel-dev/auth` shipa o fluxo completo de recuperação de senha — sem Breeze, Fortify ou Jetstream necessários.

### Como ativar

No painel, use o fluent API:

```php
use Arqel\Core\Panel\PanelRegistry;

app(PanelRegistry::class)
    ->panel('admin')
    ->login()
    ->passwordReset()
    ->passwordResetExpirationMinutes(120); // padrão 60
```

Isso registra automaticamente quatro rotas (idempotentes — não duplicam se o host já tiver `password.request`/`password.reset`):

| Método | URL | Nome | Componente Inertia |
|---|---|---|---|
| GET | `/admin/forgot-password` | `password.request` | `arqel-dev/auth/ForgotPassword` |
| POST | `/admin/forgot-password` | `password.email` | — |
| GET | `/admin/reset-password/{token}` | `password.reset` | `arqel-dev/auth/ResetPassword` |
| POST | `/admin/reset-password` | `password.update` | — |

### Fluxo completo

1. Usuário clica em **"Esqueci minha senha"** na `<LoginPage />` (link aparece automaticamente quando `passwordReset()` está ativo no painel).
2. Inertia abre `arqel-dev/auth/ForgotPassword`. Usuário digita o e-mail e submete.
3. Backend chama `Password::sendResetLink(['email' => ...])` e retorna **flash genérico** — independente do e-mail existir ou não. Isso evita user enumeration.
4. Se o e-mail existe, Laravel envia a notificação `ResetPassword` com link para `/admin/reset-password/{token}?email=...`.
5. Usuário abre o link, Inertia renderiza `arqel-dev/auth/ResetPassword` com `token` (rota) e `email` (query) pré-preenchidos.
6. Submete nova senha + confirmação. Backend valida via `ResetPasswordRequest` (min:8 + confirmed), chama `Password::reset` e redireciona para `Panel::getLoginUrl()` com flash de sucesso.

### Segurança e limites

- **Rate-limit**: 3 requests por e-mail+IP por hora em `forgot-password` e `reset-password`. Excedido, retorna `422` com mensagem traduzida `auth.throttle`.
- **Expiração de token**: `passwordResetExpirationMinutes(int)` ajusta `auth.passwords.users.expire` em runtime (default 60 minutos).
- **CSRF**: rotas POST estão sob `web` middleware → token automático.
- **Resposta genérica**: nunca revela se um e-mail está ou não cadastrado.

### Custom views

As páginas React vêm em `@arqel-dev/auth`:

```tsx
import { ForgotPasswordPage, ResetPasswordPage } from '@arqel-dev/auth';
```

Você pode trocar pelo seu próprio componente registrando o nome do componente Inertia (`arqel-dev/auth/ForgotPassword`/`arqel-dev/auth/ResetPassword`) com a sua versão no resolver Inertia do app host.

## Próximos passos planejados

- **AUTH-006** — Login + logout Inertia-React páginas opt-in via `Panel::configure()->login()` (entregue).
- **AUTH-007** — Registration opt-in + email verification opt-in (entregue).
- **AUTH-008** — Forgot-password + reset token flow (entregue).

Quando esse ticket shipar, `composer require arqel-dev/arqel` + `php artisan arqel:install` será suficiente — sem starter kit obrigatório.

## Referências

- [Auth (authorization)](./auth.md) — Policies, Gates e abilities.
- [DevTools extension install](../devtools-extension/install.md) — DevTools extension.
- [Laravel Breeze docs](https://laravel.com/docs/breeze) · [Jetstream](https://jetstream.laravel.com) · [Fortify](https://laravel.com/docs/fortify)

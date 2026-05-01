# Autenticação no Arqel

> **TL;DR:** Arqel **não publica** páginas de login/registro hoje. Você precisa instalar um starter kit Laravel (Breeze, Jetstream ou Fortify), ou rolar o seu próprio. Um fluxo Inertia-React **opt-in** dentro de `arqel/auth` está planejado nos tickets **AUTH-006/007/008** — quando shipados, será equivalente ao que Filament/Nova oferecem nativamente.

## Por que Arqel não shipa autenticação hoje

A decisão original ficou em volta de duas regras de ouro do projeto:

1. **Laravel-native** — usar features do framework antes de reinventar (Policies, FormRequest, Eloquent, Gate).
2. **Não duplicar** — Laravel já tem starter kits oficiais (Breeze, Jetstream, Fortify) que cobrem login/register/password-reset.

Daí veio a lógica: o `arqel new` CLI instala Breeze + React + Inertia automaticamente, e quem usa Arqel "delega" auth ao starter kit.

Na prática, isso falha em três cenários:

- **`composer require arqel/arqel` direto, sem CLI** — o usuário não sabe que precisa de starter kit. Resultado: app instalada sem `/login`/`/register`.
- **Comparação com competidores** — Filament e Nova shipam login pronto out-of-the-box; Arqel parece "mais incompleto" mesmo sendo a mesma quantidade de código no fim.
- **Acoplamento escondido a Breeze + React + Inertia** — apesar do nome "starter kit-agnóstico", na realidade só essa combinação casa visualmente com o painel Arqel.

Os tickets AUTH-006/007/008 (em revisão) propõem shipar páginas Inertia-React de auth dentro de `arqel/auth`, configuráveis via `Panel::configure()->login()->registration()->passwordReset()` no estilo Filament.

## Como ter login/registro hoje

### Opção 1 — `arqel new` CLI (caminho recomendado)

Se está começando uma app nova:

```bash
composer global require arqel/cli
arqel new my-admin --starter=breeze --tenancy=none --first-resource=Post
bash arqel-setup-my-admin.sh
```

O script gerado executa:

- `laravel new my-admin`
- `composer require arqel/arqel`
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

## Integração com `arqel/auth` (authorization)

Independente do starter kit, o pacote `arqel/auth` continua cuidando de **authorization** (Policies + Gate + abilities serializadas para Inertia). Após instalar o starter:

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

- `GET /admin/register` — página Inertia `arqel/auth/Register` (componente `<RegisterPage />` do pacote `@arqel/auth`).
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

- `GET /admin/email/verify` — notice page Inertia `arqel/auth/VerifyEmailNotice`.
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

## Próximos passos planejados

- **AUTH-008** — Forgot-password + reset token flow.

Quando esse ticket shipar, `composer require arqel/arqel` + `php artisan arqel:install` será suficiente — sem starter kit obrigatório.

## Referências

- [Auth (authorization)](./auth.md) — Policies, Gates e abilities.
- [DevTools extension install](../devtools-extension/install.md) — DevTools extension.
- [Laravel Breeze docs](https://laravel.com/docs/breeze) · [Jetstream](https://jetstream.laravel.com) · [Fortify](https://laravel.com/docs/fortify)

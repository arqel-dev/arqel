# Autenticación en Arqel

> **TL;DR:** Arqel **actualmente no publica** páginas de login/registro. Necesitas instalar un starter kit de Laravel (Breeze, Jetstream o Fortify), o construir el tuyo propio. Un flujo Inertia-React **opt-in** dentro de `arqel-dev/auth` está planificado en los tickets **AUTH-006/007/008** — una vez entregado, será equivalente a lo que Filament/Nova ofrecen nativamente.

## Por qué Arqel no incluye autenticación hoy

La decisión original se basó en dos de las reglas de oro del proyecto:

1. **Nativo de Laravel** — usar features del framework antes de reinventarlas (Policies, FormRequest, Eloquent, Gate).
2. **No duplicar** — Laravel ya tiene starter kits oficiales (Breeze, Jetstream, Fortify) que cubren login/registro/reset de password.

De ahí la lógica: el CLI `arqel new` instala Breeze + React + Inertia automáticamente, y los usuarios de Arqel "delegan" auth al starter kit.

En la práctica, esto falla en tres escenarios:

- **`composer require arqel-dev/arqel` directamente, sin el CLI** — el usuario no sabe que necesita un starter kit. Resultado: app instalada sin `/login`/`/register`.
- **Comparación con competidores** — Filament y Nova traen login out-of-the-box; Arqel parece "más incompleto" aunque sea la misma cantidad de código al final.
- **Acoplamiento oculto a Breeze + React + Inertia** — a pesar del nombre "starter kit-agnostic", en realidad solo esa combinación encaja visualmente con el panel de Arqel.

Los tickets AUTH-006/007/008 (en revisión) proponen incluir páginas de auth Inertia-React dentro de `arqel-dev/auth`, configurables vía `Panel::configure()->login()->registration()->passwordReset()` al estilo Filament.

## Cómo conseguir login/registro hoy

### Opción 1 — CLI `arqel new` (camino recomendado)

Si vas a empezar una app nueva:

```bash
composer global require arqel-dev/cli
arqel new my-admin --starter=breeze --tenancy=none --first-resource=Post
bash arqel-setup-my-admin.sh
```

El script generado ejecuta:

- `laravel new my-admin`
- `composer require arqel-dev/arqel`
- `composer require laravel/breeze --dev`
- `php artisan breeze:install react` (instala vistas Inertia/React para login, register, forgot-password, profile)
- `php artisan migrate`
- `pnpm install && pnpm run build`

Resultado: una app lista con `/login`, `/register`, `/admin/{resource}`.

### Opción 2 — Breeze manualmente (más ligero)

Para apps existentes que se saltaron el CLI:

```bash
composer require laravel/breeze --dev
php artisan breeze:install react
php artisan migrate
pnpm install
pnpm run build
```

### Opción 3 — Jetstream (con teams + 2FA)

```bash
composer require laravel/jetstream
php artisan jetstream:install inertia
php artisan migrate
```

Nota: Jetstream usa Livewire por defecto; la variante Inertia encaja mejor con el panel React de Arqel, pero el frontend de Jetstream tiende a ser más opinionado. Si quieres flexibilidad total, prefiere Breeze.

### Opción 4 — Fortify (solo backend)

Para apps que ya tienen su propio frontend:

```bash
composer require laravel/fortify
php artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider"
php artisan migrate
```

Tu implementas las vistas React/Inertia manualmente. Útil para apps SaaS con onboarding pesado donde el login es parte del funnel de marketing.

## Comparación rápida

| Aspecto | Breeze + React | Jetstream Inertia | Fortify | Arqel-native (TBD) |
|---|---|---|---|---|
| Login | Sí | Sí | Sí (backend) | Sí (AUTH-006) |
| Registro | Sí | Sí | Sí (backend) | Sí opt-in (AUTH-007) |
| Forgot password | Sí | Sí | Sí (backend) | Sí opt-in (planificado) |
| Verificación de email | opt-in | Sí | opt-in | opt-in (AUTH-007) |
| 2FA | No | Sí | Sí | No (futuro) |
| Teams | No | Sí | No | No |
| Página de perfil | Sí | Sí | No | Sí (planificado) |
| Tamaño del bundle | Pequeño | Mayor | Solo backend | TBD |
| Encaja visualmente con el panel Arqel | Sí (Inertia/React) | Sí (variante inertia) | depende | Sí |

## Integración con `arqel-dev/auth` (autorización)

Independientemente del starter kit, el paquete `arqel-dev/auth` sigue manejando **autorización** (Policies + Gate + abilities serializados para Inertia). Tras instalar el starter:

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

Y define el Gate como de costumbre:

```php
// app/Providers/AuthServiceProvider.php
Gate::define('viewAdminPanel', fn ($user) => $user?->isAdmin());
```

El middleware `EnsureUserCanAccessPanel` aborta con 401 para guests y 403 cuando la ability está registrada y deniega — independientemente del starter kit instalado.

## Cómo verificar si tu app está en orden

Ejecuta:

```bash
php artisan arqel:doctor
```

A partir de AUTH-005-doctor (planificado), el comando detectará:

- Si `App\Models\User` existe y usa el trait `Authenticatable`.
- Si hay un starter kit instalado (Breeze, Jetstream o Fortify) — avisa cuando falta.
- Si las rutas `/login` y `/admin` están registradas.

## Habilitando el registro (AUTH-007)

Con AUTH-007 entregado, basta con habilitar el flag `registration()` en el `Panel`:

```php
use Arqel\Core\Panel\Panel;

$panel = Panel::configure()
    ->login()
    ->registration();
```

Esto registra automáticamente:

- `GET /admin/register` — página Inertia `arqel-dev/auth/Register` (componente `<RegisterPage />` del paquete `@arqel-dev/auth`).
- `POST /admin/register` — crea el `User` vía `config('auth.providers.users.model')`, dispara `Illuminate\Auth\Events\Registered` y auto-loguea.

Validación por defecto: `name` (2–100 chars), `email` (único en la tabla), `password` (mín 8 chars, con `password_confirmation`). Rate-limit: 3 registros por IP por hora.

Para personalizar los fields de registro, usa el builder `registrationFields()`:

```php
$panel->registration()->registrationFields(fn () => [
    ['name' => 'name', 'type' => 'text', 'label' => 'Full name', 'required' => true],
    ['name' => 'email', 'type' => 'email', 'label' => 'Corporate email', 'required' => true],
    ['name' => 'password', 'type' => 'password', 'label' => 'Password', 'required' => true],
    ['name' => 'password_confirmation', 'type' => 'password', 'label' => 'Confirm password', 'required' => true],
]);
```

## Verificación de email (AUTH-007)

Para habilitar la verificación de email (opt-in, sigue el contrato `MustVerifyEmail` de Laravel):

```php
$panel = Panel::configure()
    ->login()
    ->registration()
    ->emailVerification();
```

Esto registra:

- `GET /admin/email/verify` — página de aviso Inertia `arqel-dev/auth/VerifyEmailNotice`.
- `GET /admin/email/verify/{id}/{hash}` — handler firmado (con middleware `signed` + `throttle:6,1`), dispara el evento `Verified` en la confirmación.
- `POST /admin/email/verify/resend` — reenvía el link vía `sendEmailVerificationNotification()`.

Requisitos previos en el modelo `User`:

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

Y protege las rutas autenticadas con el middleware `verified` cuando quieras requerir verificación:

```php
$panel->middleware(['web', 'auth', 'verified']);
```

## Forgot password (AUTH-008)

A partir de AUTH-008, el paquete `arqel-dev/auth` incluye el flujo completo de recuperación de password — sin necesidad de Breeze, Fortify o Jetstream.

### Cómo habilitar

En el panel, usa la API fluida:

```php
use Arqel\Core\Panel\PanelRegistry;

app(PanelRegistry::class)
    ->panel('admin')
    ->login()
    ->passwordReset()
    ->passwordResetExpirationMinutes(120); // por defecto 60
```

Esto registra automáticamente cuatro rutas (idempotente — no duplica si el host ya tiene `password.request`/`password.reset`):

| Método | URL | Nombre | Componente Inertia |
|---|---|---|---|
| GET | `/admin/forgot-password` | `password.request` | `arqel-dev/auth/ForgotPassword` |
| POST | `/admin/forgot-password` | `password.email` | — |
| GET | `/admin/reset-password/{token}` | `password.reset` | `arqel-dev/auth/ResetPassword` |
| POST | `/admin/reset-password` | `password.update` | — |

### Flujo completo

1. El usuario hace click en **"Forgot my password"** en `<LoginPage />` (el enlace aparece automáticamente cuando `passwordReset()` está activo en el panel).
2. Inertia abre `arqel-dev/auth/ForgotPassword`. El usuario escribe su email y envía.
3. El backend llama `Password::sendResetLink(['email' => ...])` y devuelve un **flash genérico** — independientemente de si el email existe. Esto evita la enumeración de usuarios.
4. Si el email existe, Laravel envía la notificación `ResetPassword` con un link a `/admin/reset-password/{token}?email=...`.
5. El usuario abre el link, Inertia renderiza `arqel-dev/auth/ResetPassword` con `token` (route) y `email` (query) pre-rellenados.
6. Envía un nuevo password + confirmación. El backend valida vía `ResetPasswordRequest` (min:8 + confirmed), llama `Password::reset` y redirige a `Panel::getLoginUrl()` con un flash de éxito.

### Seguridad y límites

- **Rate-limit**: 3 requests por email+IP por hora en `forgot-password` y `reset-password`. Cuando se excede, devuelve `422` con el mensaje `auth.throttle` traducido.
- **Expiración del token**: `passwordResetExpirationMinutes(int)` ajusta `auth.passwords.users.expire` en runtime (por defecto 60 minutos).
- **CSRF**: las rutas POST están bajo el middleware `web` → token automático.
- **Respuesta genérica**: nunca revela si un email está registrado.

### Vistas personalizadas

Las páginas React vienen de `@arqel-dev/auth`:

```tsx
import { ForgotPasswordPage, ResetPasswordPage } from '@arqel-dev/auth';
```

Puedes intercambiarlas por tu propio componente registrando el nombre del componente Inertia (`arqel-dev/auth/ForgotPassword`/`arqel-dev/auth/ResetPassword`) con tu versión en el resolver Inertia de la app host.

## Aspecto visual de las páginas de auth

Las páginas de auth Inertia-React (`LoginPage`, `RegisterPage`, `ForgotPasswordPage`, `ResetPasswordPage`) se construyen sobre el **bloque shadcn `login-04`** — un layout split-screen con una ilustración hero a la derecha y el form a la izquierda. Los tokens semánticos (`--primary`, `--background`, `--foreground`, etc.) vienen de `@arqel-dev/ui/styles.css`, así que el tema del panel se aplica automáticamente.

El `LoginController` (y los equivalentes de register/forgot/reset) pasa props ya resueltas al componente Inertia: `loginUrl`, `registerUrl`, `forgotPasswordUrl` — no necesitas construir las URLs manualmente en React. Solo consúmelas vía `usePage().props`.

## Próximos pasos planificados

- **AUTH-006** — Páginas Inertia-React opt-in de Login + logout vía `Panel::configure()->login()` (entregado).
- **AUTH-007** — Registro opt-in + verificación de email opt-in (entregado).
- **AUTH-008** — Flujo de forgot-password + reset token (entregado).

Una vez ese ticket entregado, `composer require arqel-dev/arqel` + `php artisan arqel:install` será suficiente — sin starter kit requerido.

## Referencias

- [Auth (autorización)](./auth.md) — Policies, Gates y abilities.
- [DevTools extension install](../devtools-extension/install.md) — extensión DevTools.
- [Docs de Laravel Breeze](https://laravel.com/docs/breeze) · [Jetstream](https://jetstream.laravel.com) · [Fortify](https://laravel.com/docs/fortify)

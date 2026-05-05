# Authentication in Arqel

> **TL;DR:** Arqel **does not currently publish** login/register pages. You need to install a Laravel starter kit (Breeze, Jetstream, or Fortify), or roll your own. An **opt-in** Inertia-React flow inside `arqel-dev/auth` is planned in tickets **AUTH-006/007/008** — once shipped, it will be equivalent to what Filament/Nova offer natively.

## Why Arqel does not ship authentication today

The original decision rested on two of the project's golden rules:

1. **Laravel-native** — use framework features before reinventing them (Policies, FormRequest, Eloquent, Gate).
2. **Don't duplicate** — Laravel already has official starter kits (Breeze, Jetstream, Fortify) that cover login/register/password-reset.

Hence the logic: the `arqel new` CLI installs Breeze + React + Inertia automatically, and Arqel users "delegate" auth to the starter kit.

In practice, this fails in three scenarios:

- **`composer require arqel-dev/framework` directly, without the CLI** — the user doesn't know they need a starter kit. Result: app installed without `/login`/`/register`.
- **Comparison with competitors** — Filament and Nova ship login out-of-the-box; Arqel looks "more incomplete" even though it's the same amount of code in the end.
- **Hidden coupling to Breeze + React + Inertia** — despite the "starter kit-agnostic" name, in reality only that combination matches the Arqel panel visually.

The AUTH-006/007/008 tickets (under review) propose shipping Inertia-React auth pages inside `arqel-dev/auth`, configurable via `Panel::configure()->login()->registration()->passwordReset()` in Filament style.

## How to get login/register today

### Option 1 — `arqel new` CLI (recommended path)

If you're starting a new app:

```bash
composer global require arqel-dev/cli
arqel new my-admin --starter=breeze --tenancy=none --first-resource=Post
bash arqel-setup-my-admin.sh
```

The generated script runs:

- `laravel new my-admin`
- `composer require arqel-dev/framework`
- `composer require laravel/breeze --dev`
- `php artisan breeze:install react` (installs Inertia/React views for login, register, forgot-password, profile)
- `php artisan migrate`
- `pnpm install && pnpm run build`

Result: an app ready with `/login`, `/register`, `/admin/{resource}`.

### Option 2 — Breeze manually (lighter)

For existing apps that skipped the CLI:

```bash
composer require laravel/breeze --dev
php artisan breeze:install react
php artisan migrate
pnpm install
pnpm run build
```

### Option 3 — Jetstream (with teams + 2FA)

```bash
composer require laravel/jetstream
php artisan jetstream:install inertia
php artisan migrate
```

Note: Jetstream uses Livewire by default; the Inertia variant pairs better with the Arqel React panel, but Jetstream's frontend tends to be more opinionated. If you want full flexibility, prefer Breeze.

### Option 4 — Fortify (backend only)

For apps that already have their own frontend:

```bash
composer require laravel/fortify
php artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider"
php artisan migrate
```

You implement the React/Inertia views manually. Useful for onboarding-heavy SaaS apps where login is part of the marketing funnel.

## Quick comparison

| Aspect | Breeze + React | Jetstream Inertia | Fortify | Arqel-native (TBD) |
|---|---|---|---|---|
| Login | Yes | Yes | Yes (backend) | Yes (AUTH-006) |
| Register | Yes | Yes | Yes (backend) | Yes opt-in (AUTH-007) |
| Forgot password | Yes | Yes | Yes (backend) | Yes opt-in (planned) |
| Email verification | opt-in | Yes | opt-in | opt-in (AUTH-007) |
| 2FA | No | Yes | Yes | No (future) |
| Teams | No | Yes | No | No |
| Profile page | Yes | Yes | No | Yes (planned) |
| Bundle size | Small | Larger | Backend-only | TBD |
| Visually fits the Arqel panel | Yes (Inertia/React) | Yes (inertia variant) | depends | Yes |

## Integration with `arqel-dev/auth` (authorization)

Regardless of the starter kit, the `arqel-dev/auth` package still handles **authorization** (Policies + Gate + abilities serialized for Inertia). After installing the starter:

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

And define the Gate as usual:

```php
// app/Providers/AuthServiceProvider.php
Gate::define('viewAdminPanel', fn ($user) => $user?->isAdmin());
```

The `EnsureUserCanAccessPanel` middleware aborts with 401 for guests and 403 when the ability is registered and denies — regardless of which starter kit was installed.

## How to check whether your app is in order

Run:

```bash
php artisan arqel:doctor
```

Starting with AUTH-005-doctor (planned), the command will detect:

- Whether `App\Models\User` exists and uses the `Authenticatable` trait.
- Whether a starter kit is installed (Breeze, Jetstream, or Fortify) — warns when absent.
- Whether the `/login` and `/admin` routes are registered.

## Enabling registration (AUTH-007)

With AUTH-007 delivered, simply enable the `registration()` flag on the `Panel`:

```php
use Arqel\Core\Panel\Panel;

$panel = Panel::configure()
    ->login()
    ->registration();
```

This automatically registers:

- `GET /admin/register` — Inertia page `arqel-dev/auth/Register` (`<RegisterPage />` component from the `@arqel-dev/auth` package).
- `POST /admin/register` — creates the `User` via `config('auth.providers.users.model')`, fires `Illuminate\Auth\Events\Registered`, and auto-logs in.

Default validation: `name` (2–100 chars), `email` (unique in the table), `password` (min 8 chars, with `password_confirmation`). Rate-limit: 3 registrations per IP per hour.

To customize the registration fields, use the `registrationFields()` builder:

```php
$panel->registration()->registrationFields(fn () => [
    ['name' => 'name', 'type' => 'text', 'label' => 'Full name', 'required' => true],
    ['name' => 'email', 'type' => 'email', 'label' => 'Corporate email', 'required' => true],
    ['name' => 'password', 'type' => 'password', 'label' => 'Password', 'required' => true],
    ['name' => 'password_confirmation', 'type' => 'password', 'label' => 'Confirm password', 'required' => true],
]);
```

## Email verification (AUTH-007)

To enable email verification (opt-in, follows Laravel's `MustVerifyEmail` contract):

```php
$panel = Panel::configure()
    ->login()
    ->registration()
    ->emailVerification();
```

This registers:

- `GET /admin/email/verify` — Inertia notice page `arqel-dev/auth/VerifyEmailNotice`.
- `GET /admin/email/verify/{id}/{hash}` — signed handler (with `signed` + `throttle:6,1` middleware), fires the `Verified` event on confirmation.
- `POST /admin/email/verify/resend` — resends the link via `sendEmailVerificationNotification()`.

Prerequisites on the `User` model:

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

And protect authenticated routes with the `verified` middleware when you want to require verification:

```php
$panel->middleware(['web', 'auth', 'verified']);
```

## Forgot password (AUTH-008)

Starting with AUTH-008, the `arqel-dev/auth` package ships the full password recovery flow — without Breeze, Fortify, or Jetstream needed.

### How to enable

In the panel, use the fluent API:

```php
use Arqel\Core\Panel\PanelRegistry;

app(PanelRegistry::class)
    ->panel('admin')
    ->login()
    ->passwordReset()
    ->passwordResetExpirationMinutes(120); // default 60
```

This automatically registers four routes (idempotent — they don't duplicate if the host already has `password.request`/`password.reset`):

| Method | URL | Name | Inertia component |
|---|---|---|---|
| GET | `/admin/forgot-password` | `password.request` | `arqel-dev/auth/ForgotPassword` |
| POST | `/admin/forgot-password` | `password.email` | — |
| GET | `/admin/reset-password/{token}` | `password.reset` | `arqel-dev/auth/ResetPassword` |
| POST | `/admin/reset-password` | `password.update` | — |

### Full flow

1. The user clicks **"Forgot my password"** on `<LoginPage />` (the link appears automatically when `passwordReset()` is active on the panel).
2. Inertia opens `arqel-dev/auth/ForgotPassword`. The user types their email and submits.
3. The backend calls `Password::sendResetLink(['email' => ...])` and returns a **generic flash** — regardless of whether the email exists. This avoids user enumeration.
4. If the email exists, Laravel sends the `ResetPassword` notification with a link to `/admin/reset-password/{token}?email=...`.
5. The user opens the link, Inertia renders `arqel-dev/auth/ResetPassword` with `token` (route) and `email` (query) pre-filled.
6. They submit a new password + confirmation. The backend validates via `ResetPasswordRequest` (min:8 + confirmed), calls `Password::reset`, and redirects to `Panel::getLoginUrl()` with a success flash.

### Security and limits

- **Rate-limit**: 3 requests per email+IP per hour on `forgot-password` and `reset-password`. When exceeded, returns `422` with the translated `auth.throttle` message.
- **Token expiration**: `passwordResetExpirationMinutes(int)` adjusts `auth.passwords.users.expire` at runtime (default 60 minutes).
- **CSRF**: POST routes are under the `web` middleware → automatic token.
- **Generic response**: never reveals whether an email is registered.

### Custom views

The React pages come from `@arqel-dev/auth`:

```tsx
import { ForgotPasswordPage, ResetPasswordPage } from '@arqel-dev/auth';
```

You can swap them for your own component by registering the Inertia component name (`arqel-dev/auth/ForgotPassword`/`arqel-dev/auth/ResetPassword`) with your version in the host app's Inertia resolver.

## Auth pages visuals

The Inertia-React auth pages (`LoginPage`, `RegisterPage`, `ForgotPasswordPage`, `ResetPasswordPage`) are built on top of the **shadcn `login-04` block** — a split-screen layout with a hero illustration on the right and the form on the left. The semantic tokens (`--primary`, `--background`, `--foreground`, etc.) come from `@arqel-dev/ui/styles.css`, so the panel theme is applied automatically.

The `LoginController` (and the equivalents for register/forgot/reset) passes already-resolved props to the Inertia component: `loginUrl`, `registerUrl`, `forgotPasswordUrl` — you don't need to build the URLs manually in React. Just consume them via `usePage().props`.

## Planned next steps

- **AUTH-006** — Login + logout opt-in Inertia-React pages via `Panel::configure()->login()` (delivered).
- **AUTH-007** — Opt-in registration + opt-in email verification (delivered).
- **AUTH-008** — Forgot-password + reset token flow (delivered).

Once that ticket ships, `composer require arqel-dev/framework` + `php artisan arqel:install` will be enough — no required starter kit.

## References

- [Auth (authorization)](./auth.md) — Policies, Gates, and abilities.
- [DevTools extension install](../devtools-extension/install.md) — DevTools extension.
- [Laravel Breeze docs](https://laravel.com/docs/breeze) · [Jetstream](https://jetstream.laravel.com) · [Fortify](https://laravel.com/docs/fortify)

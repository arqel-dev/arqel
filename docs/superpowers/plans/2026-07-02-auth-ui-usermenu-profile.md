# Auth UI — UserMenu + Profile Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the two missing Auth UI pieces — a `UserMenu` dropdown (authenticated user + theme + logout) in the Topbar, and an opt-in Profile page (edit name/email/password) — reusing the existing auth backend.

**Architecture:** `UserMenu` is a presentational shadcn `DropdownMenu` in `@arqel-dev/ui` reading props (no data-fetching). The Profile feature mirrors the existing modular auth pattern: a `Panel::profile()` gate (core), a `Routes::registerProfile()` block + `ProfileController` + two `FormRequest`s (auth PHP), and a `ProfilePage` React component (auth JS) resolved as the Inertia page `arqel-dev/auth/Profile`. All copy flows through the existing `arqel::` PHP lang files (EN + pt_BR), surfaced to React via `useArqelTranslations()`.

**Tech Stack:** PHP 8.3+ / Laravel 12+ (Pest 3, Orchestra Testbench), React 19 + Inertia 3 + TypeScript strict (Vitest), shadcn/Radix primitives, Tailwind v4.

## Global Constraints

- **Inertia-only** PHP↔React bridge (ADR-001); no fetch libs. Profile forms use `@inertiajs/react` `useForm`.
- **`declare(strict_types=1)`** at the top of every PHP file; classes `final` unless extensibility is intended.
- **Code in English** (identifiers, comments); **user-facing copy in EN + pt_BR with full diacritics** and key parity between the two lang files.
- **Tests are mandatory** (ADR-008): Pest for PHP, Vitest for JS. No new code without tests.
- **Commit format:** Conventional Commits + DCO sign-off (`git commit --signoff`), scope = package name, body references the spec. Footer: `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`.
- **i18n key homes (verified):** frontend UI strings live under `arqel.*` in `packages/core/resources/lang/{en,pt_BR}/arqel.php` (surfaced to React via `useArqelTranslations`); PHP flash/validation copy lives under `arqel::messages.*` in `messages.php`. The existing `arqel.auth.*` block (arqel.php line 28) and `arqel::messages.flash.*` block (messages.php line 20) are the homes to extend. NOTE: the spec's `arqel.theme.light/dark/system` flat keys do NOT exist — real theme keys are `arqel.theme.toggle.{system,light,dark}` (toggle-phrased). This plan defines fresh `arqel.auth.menu.*` keys instead.
- **Inertia page name:** the Profile React page resolves as `arqel-dev/auth/Profile` (matching the existing `arqel-dev/auth/*` resolver convention in `apps/showcase/resources/js/app.tsx:111-120`), NOT the spec's `Arqel/Profile`.
- **useTheme shape (verified):** `useTheme()` from `@arqel-dev/react/providers` returns `{ theme, resolved, resolvedTheme, setTheme, toggle }` (`ThemeProvider.tsx:200`). `theme: 'light' | 'dark' | 'system'`, `setTheme(theme)`.
- **Out of scope:** avatar image upload; 2FA; email-change re-verification (email updates directly).

---

## File Structure

**New files:**
- `packages-js/ui/src/shell/UserMenu.tsx` — the dropdown component.
- `packages-js/ui/src/shell/UserMenu.test.tsx` — Vitest.
- `packages/core/` — no new file (edit `Panel.php`).
- `packages/auth/src/Http/Controllers/ProfileController.php` — show/update/updatePassword.
- `packages/auth/src/Http/Requests/UpdateProfileRequest.php` — name/email validation.
- `packages/auth/src/Http/Requests/UpdatePasswordRequest.php` — current+new password.
- `packages/auth/tests/Feature/Auth/ProfileControllerTest.php` — Pest.
- `packages-js/auth/src/ProfilePage.tsx` — the account-settings page.
- `packages-js/auth/src/ProfilePage.test.tsx` — Vitest.

**Modified files:**
- `packages/core/src/Panel/Panel.php` — add `$profileEnabled` flag + `profile()`/`profileEnabled()`.
- `packages/auth/src/Routes.php` — add `registerProfile()`, call it from `register()`.
- `packages-js/ui/src/shell/index.ts` + `packages-js/ui/src/index.ts` — export `UserMenu`.
- `packages-js/auth/src/index.ts` — export `ProfilePage`.
- `packages-js/ui/src/shell/Topbar.tsx` — remove the standalone theme toggle button.
- `packages/core/resources/lang/{en,pt_BR}/arqel.php` — `auth.menu.*` + `auth.profile.*` keys.
- `packages/core/resources/lang/{en,pt_BR}/messages.php` — `flash.profile.*` keys.
- `apps/showcase/resources/js/app.tsx` — wire `UserMenu`, enable panel `->profile()` resolver entry.
- `apps/showcase/routes/*` or panel config — call `->profile()` on the showcase panel (PHP side).

---

## Task 1: Panel profile gate (core)

**Files:**
- Modify: `packages/core/src/Panel/Panel.php` (add flag near line 64, setter/getter near line 352)
- Test: `packages/core/tests/` (add a Pest test mirroring existing Panel flag tests)

**Interfaces:**
- Produces: `Panel::profile(bool $enabled = true): self`, `Panel::profileEnabled(): bool` (returns `$this->profileEnabled && $this->defaultAuth`, mirroring `passwordResetEnabled()`).

- [ ] **Step 1: Write the failing test**

Find the existing Panel test file (`grep -rl "passwordResetEnabled" packages/core/tests`). Add to it:

```php
it('is profile-disabled by default and enables via profile()', function (): void {
    $panel = new \Arqel\Core\Panel\Panel('admin');
    expect($panel->profileEnabled())->toBeFalse();

    $panel->profile();
    expect($panel->profileEnabled())->toBeTrue();

    $panel->profile(false);
    expect($panel->profileEnabled())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd packages/core && vendor/bin/pest --filter="profile-disabled by default"`
Expected: FAIL — `Call to undefined method Arqel\Core\Panel\Panel::profileEnabled()`

- [ ] **Step 3: Add the flag + setter/getter**

In `packages/core/src/Panel/Panel.php`, add the flag next to `private bool $passwordResetEnabled = false;`:

```php
    private bool $profileEnabled = false;
```

And add the methods next to `passwordResetEnabled()` (~line 352):

```php
    /**
     * Habilita a página bundled de perfil do usuário (Auth UI).
     */
    public function profile(bool $enabled = true): self
    {
        $this->profileEnabled = $enabled;

        return $this;
    }

    public function profileEnabled(): bool
    {
        return $this->profileEnabled && $this->defaultAuth;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd packages/core && vendor/bin/pest --filter="profile-disabled by default"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add packages/core/src/Panel/Panel.php packages/core/tests
git commit --signoff -m "feat(core): add Panel::profile()/profileEnabled() gate

Implements the Profile gate from
docs/superpowers/specs/2026-06-25-auth-ui-usermenu-profile-design.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Profile FormRequests (auth PHP)

**Files:**
- Create: `packages/auth/src/Http/Requests/UpdateProfileRequest.php`
- Create: `packages/auth/src/Http/Requests/UpdatePasswordRequest.php`
- Test: covered by Task 4 (ProfileControllerTest exercises both via the routes)

**Interfaces:**
- Produces:
  - `UpdateProfileRequest` — `authorize(): bool` (true when `$this->user() !== null`); `rules()` → `name` required|string|max:255, `email` required|email|max:255|`Rule::unique($usersTable,'email')->ignore($user->id)`.
  - `UpdatePasswordRequest` — `authorize(): bool` (same); `rules()` → `current_password` required|`current_password:{guard}`, `password` required|confirmed|min:8.
- Consumes: `ResolvesPanelGuard` trait (`packages/auth/src/Concerns/ResolvesPanelGuard.php`) for `resolvePanelGuard()`.

- [ ] **Step 1: Write UpdateProfileRequest**

Create `packages/auth/src/Http/Requests/UpdateProfileRequest.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Auth\Http\Requests;

use Arqel\Auth\Concerns\ResolvesPanelGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest para atualização dos dados de conta (nome + email) do
 * usuário autenticado. Escopo Auth UI (Profile).
 */
final class UpdateProfileRequest extends FormRequest
{
    use ResolvesPanelGuard;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $userId = $user?->getAuthIdentifier();
        $usersTable = $this->resolveUsersTable();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique($usersTable, 'email')->ignore($userId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => (string) __('arqel::messages.profile.attributes.name'),
            'email' => (string) __('arqel::messages.profile.attributes.email'),
        ];
    }

    /**
     * Resolve the users table backing the panel guard's provider model,
     * mirroring RegisterRequest so the unique check targets the right table.
     */
    private function resolveUsersTable(): string
    {
        $guard = $this->resolvePanelGuard();
        $provider = config("auth.guards.{$guard}.provider", 'users');
        $providerKey = is_string($provider) && $provider !== '' ? $provider : 'users';
        $model = (string) config("auth.providers.{$providerKey}.model", 'App\\Models\\User');

        if (class_exists($model)) {
            /** @var \Illuminate\Database\Eloquent\Model $instance */
            $instance = new $model;

            return $instance->getTable();
        }

        return 'users';
    }
}
```

- [ ] **Step 2: Write UpdatePasswordRequest**

Create `packages/auth/src/Http/Requests/UpdatePasswordRequest.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Auth\Http\Requests;

use Arqel\Auth\Concerns\ResolvesPanelGuard;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest para troca de senha do usuário autenticado (verifica a
 * senha atual). Escopo Auth UI (Profile).
 */
final class UpdatePasswordRequest extends FormRequest
{
    use ResolvesPanelGuard;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $guard = $this->resolvePanelGuard();

        return [
            'current_password' => ['required', 'string', "current_password:{$guard}"],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => (string) __('arqel::messages.profile.attributes.current_password'),
            'password' => (string) __('arqel::messages.profile.attributes.password'),
        ];
    }
}
```

- [ ] **Step 3: Verify PHP parses (syntax)**

Run: `php -l packages/auth/src/Http/Requests/UpdateProfileRequest.php && php -l packages/auth/src/Http/Requests/UpdatePasswordRequest.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```bash
git add packages/auth/src/Http/Requests/UpdateProfileRequest.php packages/auth/src/Http/Requests/UpdatePasswordRequest.php
git commit --signoff -m "feat(auth): add Profile FormRequests (name/email + password)

Implements Component 2 (backend) from
docs/superpowers/specs/2026-06-25-auth-ui-usermenu-profile-design.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: ProfileController + Routes::registerProfile (auth PHP)

**Files:**
- Create: `packages/auth/src/Http/Controllers/ProfileController.php`
- Modify: `packages/auth/src/Routes.php` (add `registerProfile()`, call from `register()`, add flag + reset + `isProfileRegistered()`)
- Test: Task 4

**Interfaces:**
- Consumes: `Panel::profileEnabled()`/`getLoginUrl()` (Task 1), `UpdateProfileRequest`/`UpdatePasswordRequest` (Task 2), `deriveSiblingUrl()` (existing, Routes.php:243).
- Produces:
  - `ProfileController::show(Request): Inertia\Response` renders `arqel-dev/auth/Profile` with `['user' => $request->user()->only('id','name','email'), 'updateUrl' => ..., 'passwordUrl' => ...]`.
  - `ProfileController::update(UpdateProfileRequest): RedirectResponse`.
  - `ProfileController::updatePassword(UpdatePasswordRequest): RedirectResponse`.
  - Routes named `arqel.auth.profile.show` (GET), `arqel.auth.profile.update` (PUT), `arqel.auth.profile.password` (PUT), all under middleware `['web', HandleArqelInertiaRequests::class, "auth:{$guard}"]`.

- [ ] **Step 1: Write ProfileController**

Create `packages/auth/src/Http/Controllers/ProfileController.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Auth\Http\Controllers;

use Arqel\Auth\Http\Requests\UpdatePasswordRequest;
use Arqel\Auth\Http\Requests\UpdateProfileRequest;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página de perfil bundled de Arqel (Auth UI).
 *
 * Renderiza o componente Inertia `arqel-dev/auth/Profile` e processa os
 * dois formulários: dados de conta (nome/email) e troca de senha.
 */
final class ProfileController
{
    /**
     * GET {profileUrl} — renderiza a página Inertia com os dados do user.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('arqel-dev/auth/Profile', [
            'user' => $user?->only(['id', 'name', 'email']) ?? [],
            'updateUrl' => $this->profileUrl(),
            'passwordUrl' => $this->profileUrl().'/password',
        ]);
    }

    /**
     * PUT {profileUrl} — atualiza nome + email.
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->save();

        return back()->with('success', __('arqel::messages.flash.profile.updated'));
    }

    /**
     * PUT {profileUrl}/password — troca a senha.
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $user->password = Hash::make((string) $validated['password']);
        $user->save();

        return back()->with('success', __('arqel::messages.flash.profile.password_updated'));
    }

    private function profileUrl(): string
    {
        $panel = $this->currentPanel();
        $loginUrl = $panel?->getLoginUrl() ?? '/admin/login';

        if (str_ends_with($loginUrl, '/login')) {
            return substr($loginUrl, 0, -6).'/profile';
        }

        return rtrim($loginUrl, '/').'/profile';
    }

    private function currentPanel(): ?\Arqel\Core\Panel\Panel
    {
        if (! app()->bound(PanelRegistry::class)) {
            return null;
        }

        return app(PanelRegistry::class)->getCurrent();
    }
}
```

- [ ] **Step 2: Add registerProfile() to Routes.php**

In `packages/auth/src/Routes.php`: add the import at the top with the other controller imports:

```php
use Arqel\Auth\Http\Controllers\ProfileController;
```

Add the flag next to the others (after `$passwordResetRegistered`):

```php
    private static bool $profileRegistered = false;
```

Call it in `register()` (after `registerPasswordReset`):

```php
        self::registerProfile($panel);
```

Add the method (after `registerPasswordReset`, before `reset()`):

```php
    /**
     * Regista as rotas bundled de perfil do usuário (idempotente).
     */
    public static function registerProfile(?Panel $panel = null): void
    {
        if (self::$profileRegistered) {
            return;
        }

        if (! ($panel?->profileEnabled() ?? false)) {
            return;
        }

        Route::getRoutes()->refreshNameLookups();
        if (Route::has('arqel.auth.profile.show')) {
            self::$profileRegistered = true;

            return;
        }

        $profileUrl = self::deriveSiblingUrl($panel->getLoginUrl(), 'profile');
        $guard = self::guardFor($panel);
        $middleware = ['web', HandleArqelInertiaRequests::class, "auth:{$guard}"];

        Route::get($profileUrl, [ProfileController::class, 'show'])
            ->middleware($middleware)
            ->name('arqel.auth.profile.show');

        Route::put($profileUrl, [ProfileController::class, 'update'])
            ->middleware($middleware)
            ->name('arqel.auth.profile.update');

        Route::put($profileUrl.'/password', [ProfileController::class, 'updatePassword'])
            ->middleware($middleware)
            ->name('arqel.auth.profile.password');

        self::$profileRegistered = true;
    }
```

Extend `reset()` (add the line inside it):

```php
        self::$profileRegistered = false;
```

Add the accessor next to `isPasswordResetRegistered()`:

```php
    public static function isProfileRegistered(): bool
    {
        return self::$profileRegistered;
    }
```

- [ ] **Step 3: Verify PHP parses**

Run: `php -l packages/auth/src/Http/Controllers/ProfileController.php && php -l packages/auth/src/Routes.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```bash
git add packages/auth/src/Http/Controllers/ProfileController.php packages/auth/src/Routes.php
git commit --signoff -m "feat(auth): add ProfileController + Routes::registerProfile

Implements Component 2 (backend) from
docs/superpowers/specs/2026-06-25-auth-ui-usermenu-profile-design.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: ProfileController Pest tests (auth PHP)

**Files:**
- Create: `packages/auth/tests/Feature/Auth/ProfileControllerTest.php`

**Interfaces:**
- Consumes: `Routes::registerProfile`, `ProfileController`, `Panel::profile()` (Tasks 1-3). Mirrors `RegisterControllerTest.php` Testbench setup.

- [ ] **Step 1: Write the test file**

Create `packages/auth/tests/Feature/Auth/ProfileControllerTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Auth\Routes;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class ProfileUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function (): void {
    Routes::reset();

    Schema::dropIfExists('users');
    Schema::create('users', function ($table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->rememberToken()->nullable();
    });

    config()->set('auth.providers.users.driver', 'eloquent');
    config()->set('auth.providers.users.model', ProfileUser::class);

    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login()->profile();
    $registry->setCurrent('admin');

    Routes::register($panel);
});

function makeProfileUser(): ProfileUser
{
    return ProfileUser::create([
        'name' => 'Original',
        'email' => 'original@example.com',
        'password' => Hash::make('secret-password'),
    ]);
}

it('renders the Inertia profile page on GET /admin/profile', function (): void {
    $user = makeProfileUser();

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->get('/admin/profile');

    $response->assertOk();
    $payload = json_decode($response->getContent() ?: '', true);
    expect($payload['component'] ?? null)->toBe('arqel-dev/auth/Profile');
    expect($payload['props']['user']['email'] ?? null)->toBe('original@example.com');
});

it('redirects a guest away from GET /admin/profile', function (): void {
    $response = $this->get('/admin/profile');
    expect($response->getStatusCode())->toBe(302);
});

it('updates name and email', function (): void {
    $user = makeProfileUser();

    $response = $this->actingAs($user)->put('/admin/profile', [
        'name' => 'Renamed',
        'email' => 'renamed@example.com',
    ]);

    $response->assertRedirect();
    $user->refresh();
    expect($user->name)->toBe('Renamed');
    expect($user->email)->toBe('renamed@example.com');
});

it('rejects a blank name', function (): void {
    $user = makeProfileUser();

    $response = $this->actingAs($user)->put('/admin/profile', [
        'name' => '',
        'email' => 'renamed@example.com',
    ]);

    $response->assertSessionHasErrors('name');
});

it('rejects a duplicate email', function (): void {
    $user = makeProfileUser();
    ProfileUser::create([
        'name' => 'Other',
        'email' => 'taken@example.com',
        'password' => Hash::make('x'),
    ]);

    $response = $this->actingAs($user)->put('/admin/profile', [
        'name' => 'Original',
        'email' => 'taken@example.com',
    ]);

    $response->assertSessionHasErrors('email');
});

it('allows keeping your own email (unique ignore self)', function (): void {
    $user = makeProfileUser();

    $response = $this->actingAs($user)->put('/admin/profile', [
        'name' => 'Original',
        'email' => 'original@example.com',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

it('updates the password when current_password is correct', function (): void {
    $user = makeProfileUser();

    $response = $this->actingAs($user)->put('/admin/profile/password', [
        'current_password' => 'secret-password',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ]);

    $response->assertRedirect();
    $user->refresh();
    expect(Hash::check('new-secret-password', $user->password))->toBeTrue();
});

it('rejects a wrong current_password', function (): void {
    $user = makeProfileUser();

    $response = $this->actingAs($user)->put('/admin/profile/password', [
        'current_password' => 'wrong-password',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ]);

    $response->assertSessionHasErrors('current_password');
});

it('does NOT register profile routes when profileEnabled() is false', function (): void {
    Routes::reset();
    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login(); // no ->profile()
    $registry->setCurrent('admin');
    Routes::register($panel);

    expect(\Illuminate\Support\Facades\Route::has('arqel.auth.profile.show'))->toBeFalse();
});
```

- [ ] **Step 2: Run the tests**

Run: `cd packages/auth && vendor/bin/pest --filter=ProfileController`
Expected: PASS (9 tests). If `current_password` rule errors on guard, confirm the default `web` guard is active in Testbench; the rule `current_password:web` validates against `Auth::guard('web')`.

- [ ] **Step 3: Commit**

```bash
git add packages/auth/tests/Feature/Auth/ProfileControllerTest.php
git commit --signoff -m "test(auth): cover ProfileController update/password/gating

Refs docs/superpowers/specs/2026-06-25-auth-ui-usermenu-profile-design.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: i18n keys (EN + pt_BR parity)

**Files:**
- Modify: `packages/core/resources/lang/en/arqel.php`, `packages/core/resources/lang/pt_BR/arqel.php`
- Modify: `packages/core/resources/lang/en/messages.php`, `packages/core/resources/lang/pt_BR/messages.php`
- Test: `packages/core/tests/` (add a parity assertion if a lang-parity test exists; else Step 4 grep-verifies)

**Interfaces:**
- Produces (consumed by Tasks 6 & 7 via `useArqelTranslations`):
  - `arqel.auth.menu.{open,profile,logout,account,theme,theme_light,theme_dark,theme_system}`
  - `arqel.auth.profile.{title,account_section,name,email,save,saved,password_section,current_password,new_password,confirm_password,change_password,changed}`
  - `arqel::messages.flash.profile.{updated,password_updated}`
  - `arqel::messages.profile.attributes.{name,email,current_password,password}` (consumed by Task 2 FormRequests)

- [ ] **Step 1: Add EN keys to arqel.php**

In `packages/core/resources/lang/en/arqel.php`, inside the existing `'auth' => [ ... ]` block (arqel.php line 28), append these keys before its closing `]`:

```php
        // Auth UI — UserMenu + Profile (design spec 2026-06-25).
        'menu' => [
            'open' => 'Open user menu',
            'profile' => 'Profile',
            'logout' => 'Log out',
            'account' => 'Account',
            'theme' => 'Theme',
            'theme_light' => 'Light',
            'theme_dark' => 'Dark',
            'theme_system' => 'System',
        ],
        'profile' => [
            'title' => 'Profile',
            'account_section' => 'Account data',
            'name' => 'Name',
            'email' => 'Email',
            'save' => 'Save',
            'saved' => 'Profile updated.',
            'password_section' => 'Change password',
            'current_password' => 'Current password',
            'new_password' => 'New password',
            'confirm_password' => 'Confirm password',
            'change_password' => 'Change password',
            'changed' => 'Password updated.',
        ],
```

- [ ] **Step 2: Add pt_BR keys to arqel.php (parity + diacritics)**

In `packages/core/resources/lang/pt_BR/arqel.php`, inside its `'auth' => [ ... ]` block, append:

```php
        // Auth UI — UserMenu + Profile (design spec 2026-06-25).
        'menu' => [
            'open' => 'Abrir menu do usuário',
            'profile' => 'Perfil',
            'logout' => 'Sair',
            'account' => 'Conta',
            'theme' => 'Tema',
            'theme_light' => 'Claro',
            'theme_dark' => 'Escuro',
            'theme_system' => 'Sistema',
        ],
        'profile' => [
            'title' => 'Perfil',
            'account_section' => 'Dados da conta',
            'name' => 'Nome',
            'email' => 'E-mail',
            'save' => 'Salvar',
            'saved' => 'Perfil atualizado.',
            'password_section' => 'Alterar senha',
            'current_password' => 'Senha atual',
            'new_password' => 'Nova senha',
            'confirm_password' => 'Confirmar senha',
            'change_password' => 'Alterar senha',
            'changed' => 'Senha atualizada.',
        ],
```

- [ ] **Step 3: Add flash + attribute keys to messages.php (both locales)**

In `packages/core/resources/lang/en/messages.php`, inside the `'flash' => [ ... ]` block (messages.php line 20), add:

```php
        'profile' => [
            'updated' => 'Profile updated.',
            'password_updated' => 'Password updated.',
        ],
```

And add a top-level `'profile'` block (sibling of `'flash'`, e.g. after the `'errors'` block):

```php
    'profile' => [
        'attributes' => [
            'name' => 'name',
            'email' => 'email',
            'current_password' => 'current password',
            'password' => 'password',
        ],
    ],
```

In `packages/core/resources/lang/pt_BR/messages.php`, inside its `'flash'` block:

```php
        'profile' => [
            'updated' => 'Perfil atualizado.',
            'password_updated' => 'Senha atualizada.',
        ],
```

And the top-level block:

```php
    'profile' => [
        'attributes' => [
            'name' => 'nome',
            'email' => 'e-mail',
            'current_password' => 'senha atual',
            'password' => 'senha',
        ],
    ],
```

- [ ] **Step 4: Verify PHP parses + key parity**

Run:
```bash
php -l packages/core/resources/lang/en/arqel.php && php -l packages/core/resources/lang/pt_BR/arqel.php
php -l packages/core/resources/lang/en/messages.php && php -l packages/core/resources/lang/pt_BR/messages.php
php -r "\$en=require 'packages/core/resources/lang/en/arqel.php'; \$pt=require 'packages/core/resources/lang/pt_BR/arqel.php'; \$fen=array_keys(\Illuminate\Support\Arr::dot(\$en['auth']['profile'])); \$fpt=array_keys(\Illuminate\Support\Arr::dot(\$pt['auth']['profile'])); echo (\$fen===\$fpt) ? 'PARITY OK\n' : 'PARITY FAIL\n';" 2>/dev/null || echo "run parity via the core test-suite instead"
```
Expected: `No syntax errors detected` (×4). Parity: EN and pt_BR must have identical key sets under `auth.menu`, `auth.profile`, `flash.profile`, `profile.attributes`.

- [ ] **Step 5: Commit**

```bash
git add packages/core/resources/lang
git commit --signoff -m "feat(core): add Auth UI i18n keys (menu + profile, en + pt_BR)

Refs docs/superpowers/specs/2026-06-25-auth-ui-usermenu-profile-design.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: UserMenu component (ui JS)

**Files:**
- Create: `packages-js/ui/src/shell/UserMenu.tsx`
- Create: `packages-js/ui/src/shell/UserMenu.test.tsx`
- Modify: `packages-js/ui/src/shell/index.ts`, `packages-js/ui/src/index.ts`
- Modify: `packages-js/ui/src/shell/Topbar.tsx` (remove theme toggle button)

**Interfaces:**
- Consumes: shadcn `DropdownMenu*` from `../shadcn/ui/dropdown-menu.js`; `useTheme` from `@arqel-dev/react/providers`; `useArqelTranslations` from `@arqel-dev/react/utils`; `router`, `Link` from `@inertiajs/react`; `cn` from `../utils/cn.js`; `arqel.auth.menu.*` keys (Task 5).
- Produces: `UserMenu` component + `UserMenuProps` (exported from `shell/index.ts` and root `index.ts`).

```ts
interface UserMenuProps {
  user: { name?: string | null; email?: string | null };
  logoutUrl: string;
  profileUrl?: string;
  className?: string;
}
```

- [ ] **Step 1: Write the failing test**

Create `packages-js/ui/src/shell/UserMenu.test.tsx`:

```tsx
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

const post = vi.fn();
vi.mock('@inertiajs/react', () => ({
  router: { post: (...args: unknown[]) => post(...args) },
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => (
    <a href={href}>{children}</a>
  ),
}));

const setTheme = vi.fn();
vi.mock('@arqel-dev/react/providers', () => ({
  useTheme: () => ({ theme: 'system', setTheme }),
}));

vi.mock('@arqel-dev/react/utils', () => ({
  useArqelTranslations: () => (_key: string, fallback?: string) => fallback ?? _key,
}));

import { UserMenu } from './UserMenu.js';

describe('UserMenu', () => {
  it('posts to logoutUrl when Log out is chosen', async () => {
    render(<UserMenu user={{ name: 'Ada', email: 'ada@x.com' }} logoutUrl="/admin/logout" />);
    await userEvent.click(screen.getByRole('button', { name: /open user menu/i }));
    await userEvent.click(screen.getByText(/log out/i));
    expect(post).toHaveBeenCalledWith('/admin/logout');
  });

  it('shows Profile only when profileUrl is present', async () => {
    const { rerender } = render(
      <UserMenu user={{ name: 'Ada' }} logoutUrl="/admin/logout" />,
    );
    await userEvent.click(screen.getByRole('button', { name: /open user menu/i }));
    expect(screen.queryByText(/^profile$/i)).toBeNull();

    rerender(
      <UserMenu user={{ name: 'Ada' }} logoutUrl="/admin/logout" profileUrl="/admin/profile" />,
    );
    expect(screen.getByText(/^profile$/i)).toBeInTheDocument();
  });

  it('falls back to email, then Account, when name is null', async () => {
    render(<UserMenu user={{ name: null, email: 'ada@x.com' }} logoutUrl="/admin/logout" />);
    expect(screen.getByText('ada@x.com')).toBeInTheDocument();
  });

  it('calls setTheme when a theme radio item is chosen', async () => {
    render(<UserMenu user={{ name: 'Ada' }} logoutUrl="/admin/logout" />);
    await userEvent.click(screen.getByRole('button', { name: /open user menu/i }));
    await userEvent.click(screen.getByText(/^dark$/i));
    expect(setTheme).toHaveBeenCalledWith('dark');
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd packages-js/ui && vitest run src/shell/UserMenu.test.tsx`
Expected: FAIL — cannot resolve `./UserMenu.js`.

- [ ] **Step 3: Write UserMenu.tsx**

Create `packages-js/ui/src/shell/UserMenu.tsx`:

```tsx
/**
 * `<UserMenu>` — authenticated-user dropdown for the Topbar `userMenu` slot.
 *
 * Presentational: reads `user`/`logoutUrl`/`profileUrl` via props (no
 * data-fetching). Built on the vendored shadcn DropdownMenu. Logout posts
 * via Inertia (CSRF + redirect handled server-side). Theme control lives
 * here (moved out of the Topbar). Usable standalone — the Profile item and
 * the theme group are each conditional.
 */

import { useTheme } from '@arqel-dev/react/providers';
import { useArqelTranslations } from '@arqel-dev/react/utils';
import { Link, router } from '@inertiajs/react';
import type { ReactElement } from 'react';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '../shadcn/ui/dropdown-menu.js';
import { cn } from '../utils/cn.js';

export interface UserMenuProps {
  user: { name?: string | null; email?: string | null };
  logoutUrl: string;
  profileUrl?: string;
  className?: string;
}

function useThemeSafe(): { theme?: string; setTheme?: (t: string) => void } {
  try {
    return useTheme() as { theme?: string; setTheme?: (t: string) => void };
  } catch {
    return {};
  }
}

export function UserMenu({ user, logoutUrl, profileUrl, className }: UserMenuProps): ReactElement {
  const t = useArqelTranslations();
  const { theme, setTheme } = useThemeSafe();

  const name = user.name ?? undefined;
  const email = user.email ?? undefined;
  const label = name ?? email ?? t('arqel.auth.menu.account', 'Account');
  const initial = (name ?? email ?? '?').trim().charAt(0).toUpperCase();

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        aria-label={t('arqel.auth.menu.open', 'Open user menu')}
        className={cn(
          'inline-flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-ring',
          className,
        )}
      >
        <span
          aria-hidden="true"
          className="flex h-7 w-7 items-center justify-center rounded-full bg-muted text-muted-foreground"
        >
          {initial}
        </span>
        <span className="hidden max-w-[10rem] truncate md:inline">{label}</span>
        <span aria-hidden="true" className="text-muted-foreground">
          ▾
        </span>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-56">
        <DropdownMenuLabel className="flex flex-col">
          {name && <span className="font-medium">{name}</span>}
          {email && <span className="truncate text-xs text-muted-foreground">{email}</span>}
          {!name && !email && <span className="font-medium">{label}</span>}
        </DropdownMenuLabel>

        {profileUrl && (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
              <Link href={profileUrl}>{t('arqel.auth.menu.profile', 'Profile')}</Link>
            </DropdownMenuItem>
          </>
        )}

        {setTheme && (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuLabel className="text-xs text-muted-foreground">
              {t('arqel.auth.menu.theme', 'Theme')}
            </DropdownMenuLabel>
            <DropdownMenuRadioGroup value={theme} onValueChange={(v) => setTheme(v)}>
              <DropdownMenuRadioItem value="light">
                {t('arqel.auth.menu.theme_light', 'Light')}
              </DropdownMenuRadioItem>
              <DropdownMenuRadioItem value="dark">
                {t('arqel.auth.menu.theme_dark', 'Dark')}
              </DropdownMenuRadioItem>
              <DropdownMenuRadioItem value="system">
                {t('arqel.auth.menu.theme_system', 'System')}
              </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
          </>
        )}

        <DropdownMenuSeparator />
        <DropdownMenuItem
          className="text-destructive focus:text-destructive"
          onSelect={() => router.post(logoutUrl)}
        >
          {t('arqel.auth.menu.logout', 'Log out')}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd packages-js/ui && vitest run src/shell/UserMenu.test.tsx`
Expected: PASS (4 tests). If the `useTheme` mock (which does not throw) makes `useThemeSafe`'s try/catch untested, that's fine — the guard exists for the real no-provider case; the mock validates the happy path.

- [ ] **Step 5: Export UserMenu**

In `packages-js/ui/src/shell/index.ts`, add (keep alphabetical-ish grouping):

```ts
export type { UserMenuProps } from './UserMenu.js';
export { UserMenu } from './UserMenu.js';
```

In `packages-js/ui/src/index.ts`, add near the other shell re-exports (search for `Topbar` and add beside it):

```ts
export type { UserMenuProps } from './shell/UserMenu.js';
export { UserMenu } from './shell/UserMenu.js';
```

- [ ] **Step 6: Remove the theme toggle from Topbar**

In `packages-js/ui/src/shell/Topbar.tsx`, remove the `<Button variant="ghost" size="icon" ...>` theme-toggle block (lines 56-67) — the `{tenantSwitcher}` … `{userMenu}` wrapper keeps only those two. Then remove now-unused imports: `useTheme` (line 10) and `Button` (line 13) **only if nothing else in the file uses them** (after removal, `resolved`/`toggle` are unused — drop the `const { resolved, toggle } = useTheme();` line too). Keep `useArqelTranslations`/`t` only if still referenced; if not, remove them as well. Verify by reading the final file.

Resulting control cluster:

```tsx
      <div className="ml-auto flex min-w-0 items-center gap-2 md:ml-0">
        {tenantSwitcher}
        {userMenu}
      </div>
```

- [ ] **Step 7: Update the Topbar test (theme toggle removed)**

`packages-js/ui/tests/Topbar.test.tsx` asserts the theme toggle button — the `describe('Topbar theme toggle (#236)', ...)` block (~lines 63-120, incl. "toggles the html dark class…" and "translates the theme-toggle accessible name (pt_BR)") and the already-`.skip`ped "toggles theme via the theme button" (~lines 39-47). These now belong to `UserMenu`, not `Topbar`. Delete those theme-toggle test cases (and the now-unused `withTheme`/`ThemeProvider` scaffolding if nothing else in the file uses it). Keep the non-theme Topbar tests (brand, mobile-menu trigger, slots). The theme behavior is already covered by `UserMenu.test.tsx` Step 1.

- [ ] **Step 8: Run ui tests + typecheck**

Run: `cd packages-js/ui && vitest run && pnpm typecheck`
Expected: all pass; no unused-import or type errors, no failing Topbar theme-toggle assertions.

- [ ] **Step 9: Commit**

```bash
git add packages-js/ui/src/shell/UserMenu.tsx packages-js/ui/src/shell/UserMenu.test.tsx packages-js/ui/src/shell/index.ts packages-js/ui/src/index.ts packages-js/ui/src/shell/Topbar.tsx packages-js/ui/tests/Topbar.test.tsx
git commit --signoff -m "feat(ui): add UserMenu dropdown; move theme control out of Topbar

Implements Component 1 from
docs/superpowers/specs/2026-06-25-auth-ui-usermenu-profile-design.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: ProfilePage component (auth JS)

**Files:**
- Create: `packages-js/auth/src/ProfilePage.tsx`
- Create: `packages-js/auth/src/ProfilePage.test.tsx`
- Modify: `packages-js/auth/src/index.ts`

**Interfaces:**
- Consumes: `Button`, `Card`, `CardContent`, `Field`, `FieldError`, `FieldGroup`, `FieldLabel`, `Input` from `@arqel-dev/ui`; `useForm` from `@inertiajs/react`; `useArqelTranslations` from `@arqel-dev/react/utils`; `arqel.auth.profile.*` keys (Task 5).
- Produces: `ProfilePage` + `ProfilePageProps` (exported from `packages-js/auth/src/index.ts`), resolved as Inertia page `arqel-dev/auth/Profile`.

```ts
interface ProfilePageProps {
  user: { id?: number; name?: string; email?: string };
  updateUrl?: string;   // default '/admin/profile'
  passwordUrl?: string; // default '/admin/profile/password'
}
```

- [ ] **Step 1: Write the failing test**

Create `packages-js/auth/src/ProfilePage.test.tsx`:

```tsx
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

const put = vi.fn();
const setData = vi.fn();
vi.mock('@inertiajs/react', () => ({
  useForm: (initial: Record<string, unknown>) => ({
    data: initial,
    setData,
    put,
    processing: false,
    errors: {},
    reset: vi.fn(),
  }),
}));

vi.mock('@arqel-dev/react/utils', () => ({
  useArqelTranslations: () => (_key: string, fallback?: string) => fallback ?? _key,
}));

import { ProfilePage } from './ProfilePage';

describe('ProfilePage', () => {
  it('renders both the account and password sections', () => {
    render(<ProfilePage user={{ id: 1, name: 'Ada', email: 'ada@x.com' }} />);
    expect(screen.getByText(/account data/i)).toBeInTheDocument();
    expect(screen.getByText(/change password/i)).toBeInTheDocument();
  });

  it('submits the account form to updateUrl via put', async () => {
    render(
      <ProfilePage user={{ id: 1, name: 'Ada', email: 'ada@x.com' }} updateUrl="/admin/profile" />,
    );
    await userEvent.click(screen.getByRole('button', { name: /^save$/i }));
    expect(put).toHaveBeenCalledWith('/admin/profile', expect.any(Object));
  });

  it('submits the password form to passwordUrl via put', async () => {
    render(
      <ProfilePage
        user={{ id: 1, name: 'Ada', email: 'ada@x.com' }}
        passwordUrl="/admin/profile/password"
      />,
    );
    await userEvent.click(screen.getByRole('button', { name: /change password/i }));
    expect(put).toHaveBeenCalledWith('/admin/profile/password', expect.any(Object));
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd packages-js/auth && vitest run src/ProfilePage.test.tsx`
Expected: FAIL — cannot resolve `./ProfilePage`.

- [ ] **Step 3: Write ProfilePage.tsx**

Create `packages-js/auth/src/ProfilePage.tsx`:

```tsx
import { useArqelTranslations } from '@arqel-dev/react/utils';
import {
  Button,
  Card,
  CardContent,
  Field,
  FieldError,
  FieldGroup,
  FieldLabel,
  Input,
} from '@arqel-dev/ui';
import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactElement } from 'react';

export interface ProfilePageProps {
  user: { id?: number; name?: string; email?: string };
  /** Submit URL for account data (default `/admin/profile`). */
  updateUrl?: string;
  /** Submit URL for password change (default `/admin/profile/password`). */
  passwordUrl?: string;
}

type ProfileFormData = { name: string; email: string };
type PasswordFormData = {
  current_password: string;
  password: string;
  password_confirmation: string;
};

/**
 * Página de perfil (account settings) bundled de Arqel. Renderiza DENTRO do
 * admin shell (não é a auth split-screen). Dois cards: dados de conta +
 * troca de senha. Resolvida via Inertia em `arqel-dev/auth/Profile`.
 */
export function ProfilePage({
  user,
  updateUrl = '/admin/profile',
  passwordUrl = '/admin/profile/password',
}: ProfilePageProps): ReactElement {
  const t = useArqelTranslations();

  const account = useForm<ProfileFormData>({
    name: user.name ?? '',
    email: user.email ?? '',
  });
  const password = useForm<PasswordFormData>({
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  const submitAccount = (e: FormEvent) => {
    e.preventDefault();
    account.put(updateUrl, { preserveScroll: true });
  };
  const submitPassword = (e: FormEvent) => {
    e.preventDefault();
    password.put(passwordUrl, {
      preserveScroll: true,
      onSuccess: () => password.reset(),
    });
  };

  return (
    <div className="mx-auto flex max-w-2xl flex-col gap-6">
      <h1 className="text-2xl font-semibold">{t('arqel.auth.profile.title', 'Profile')}</h1>

      <Card>
        <CardContent className="pt-6">
          <form onSubmit={submitAccount}>
            <FieldGroup>
              <h2 className="text-lg font-medium">
                {t('arqel.auth.profile.account_section', 'Account data')}
              </h2>
              <Field>
                <FieldLabel htmlFor="profile-name">
                  {t('arqel.auth.profile.name', 'Name')}
                </FieldLabel>
                <Input
                  id="profile-name"
                  value={account.data.name}
                  onChange={(e) => account.setData('name', e.target.value)}
                />
                {account.errors.name && <FieldError>{account.errors.name}</FieldError>}
              </Field>
              <Field>
                <FieldLabel htmlFor="profile-email">
                  {t('arqel.auth.profile.email', 'Email')}
                </FieldLabel>
                <Input
                  id="profile-email"
                  type="email"
                  value={account.data.email}
                  onChange={(e) => account.setData('email', e.target.value)}
                />
                {account.errors.email && <FieldError>{account.errors.email}</FieldError>}
              </Field>
              <Button type="submit" disabled={account.processing}>
                {t('arqel.auth.profile.save', 'Save')}
              </Button>
            </FieldGroup>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardContent className="pt-6">
          <form onSubmit={submitPassword}>
            <FieldGroup>
              <h2 className="text-lg font-medium">
                {t('arqel.auth.profile.password_section', 'Change password')}
              </h2>
              <Field>
                <FieldLabel htmlFor="current-password">
                  {t('arqel.auth.profile.current_password', 'Current password')}
                </FieldLabel>
                <Input
                  id="current-password"
                  type="password"
                  value={password.data.current_password}
                  onChange={(e) => password.setData('current_password', e.target.value)}
                />
                {password.errors.current_password && (
                  <FieldError>{password.errors.current_password}</FieldError>
                )}
              </Field>
              <Field>
                <FieldLabel htmlFor="new-password">
                  {t('arqel.auth.profile.new_password', 'New password')}
                </FieldLabel>
                <Input
                  id="new-password"
                  type="password"
                  value={password.data.password}
                  onChange={(e) => password.setData('password', e.target.value)}
                />
                {password.errors.password && <FieldError>{password.errors.password}</FieldError>}
              </Field>
              <Field>
                <FieldLabel htmlFor="confirm-password">
                  {t('arqel.auth.profile.confirm_password', 'Confirm password')}
                </FieldLabel>
                <Input
                  id="confirm-password"
                  type="password"
                  value={password.data.password_confirmation}
                  onChange={(e) => password.setData('password_confirmation', e.target.value)}
                />
              </Field>
              <Button type="submit" disabled={password.processing}>
                {t('arqel.auth.profile.change_password', 'Change password')}
              </Button>
            </FieldGroup>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd packages-js/auth && vitest run src/ProfilePage.test.tsx`
Expected: PASS (3 tests). If `Card`/`Field*` aren't exported from `@arqel-dev/ui` root, verify with `grep -nE "CardContent|FieldGroup|FieldError" packages-js/ui/src/index.ts` and adjust imports to the exported names (RegisterPage.tsx uses `Card, CardContent, Field, FieldDescription, FieldError, FieldGroup, FieldLabel, Input` — mirror exactly what it imports).

- [ ] **Step 5: Export ProfilePage**

In `packages-js/auth/src/index.ts`, add (keep the existing alpha order):

```ts
export type { ProfilePageProps } from './ProfilePage';
export { ProfilePage } from './ProfilePage';
```

- [ ] **Step 6: Run auth JS tests + typecheck**

Run: `cd packages-js/auth && vitest run && pnpm typecheck`
Expected: all pass.

- [ ] **Step 7: Commit**

```bash
git add packages-js/auth/src/ProfilePage.tsx packages-js/auth/src/ProfilePage.test.tsx packages-js/auth/src/index.ts
git commit --signoff -m "feat(auth): add ProfilePage (account data + password)

Implements Component 3 from
docs/superpowers/specs/2026-06-25-auth-ui-usermenu-profile-design.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: Showcase wiring (demo)

**Files:**
- Modify: `apps/showcase/resources/js/app.tsx` (import `UserMenu` + `ProfilePage`; wire `userMenu`; add `arqel-dev/auth/Profile` to the admin-layout page map)
- Modify: the showcase panel config (PHP) to call `->profile()` — locate via `grep -rn "->login()" apps/showcase/app apps/showcase/routes`

**Interfaces:**
- Consumes: `UserMenu` from `@arqel-dev/ui` (Task 6), `ProfilePage` from `@arqel-dev/auth` (Task 7), `Panel::profile()` (Task 1).

- [ ] **Step 1: Enable ->profile() on the showcase panel (PHP)**

Run `grep -rn "->login()" apps/showcase` to find the panel config. Add `->profile()` to that chain, e.g.:

```php
$panel->login()->registration()->passwordReset()->profile();
```

- [ ] **Step 2: Wire UserMenu into the Topbar (app.tsx)**

In `apps/showcase/resources/js/app.tsx`, import `UserMenu` from `@arqel-dev/ui` (add to the existing `@arqel-dev/ui` import block) and `ProfilePage` from `@arqel-dev/auth` (add to the existing `@arqel-dev/auth` import at line 8).

Add a slot component near `TenantSwitcherSlot` (uses `usePage` to read `auth.user`):

```tsx
interface SharedAuthProps {
  auth?: { user?: { name?: string | null; email?: string | null } };
}

function UserMenuSlot(): JSX.Element {
  const { props } = usePage<SharedAuthProps>();
  return (
    <>
      <LocaleSwitcher />
      <UserMenu
        user={props.auth?.user ?? {}}
        logoutUrl="/admin/logout"
        profileUrl="/admin/profile"
      />
    </>
  );
}
```

Replace `userMenu={<LocaleSwitcher />}` (line 78) with `userMenu={<UserMenuSlot />}`.

- [ ] **Step 3: Register the Profile page under the admin layout**

The Profile page must render inside `adminLayout` (not standalone). Add it to `arqelPages` (the map wrapped by `withAdminLayout`, resolved at `wrappedArqelPages`) — NOT to `authPages`. Find the `arqelPages` definition and add:

```tsx
  'arqel-dev/auth/Profile': async () => ({ default: ProfilePage as ComponentType<unknown> }),
```

If `arqelPages` is imported from elsewhere and not editable inline, instead add a dedicated wrapped entry: define `const profilePages = { 'arqel-dev/auth/Profile': withAdminLayout(async () => ({ default: ProfilePage as ComponentType<unknown> })) };` and spread it into the resolver's page map alongside `wrappedArqelPages`, `authPages`, `userPages`. Read the resolver assembly (the `resolve:` callback of `createInertiaApp`) and add `...profilePages` where the maps are merged.

- [ ] **Step 4: Typecheck the showcase**

Run: `cd apps/showcase && pnpm typecheck` (or the repo-root `pnpm typecheck` filtered to showcase)
Expected: no type errors. Confirm `props.auth.user` typing matches (the middleware shares `{id,name,email}`).

- [ ] **Step 5: Commit**

```bash
git add apps/showcase/resources/js/app.tsx apps/showcase/app apps/showcase/routes
git commit --signoff -m "feat(demo): wire UserMenu + Profile page into showcase panel

Refs docs/superpowers/specs/2026-06-25-auth-ui-usermenu-profile-design.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 9: Full-suite verification + lint

**Files:** none (verification only)

- [ ] **Step 1: PHP — Pest + PHPStan on core + auth**

Run:
```bash
cd packages/core && vendor/bin/pest && vendor/bin/phpstan analyse
cd packages/auth && vendor/bin/pest && vendor/bin/phpstan analyse
```
Expected: all green. Fix any PHPStan level-max findings (types on new methods).

- [ ] **Step 2: JS — vitest + typecheck on ui + auth**

Run:
```bash
cd packages-js/ui && vitest run && pnpm typecheck
cd packages-js/auth && vitest run && pnpm typecheck
```
Expected: all green.

- [ ] **Step 3: Lint (Pint + Biome)**

Run (host `pint`/`biome` may be broken — use the dogfood container as the docs loop does, or repo scripts):
```bash
vendor/bin/pint --test packages/core packages/auth
pnpm biome check packages-js/ui packages-js/auth apps/showcase/resources/js
```
Expected: clean. Apply `vendor/bin/pint` / `biome check --write` if formatting drifts.

- [ ] **Step 4: i18n parity gate**

Run the core suite's lang-parity test if present (`grep -rl "parity" packages/core/tests`); else re-run the Task 5 Step 4 parity check. Confirm EN and pt_BR have identical key sets for the new blocks.

- [ ] **Step 5: Final commit (if lint applied changes)**

```bash
git add -A
git commit --signoff -m "chore: lint + typecheck pass for Auth UI (UserMenu + Profile)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage:**
- UserMenu (trigger/header/profile/theme/logout, a11y, fallbacks) → Task 6. ✅
- Topbar theme-toggle removal → Task 6 Step 6. ✅
- UserMenu exports + showcase wiring → Task 6 Step 5, Task 8. ✅
- Profile backend (`Routes::registerProfile`, `ProfileController`, gating) → Task 3. ✅
- `Panel::profile()`/`profileEnabled()` → Task 1. ✅
- FormRequests (UpdateProfile/UpdatePassword) → Task 2. ✅
- Profile frontend (`ProfilePage`, two cards, i18n, export) → Task 7. ✅
- Showcase `->profile()` + resolver + admin layout → Task 8. ✅
- i18n EN+pt_BR parity (menu + profile + flash + attributes) → Task 5. ✅
- Tests (Pest gating/validation/password; vitest UserMenu + ProfilePage) → Tasks 4, 6, 7. ✅

**Deviations from spec (intentional, noted in Global Constraints):**
- Inertia page name `arqel-dev/auth/Profile` (not `Arqel/Profile`) — matches the real resolver convention.
- Theme i18n keys are fresh `arqel.auth.menu.theme_*` (the spec's `arqel.theme.light/dark/system` don't exist; real ones are `arqel.theme.toggle.*`).
- Theme group uses `useTheme().{theme,setTheme}` — verified present on the hook.

**Placeholder scan:** No TBD/TODO; every code step shows full content; test code is concrete.

**Type consistency:** `profileEnabled()`/`profile()` (Task 1) consumed verbatim in Tasks 3 & 8. `arqel.auth.menu.*`/`arqel.auth.profile.*` keys defined in Task 5 consumed verbatim in Tasks 6 & 7. `updateUrl`/`passwordUrl` prop names consistent between ProfileController props (Task 3), ProfilePageProps (Task 7), and tests. Route names `arqel.auth.profile.{show,update,password}` consistent between Task 3 and Task 4.

**Grounded facts (verified against the code while writing this plan):**
- `@arqel-dev/ui` exports `Card`, `CardContent`, `Field`, `FieldError`, `FieldGroup`, `FieldLabel`, `Input` (index.ts:46-57) — Task 7 imports mirror RegisterPage.tsx.
- `packages-js/ui/tests/Topbar.test.tsx` DOES assert the theme toggle (describe block ~lines 63-120 + a skipped case ~39-47) — Task 6 Step 7 removes those cases.
- shadcn `dropdown-menu.tsx` + `separator.tsx` exist; `useTheme()` returns `{ theme, resolved, setTheme, toggle }`.

**Open verification the implementer must confirm at runtime (flagged inline, not placeholders):**
- Task 8 Step 3: exact shape of the showcase `createInertiaApp` resolver merge (read before editing).
- Task 8 Step 1: exact location of the showcase panel's `->login()` chain (PHP) to append `->profile()`.

# Auth UI — UserMenu + Profile (Design)

**Date:** 2026-06-25 · **Status:** approved (design), pending spec review

## Problem

When a developer uses Arqel and enables login, the framework ships the auth
*pages* (Login/Register/Forgot/Reset/Verify), the `LogoutController` + `POST`
logout route, and exposes `auth.user` (`{id, name, email}`) to the frontend via
Inertia shared props — **but there is no UI** to:

- show the authenticated user / log out (the Topbar `userMenu` slot is empty by
  design; the showcase passes only `<LocaleSwitcher/>`), and
- edit the user's own account (name, email, password).

This design adds the two missing UI pieces, reusing existing backend wiring.
It also relocates the theme control out of the Topbar into the new UserMenu.

## Scope

In: `UserMenu` dropdown component; a framework-registered Profile page
(account data + password) with controller, routes, FormRequests; showcase
wiring; i18n (en + pt_BR parity); tests. Out: avatar upload/image; 2FA;
email-change re-verification flow (email updates directly for now).

## Architecture

Two deliverables in their existing-pattern homes:

1. **`UserMenu`** — `packages-js/ui/src/shell/UserMenu.tsx` (the `ui` package
   already depends on `@arqel-dev/react` for `useTheme`/`useArqelTranslations`
   and `@inertiajs/react` for `router`/`Link`).
2. **Profile** — backend in `packages/auth` (PHP), page in
   `packages-js/auth` (React), gated by a new `Panel::profileEnabled()`,
   mirroring the existing `registerLogin`/`registerPasswordReset` modules.

---

## Component 1 — UserMenu

`packages-js/ui/src/shell/UserMenu.tsx`, rendered into the Topbar `userMenu`
slot. Built on the vendored shadcn `DropdownMenu` (`packages-js/ui/src/shadcn`).

### Props

```ts
interface UserMenuProps {
  user: { name?: string | null; email?: string | null };
  logoutUrl: string;        // e.g. '/admin/logout' (POST)
  profileUrl?: string;      // 'Profile' item shown only when present
  className?: string;
}
```

### Structure (DropdownMenuContent align="end")

- **Trigger**: ghost button — avatar fallback (first letter of name/email in a
  rounded `bg-muted text-muted-foreground` circle) + name (`hidden md:inline`) +
  chevron. `aria-label={t('arqel.auth.menu.open','Open user menu')}`. No avatar
  image (out of scope).
- **Header** (non-interactive): name (`font-medium`) + email
  (`text-xs text-muted-foreground truncate`).
- `Separator`.
- **"Profile"** — only if `profileUrl`; `<Link href={profileUrl}>` (SPA nav).
- `Separator`.
- **Theme group** — `useTheme()` from `@arqel-dev/react/providers`
  (`{ theme, setTheme }`); a `DropdownMenuRadioGroup value={theme}` with three
  `DropdownMenuRadioItem`s: Light (`setTheme('light')`), Dark (`setTheme('dark')`),
  System (`setTheme('system')`), each labeled via the **existing**
  `arqel.theme.light/dark/system` keys (already added in the i18n loop's
  ThemeToggle). The active theme is checked. If `useTheme` is unavailable
  (mounted outside a ThemeProvider), the group is omitted — `useTheme` must not
  throw; verify the hook returns a safe default, else guard the group.
- `Separator`.
- **"Log out"** — `onSelect={() => router.post(logoutUrl)}`
  (`@inertiajs/react`; CSRF + redirect handled by Inertia). Styled destructive
  (`text-destructive`).

### Fallbacks / a11y

- name null → show email; both null → `t('arqel.auth.menu.account','Account')`.
- Radix DropdownMenu provides roles + keyboard nav.

### Topbar change

Remove the standalone theme toggle button (`packages-js/ui/src/shell/Topbar.tsx`
~lines 54-67, the `☀/☾` `<Button onClick={toggle}>`). Theme now lives in the
UserMenu. Topbar keeps `{tenantSwitcher}` and `{userMenu}`. Drop the now-unused
`useTheme` import from Topbar if nothing else uses it.

### Exports + showcase wiring

- Export `UserMenu` from `packages-js/ui/src/shell/index.ts` and the package
  root `index.ts`.
- Showcase (`apps/showcase/resources/js/app.tsx`): replace
  `userMenu={<LocaleSwitcher/>}` with a small wrapper rendering both, reading
  `auth.user` via `usePage`:
  ```tsx
  userMenu={<><LocaleSwitcher/><UserMenu
    user={usePage().props.auth.user ?? {}}
    logoutUrl="/admin/logout" profileUrl="/admin/profile" /></>}
  ```

---

## Component 2 — Profile (backend)

`packages/auth`. Mirror the modular pattern in `packages/auth/src/Routes.php`.

### Routes — `Routes::registerProfile(?Panel $panel)`

Gated by `$panel?->profileEnabled()`; called from `Routes::register()` when
enabled. `profileUrl` derived from the login URL via the existing
`deriveSiblingUrl($panel->getLoginUrl(), 'profile')` (→ `/admin/profile`).
Middleware: `web`, `HandleArqelInertiaRequests`, `auth:{guard}`.

- `GET  {profileUrl}`          → `ProfileController@show`           name `arqel.auth.profile.show`
- `PUT  {profileUrl}`          → `ProfileController@update`         name `arqel.auth.profile.update`
- `PUT  {profileUrl}/password` → `ProfileController@updatePassword` name `arqel.auth.profile.password`

### Panel — `packages/core/src/Panel/Panel.php`

Add `private bool $profileEnabled = false;`, a `profile(bool $enabled = true): self`
setter, and `profileEnabled(): bool` (mirrors `loginEnabled`/`registrationEnabled`).

### ProfileController (`packages/auth/src/Http/Controllers/ProfileController.php`)

- `show(Request)` → `Inertia::render('Arqel/Profile', ['user' => $request->user()->only('id','name','email')])`.
- `update(UpdateProfileRequest)` → set name/email, `save()`, redirect back with
  `flash.status = __('arqel::messages.flash.profile.updated')`.
- `updatePassword(UpdatePasswordRequest)` → `$user->password = Hash::make($validated['password']); save()`,
  redirect back with `flash.status = __('arqel::messages.flash.profile.password_updated')`.

### FormRequests (`packages/auth/src/Http/Requests/`)

- `UpdateProfileRequest`: `name` required|string|max:255; `email`
  required|email|max:255|`Rule::unique(users)->ignore($user->id)`. `authorize()`
  returns `$this->user() !== null`. `attributes()`/`messages()` via `arqel::` keys.
- `UpdatePasswordRequest`: `current_password` required|`current_password`;
  `password` required|confirmed|`Password::defaults()`. Same localized
  attributes/messages.

---

## Component 3 — Profile (frontend)

`packages-js/auth/src/ProfilePage.tsx`, resolved as `Arqel/Profile`. Renders
*inside* the admin shell (not the split-screen auth layout — profile is a
panel page). Reuses the shadcn Input/Button/Label primitives used by the
existing auth pages.

- Title "Profile" + two sections (cards):
  - **Account data**: `name`, `email` (Inertia `useForm`), "Save" button,
    inline validation errors + success flash.
  - **Change password**: `current_password`, `password`,
    `password_confirmation`, "Change password" button, inline errors + flash.
- Props: `user`, optional `updateUrl`/`passwordUrl` (defaults derived).
- i18n via `useArqelTranslations` (`arqel.auth.profile.*`).
- Export from `packages-js/auth/src/index.ts`.

### Showcase wiring

Enable `->profile()` on the panel config; ensure the Inertia page resolver
resolves `Arqel/Profile` to `ProfilePage` (the showcase resolver already maps
`Arqel/*` framework pages — verify and add if missing).

---

## i18n keys (en + pt_BR, parity, correct diacritics)

- `arqel.auth.menu.open` ('Open user menu' / 'Abrir menu do usuário'),
  `.profile` ('Profile' / 'Perfil'), `.logout` ('Log out' / 'Sair'),
  `.account` ('Account' / 'Conta').
- Reuse existing `arqel.theme.light/dark/system`.
- `arqel.auth.profile.{title,account_section,name,email,save,password_section,
  current_password,new_password,confirm_password,change_password}`.
- `arqel::messages.flash.profile.updated` / `.password_updated`.
- FormRequest `attributes`/`messages` keys under `arqel::`.

---

## Testing

- **Pest** (`packages/auth`): ProfileController — update OK; validation fails
  (missing name, bad email); duplicate email rejected; wrong current_password
  rejected; password update OK; **gating**: routes return 404 / are absent when
  `profileEnabled()` is false. FormRequests localize under `app()->setLocale('pt_BR')`.
- **vitest** (`packages-js/ui`): UserMenu — logout item calls `router.post(logoutUrl)`;
  Profile item hidden when `profileUrl` absent, shown when present; theme radio
  calls `setTheme`; header falls back to email/Account when name null.
- **vitest** (`packages-js/auth`): ProfilePage renders both forms; submit calls
  the right `useForm` post/put.
- Biome + tsc + root pint clean; en/pt_BR lang parity.

## Risks / notes

- `getLabel()`-style gotcha N/A here. The Topbar test may assert the theme
  button — check `packages-js/ui/tests` and update when removing it.
- The showcase admin layout wraps pages in the AppShell (now padded), so the
  Profile page needs no extra outer padding.
- Keep `UserMenu` usable standalone (no profile, no theme provider) — both are
  conditional.

# SKILL.md — @arqel-dev/auth

> Contexto canónico para AI agents.

## Purpose

`@arqel-dev/auth` publica os componentes React de autenticação que o pacote PHP `arqel-dev/auth` renderiza via Inertia. Junto com o backend, fecha a lacuna de DX vs Filament/Nova: `composer require arqel-dev/arqel` + `Panel::configure()->login()` resulta em `/admin/login` funcional out-of-the-box, com layout idiomático shadcn.

## Key Contracts

### Páginas Inertia exportadas

Todas são componentes React renderizados pelo backend via `Inertia::render('arqel-dev/auth/<Page>', [...])`:

- `<LoginPage />` — email + password + "remember me" + submit
- `<RegisterPage />` — name + email + password + password_confirmation
- `<ForgotPasswordPage />` — email para envio de magic link
- `<ResetPasswordPage />` — token + email + nova senha + confirmação
- `<VerifyEmailNoticePage />` — aviso pós-registro com botão "reenviar"

### Layout

Cada página usa o block shadcn **`login-04`** (split-screen): `Card` à esquerda com formulário, hero illustration à direita (configurável via prop `heroImageSrc`, default `/login-hero.svg`). Para `RegisterPage`, equivalente `signup-04`. Layout 100% shadcn — sem CSS namespaced custom; herda tokens do host (`--background`, `--primary`, `--card`, etc.).

```tsx
import { LoginPage } from '@arqel-dev/auth';

export default function Login(props: {
  canRegister: boolean;
  canResetPassword: boolean;
  loginUrl: string;
  registerUrl?: string;
  forgotPasswordUrl?: string;
}) {
  return <LoginPage {...props} heroImageSrc="/login-hero.svg" />;
}
```

### Imports de UI

Tudo que é primitivo vem de `@arqel-dev/ui` — o pacote auth não duplica componentes:

```tsx
import {
  Button,
  Card, CardContent,
  Field, FieldGroup, FieldLabel, FieldError, FieldDescription,
  Input,
} from '@arqel-dev/ui';
```

### Props comuns

Injetadas pelo backend via Inertia props:

- `loginUrl?: string` — endpoint de submit do `LoginPage` (default `/admin/login`)
- `registerUrl?: string` — endpoint do `RegisterPage`
- `forgotPasswordUrl?: string` — endpoint do `ForgotPasswordPage`
- `canRegister?: boolean` — exibe link "Criar conta" no `LoginPage`
- `canResetPassword?: boolean` — exibe link "Esqueci minha senha"
- `title?: string` — header customizável
- `heroImageSrc?: string` — illustration lateral (block `login-04`/`signup-04`)

### Lógica

Cada página usa `useForm()` do `@inertiajs/react` para state, submit e errors. Errors são renderizados via `<FieldError>` com `aria-describedby` automático. Submit chama `form.post(loginUrl)` (ou equivalente).

### Backend pair

O contrapartida PHP vive em `packages/auth/`:

- `Arqel\Auth\Http\Controllers\LoginController` → `Inertia::render('arqel-dev/auth/Login', [...])`
- `RegisterController`, `ForgotPasswordController`, `ResetPasswordController`, `VerifyEmailController`

O page registry do app deve incluir as auth pages — `createArqelApp({ pages })` aceita o glob de auth (ver `@arqel-dev/react`).

## Conventions

- TypeScript strict; peer deps: `react ^19.0.0`, `@inertiajs/react ^2.0.0`, `@arqel-dev/ui` (workspace:*)
- Sem dependências runtime próprias além de peers — toda a estética vem de `@arqel-dev/ui`
- Estado via `useForm()` Inertia; nunca state local para campos de auth
- Acessibilidade: cada `<Field>` agrupa label + input + error com IDs corretos

## Anti-patterns

- ❌ Adicionar libs de fetch (TanStack Query/SWR) — usar Inertia (ADR-001)
- ❌ Re-implementar primitivas (`Button`, `Input`, `Card`) — sempre importar de `@arqel-dev/ui`
- ❌ CSS hardcoded ou classes `arqel-login-*` — substituídas por shadcn tokens (`bg-card`, `text-foreground`, etc.)
- ❌ Submit manual via `fetch`/`axios` — usa `form.post()` do `useForm` Inertia
- ❌ Validação client-only — server FormRequest é a fonte de verdade; client só reflete `form.errors`

## Related

- Tickets: `PLANNING/08-fase-1-mvp.md` §AUTH-006
- ADRs: ADR-001 (Inertia-only), ADR-008 (testes obrigatórios)
- PHP: `packages/auth/src/Http/Controllers/LoginController.php` (e siblings)
- UI primitives: `@arqel-dev/ui` — blocks `login-04` / `signup-04`
- Source: `packages-js/auth/src/`

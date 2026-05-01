# SKILL.md — @arqel/auth

> Contexto canónico para AI agents.

## Purpose

`@arqel/auth` publica os componentes React de autenticação que o pacote PHP `arqel/auth` renderiza via Inertia (componente `arqel/auth/Login`). Junto com `arqel/auth` (PHP), fecha a lacuna de DX vs Filament/Nova: `composer require arqel/arqel` + `Panel::configure()->login()` resulta em `/admin/login` funcional out-of-the-box.

## Key Contracts

### `<LoginPage />`

Página Inertia com email + senha + lembrar-me + submit.

```tsx
import { LoginPage } from '@arqel/auth';

export default function Login(props: { canRegister: boolean; canResetPassword: boolean; loginUrl: string }) {
  return <LoginPage {...props} />;
}
```

Props:

- `canRegister?: boolean` — exibe link "Criar conta" (default `false`).
- `canResetPassword?: boolean` — exibe link "Esqueci minha senha" (default `false`).
- `loginUrl?: string` — URL de submit (default `/admin/login`).
- `registerUrl?: string` — URL do link de registro.
- `forgotPasswordUrl?: string` — URL do link de reset.
- `title?: string` — título do header.

## Conventions

- TypeScript strict, peer deps `react ^19.0.0` + `@inertiajs/react ^2.0.0`.
- Sem dependências runtime — apenas peers.
- Estilização via classes CSS namespaced `arqel-login-*` (host app define a estética).
- `useForm()` Inertia gerencia state, submit e errors.

## Anti-patterns

- ❌ Adicionar libs de fetch (TanStack Query/SWR) — usar Inertia (ADR-001).
- ❌ Estilização inline ou Tailwind hardcoded — manter CSS opt-in.

## Related

- Tickets: `PLANNING/08-fase-1-mvp.md` §AUTH-006.
- ADRs: ADR-001 (Inertia-only), ADR-008 (testes obrigatórios).
- PHP: `packages/auth/src/Http/Controllers/LoginController.php`.
- Source: `packages-js/auth/src/`.

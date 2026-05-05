# SKILL.md — @arqel-dev/react

> Contexto canónico para AI agents.

## Purpose

`@arqel-dev/react` é a fundação do lado React do Arqel: bindings Inertia, providers (panel/tenant/theme), contexts (panel/resource/tenant), devtools hook e utilities (`route`, `translate`, `serializeFields`). É o pacote consumido por `@arqel-dev/hooks`, `@arqel-dev/ui`, `@arqel-dev/auth`, `@arqel-dev/fields`.

## Status

**Entregue (REACT-001..004 + DEVTOOLS-002..007):**

- `createArqelApp(options)` — bootstrap Inertia com defaults Arqel: title callback, page resolver, `<ArqelProvider>` wrap, progress bar, SSR hydrate vs createRoot, devtools auto-install
- `<ArqelProvider>` — wraps `PanelContext` + `TenantContext` + `ThemeProvider`
- `<ThemeProvider>` — light/dark/system com `localStorage` persist (`arqel-theme`) + `prefers-color-scheme` listener; aplica `.dark` em `document.documentElement` para FOUC mitigation
- `PanelContext`/`ResourceContext`/`TenantContext` + hooks `usePanel`/`useRequiredPanel`, `useResourceContext`/`useRequiredResource`, `useTenant`
- `useTheme()` hook → `{ theme, resolved, setTheme, toggle }`
- `route(name, params, absolute)` — typed wrapper sobre Ziggy
- `translate(dict, key, options)` + `useTranslator(dict)` — i18n com placeholders `:name`
- `buildInitialFormState(fields, record)`, `indexFieldsByName(fields)`, `fieldsVisibleIn(fields, context)`
- DevTools hook em `window.__ARQEL_DEVTOOLS_HOOK__` (DEV-only) com snapshots, navigation history, performance metrics

### Build / tsup

`tsup.config.ts` agora ativa **`splitting: true`** — necessário para que entries paralelos (`inertia`, `providers`, `context`, `utils`, `devtools`) **partilhem o mesmo módulo de `ThemeProvider`/`useTheme()`**. Sem splitting, cada entry gerava sua própria cópia do contexto e `useTheme()` em código que importava de `/inertia` lia um Provider diferente do montado pelo `<ArqelProvider>` em `/providers`, devolvendo `undefined`. Com `splitting`, o chunk compartilhado é deduplicado.

## Key Contracts

### Bootstrap

```ts
// resources/js/app.tsx
import { createArqelApp } from '@arqel-dev/react/inertia';
import { arqelPages } from '@arqel-dev/ui/pages';
import { authPages } from '@arqel-dev/auth';

const userPages = import.meta.glob<{ default: ComponentType }>('./Pages/**/*.tsx');

createArqelApp({
  appName: 'Acme Admin',
  pages: { ...arqelPages, ...authPages, ...userPages },
  defaultTheme: 'system',
  progress: { color: '#4F46E5' },
});
```

`createArqelApp({ pages, layout?, appName?, defaultTheme?, progress? })`:

- `pages` — registry obrigatório; deve incluir `arqelPages` (de `@arqel-dev/ui/pages`) + auth pages (de `@arqel-dev/auth`) + páginas do app
- `layout?` — layout root opcional aplicado a cada página
- `appName?` — usado em `document.title`
- `defaultTheme?` — `'light' | 'dark' | 'system'` (default `'system'`)
- `progress?` — config Inertia progress bar

### Theme

```tsx
import { useTheme } from '@arqel-dev/react/providers';

function ThemeToggle() {
  const { theme, resolved, setTheme, toggle } = useTheme();
  return <button onClick={toggle}>{resolved}</button>;
}
```

`localStorage['arqel-theme']` persiste a preferência. Aplica `.dark` em `<html>` antes do React hydratar (script inline em `app.blade.php`) para evitar flash.

### Contexts e Utilities

```tsx
import { usePanel, useResourceContext } from '@arqel-dev/react/context';
import { route, translate, useTranslator, buildInitialFormState } from '@arqel-dev/react/utils';

const url = route('arqel.resources.edit', { resource: 'users', id: 1 });
const initial = buildInitialFormState(fields, record);
```

### DevTools

```ts
import { installDevToolsHook, installInertiaBridge, installPerformanceObserver } from '@arqel-dev/react/devtools';

const hook = installDevToolsHook('0.10.0-rc.1'); // só em DEV
if (hook) {
  installInertiaBridge(hook, inertiaRouter);
  installPerformanceObserver(hook);
}
```

`createArqelApp` chama tudo automaticamente. Hook expõe: `getState()`, `subscribe(cb)`, `setPageProps`, `recordNavigation`, `pushSnapshot`/`getSnapshots()` (ring buffer 50), `getPerformanceMetrics()` (LCP/INP/FID/CLS/navigationTime). Em produção: tudo no-op + tree-shaken pelo Vite.

## Conventions

- **Subpath imports** preferidos para tree-shaking (`/inertia`, `/providers`, `/context`, `/utils`, `/devtools`)
- `peerDependencies` para React/Inertia
- Contexts são `null`-by-default (não throw); `useRequired*` para callsites que exigem
- Theme-aware components leem `useTheme().resolved` (sempre `'light'` | `'dark'`)
- `splitting: true` no tsup é load-bearing — não reverter sem confirmar que `useTheme()` ainda funciona cross-entry

## Anti-patterns

- ❌ Importar de `dist/` — usa apenas exports `./*` declarados
- ❌ Múltiplos `<ArqelProvider>` numa árvore — confunde contexts
- ❌ `createRoot` manual — `createArqelApp` lida com SSR/hydrate
- ❌ Hardcode de `arqel-theme` storage key — usa `<ThemeProvider storageKey="...">` para multi-app
- ❌ `route()` sem Ziggy — instala `tightenco/ziggy`, ou usa `<Link href="...">` literal
- ❌ Desativar `splitting` no tsup — quebra `useTheme()` cross-entry

## Related

- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §REACT-001..004, §DEVTOOLS-002..007
- API: [`PLANNING/06-api-react.md`](../../PLANNING/06-api-react.md)
- Source: [`packages-js/react/src/`](src/)
- Tests: [`packages-js/react/tests/`](tests/)

# SKILL.md — @arqel-dev/react

> Contexto canónico para AI agents.

## Purpose

`@arqel-dev/react` é a fundação do lado React: bindings Inertia, providers (panel/tenant/theme), contexts (panel/resource/tenant) e utilities compartilhadas (`route`, `translate`, `serializeFields`). É o pacote que `@arqel-dev/hooks`, `@arqel-dev/ui`, `@arqel-dev/fields` (JS side) consomem.

## Status

**Entregue (REACT-001..004):**

- `createArqelApp(options)` — bootstrap Inertia com defaults Arqel (title callback, page resolver, `<ArqelProvider>` wrap, progress bar, SSR hydrate vs createRoot)
- `<ArqelProvider>` — wraps `PanelContext` + `TenantContext` + `ThemeProvider`
- `<ThemeProvider>` — light/dark/system com `localStorage` persist + `prefers-color-scheme` listener; classe aplicada em `document.documentElement` para FOUC mitigation
- `PanelContext`/`ResourceContext`/`TenantContext` com hooks `usePanel/useRequiredPanel`, `useResourceContext/useRequiredResource`, `useTenant`
- `useTheme()` hook com `{ theme, resolved, setTheme, toggle }`
- `route(name, params, absolute)` — typed wrapper sobre Ziggy, throws clear error quando não disponível
- `translate(dict, key, options)` + `useTranslator(dict)` — i18n com placeholders `:name`
- `buildInitialFormState(fields, record)` — seed para `useArqelForm` (HOOKS-002)
- `indexFieldsByName(fields)` / `fieldsVisibleIn(fields, context)`
- 20 testes Vitest passando (jsdom + @testing-library/react)

**Entregue (DEVTOOLS-002):**

- Subpath `@arqel-dev/react/devtools` exporta `installDevToolsHook(version)`,
  `createDevToolsHook`, `ArqelDevToolsHook`, `ArqelDevToolsState`.
- `createArqelApp` chama `installDevToolsHook` automaticamente no bootstrap.
- 5 testes Vitest adicionais cobrindo install/no-op/idempotência/subscribe.

**Por chegar:**

- Hooks reusáveis em `@arqel-dev/hooks` (HOOKS-001+)
- UI components em `@arqel-dev/ui` (UI-001+)

## DevTools hook (DEVTOOLS-002 + DEVTOOLS-003 + DEVTOOLS-006 + DEVTOOLS-007)

Em modo desenvolvimento, `@arqel-dev/react` expõe um hook em
`window.__ARQEL_DEVTOOLS_HOOK__` para a extensão de browser
(`@arqel-dev/devtools-extension`) inspecionar o estado do panel/resource.

```ts
import { installDevToolsHook, installInertiaBridge } from '@arqel-dev/react/devtools';

const hook = installDevToolsHook('0.10.0-rc.1');
// → cria { version, getState(), subscribe(cb), setPageProps(...), recordNavigation(...) }
//   apenas quando import.meta.env.DEV.

if (hook) {
  installInertiaBridge(hook, inertiaRouter); // captura page props + history
}
```

`ArqelDevToolsState` agora carrega:

- `panel`/`resource` — id ativo
- `sharedProps` — Record canônico (auth, flash, errors, csrf_token, arqel)
- `pageProps` — payload completo do `<Page>` Inertia ativo
- `currentPath` — `page.url`
- `navigationHistory` — ring buffer de até `NAVIGATION_HISTORY_LIMIT` (20) entradas, cada uma com `{ path, timestamp, durationMs? }`

Métodos novos no hook:

- `setPageProps(pageProps, sharedProps, currentPath)` — invocado pelo bridge a cada `navigate`
- `recordNavigation(entry)` — append no ring buffer; entradas mais antigas saem na ordem de inserção

`installInertiaBridge(hook, router, { now? })` ouve `start` (para medir
duração) e `navigate` (para popular `setPageProps` + `recordNavigation`).
Retorna teardown.

Em produção, tudo desaparece: `installDevToolsHook` retorna `undefined`,
o branch é eliminado pelo Vite, e a extensão simplesmente não detecta o
runtime.

`createArqelApp` já chama `installDevToolsHook` + `installInertiaBridge`
por você quando o hook for instalado — apps com o bootstrap padrão
recebem a integração de graça.

### Snapshots para time-travel (DEVTOOLS-006)

A cada `navigate` do Inertia o bridge faz `pushSnapshot` com o payload
completo (`pageProps`, `sharedProps`, `url`, `durationMs?`). O hook
mantém um ring buffer de até `SNAPSHOT_HISTORY_LIMIT` (50) entradas e
expõe `getSnapshots()` em ordem reverso-cronológica (mais recente
primeiro). A extensão consome via `hook.getSnapshots()` e renderiza
timeline + replay.

```ts
hook.pushSnapshot({
  id: 'snap-1700000000-1',
  timestamp: 1_700_000_000,
  url: '/admin/users',
  pageProps: { users: [...] },
  sharedProps: { auth: { id: 1 } },
  durationMs: 42,
});
hook.getSnapshots()[0]?.url; // '/admin/users'
```

### Performance metrics (DEVTOOLS-007)

`installPerformanceObserver(hook)` registra `PerformanceObserver`s para
`largest-contentful-paint`, `first-input`, `event` (INP), `layout-shift`
e `paint`. SSR-safe (no-op quando `window` ou `PerformanceObserver`
ausentes). `createArqelApp` chama automaticamente se o hook está
instalado e `'PerformanceObserver' in window`.

```ts
import { installPerformanceObserver } from '@arqel-dev/react/devtools';

installPerformanceObserver(hook);
hook.getPerformanceMetrics();
// → { lcp, inp, fid, cls, navigationTime } — todos number | null
```

`hook.recordPerformanceMetric(name, value)` permite custom telemetry
(testes, fallbacks).

## Key Contracts

### Bootstrap

```ts
// resources/js/app.tsx
import { createArqelApp } from '@arqel-dev/react/inertia';

const userPages = import.meta.glob<{ default: ComponentType }>('./Pages/**/*.tsx');

createArqelApp({
  appName: 'Acme Admin',
  pages: userPages,
  defaultTheme: 'system',
  progress: { color: '#4F46E5' },
});
```

### Theme

```tsx
import { useTheme } from '@arqel-dev/react/providers';

function ThemeToggle() {
  const { theme, resolved, setTheme, toggle } = useTheme();

  return (
    <button onClick={toggle} aria-label={`Switch to ${resolved === 'dark' ? 'light' : 'dark'}`}>
      {theme === 'system' ? `auto (${resolved})` : theme}
    </button>
  );
}
```

`localStorage['arqel-theme']` persiste a preferência. O `app.blade.php` (CORE-012) tem um inline script que aplica a classe correta antes de React hydratar, evitando flash.

### Contexts

```tsx
import { usePanel, useResourceContext } from '@arqel-dev/react/context';

function PanelHeader() {
  const panel = usePanel();
  return <h1>{panel?.brand.name ?? 'Arqel'}</h1>;
}
```

### Utilities

```ts
import { route } from '@arqel-dev/react/utils';

const url = route('arqel.resources.edit', { resource: 'users', id: 1 });
```

```ts
import { translate, useTranslator } from '@arqel-dev/react/utils';

const t = useTranslator(translations); // translations from SharedProps
t('arqel.actions.create'); // → "Create"
t('hello', { name: 'World' }); // → "Hello World"
```

```ts
import { buildInitialFormState } from '@arqel-dev/react/utils';

const initial = buildInitialFormState(fields, record);
// boolean → false, multiSelect/hasMany → [], number/currency → null, others → ''
// record values override defaults; defaults override empties
```

## Conventions

- **Subpath imports** preferidos para tree-shaking
- `peerDependencies` para React/Inertia — apps escolhem suas versões
- Contexts são `null`-by-default (não throw); `useRequired*` para callsites que exigem o valor
- Theme-aware components leem `useTheme().resolved` (sempre `light` | `dark`)

## Anti-patterns

- ❌ **Importar de `dist/`** — usa apenas exports `./*` declarados
- ❌ **Múltiplos `<ArqelProvider>`** numa árvore — confunde contexts
- ❌ **`createRoot` manual** quando `createArqelApp` já lida com SSR
- ❌ **Hardcode de `arqel-theme` storage key** — usa `<ThemeProvider storageKey="...">` quando precisa de prefix multi-app
- ❌ **`route()` sem Ziggy** — instala `tightenco/ziggy` e publica os assets, ou cai para `<Link href="/admin/users">` literal

## Related

- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §REACT-001..004
- API: [`PLANNING/06-api-react.md`](../../PLANNING/06-api-react.md)
- Source: [`packages-js/react/src/`](src/)
- Tests: [`packages-js/react/tests/`](tests/)

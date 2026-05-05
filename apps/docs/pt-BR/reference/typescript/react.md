# `@arqel-dev/react` — API Reference

React + Inertia bindings, providers, contexts, utilities. 5 entry points subpath.

```ts
import { createArqelApp } from '@arqel-dev/react/inertia';
import { ArqelProvider, ThemeProvider, useTheme } from '@arqel-dev/react/providers';
import { usePanel, useResourceContext, useTenant } from '@arqel-dev/react/context';
import { route, translate, useTranslator, buildInitialFormState } from '@arqel-dev/react/utils';
```

`peerDependencies`: React 19+, `@inertiajs/react` 2+.

## Inertia bootstrap

### `createArqelApp(options)`

Bootstrapper de Inertia com defaults Arqel — função principal de entrada do client.

```ts
function createArqelApp(options: {
  appName: string;
  pages: Record<string, () => Promise<unknown>>;        // import.meta.glob('./Pages/**/*.tsx')
  layout?: ComponentType<{ children: ReactNode }>;      // layout root (default: <AppShell>)
  defaultTheme?: 'light' | 'dark' | 'system';           // default: 'system'
  progress?: { color?: string; showSpinner?: boolean } | false;
  ssrRender?: boolean;
  resolve?: (name: string) => unknown;
}): void
```

O `pages` registry deve combinar:

```ts
import { arqelPages } from '@arqel-dev/ui/pages';        // Index/Create/Edit/Show + 404/403/500
import { authPages } from '@arqel-dev/auth';             // Login/Register/Forgot/Reset/Verify

createArqelApp({
  appName: 'Acme Admin',
  pages: { ...arqelPages, ...authPages, ...import.meta.glob('./Pages/**/*.tsx') },
  layout: AppShellLayout,
  defaultTheme: 'system',
  progress: { color: 'var(--primary)' },
});
```

A ordem de spread importa: pages do app substituem os defaults se o nome colidir (ex: app override de `arqel-dev/auth/Login`).

Defaults internos: title callback `${page} — ${appName}`, `<ArqelProvider>` + `<ThemeProvider>` wrap, progress bar configurável, SSR hydrate vs createRoot, `<Toaster>` para flash.

### `resolveArqelPage(registries, name)`

Aceita múltiplos `import.meta.glob` registries (Resource pages + app pages + auth pages) e tenta resolver na ordem.

## Providers

### `<ArqelProvider>` 

Wrapper padrão: combina `<PanelContext.Provider>` + `<TenantContext.Provider>` + `<ThemeProvider>`.

```tsx
<ArqelProvider page={page}>
  <App />
</ArqelProvider>
```

### `<ThemeProvider>` + `useTheme()`

Light/dark/system com `localStorage` persist (`arqel-theme`). `prefers-color-scheme` listener. SSR-safe (localStorage só lido após mount). Vive em `@arqel-dev/theme` e é re-exportado por `@arqel-dev/react/providers`. `<ArqelProvider>` já o inclui — não precisa wrap manual.

```ts
const { theme, resolved, setTheme, toggle } = useTheme();
// theme: 'light' | 'dark' | 'system'
// resolved: 'light' | 'dark'
// setTheme(t): void
// toggle(): void
```

## Contexts

| Context | Hook | `useRequired*` (throws) |
|---|---|---|
| `PanelContext` | `usePanel(): PanelMeta \| null` | `useRequiredPanel()` |
| `ResourceContext` | `useResourceContext(): ResourceContextValue \| null` | `useRequiredResource()` |
| `TenantContext` | `useTenant(): TenantMeta \| null` | (Phase 2) |

`useRequired*` lançam erro descritivo quando o context não está bound — útil para componentes que assumem estar dentro de uma Resource page.

## Utilities

### `route(name, params?, absolute?)`

Typed wrapper sobre Ziggy. Throws clear error quando Ziggy não está instalado.

```ts
route('arqel.resources.index', { resource: 'users' });
route('arqel.resources.edit', { resource: 'users', id: 42 }, true);
```

### `translate(dict, key, options?)` + `useTranslator(dict)`

Placeholders `:name`. Retorna `key` como fallback (nunca undefined).

```ts
const t = useTranslator(translations.arqel.actions);
t('confirm', { name: 'Delete' });
```

### `buildInitialFormState(fields, record?)`

Semeia form state com type-appropriate defaults:

| Field type | Default |
|---|---|
| `boolean`, `toggle` | `false` |
| `multiSelect`, `hasMany` | `[]` |
| `number`, `currency` | `null` |
| outros | `''` |

Quando `record` é passado, usa os valores existentes.

### `indexFieldsByName(fields)`, `fieldsVisibleIn(fields, context)`

Helpers para iterar/filtrar a lista de fields do payload Inertia.

## Related

- SKILL: [`packages-js/react/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages-js/react/SKILL.md)
- Próximo: [`@arqel-dev/hooks`](/pt-BR/reference/typescript/hooks)

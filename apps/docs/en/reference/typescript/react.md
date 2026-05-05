# `@arqel-dev/react` — API Reference

React + Inertia bindings, providers, contexts, utilities. 5 subpath entry points.

```ts
import { createArqelApp } from '@arqel-dev/react/inertia';
import { ArqelProvider, ThemeProvider, useTheme } from '@arqel-dev/react/providers';
import { usePanel, useResourceContext, useTenant } from '@arqel-dev/react/context';
import { route, translate, useTranslator, buildInitialFormState } from '@arqel-dev/react/utils';
```

`peerDependencies`: React 19+, `@inertiajs/react` 2+.

## Inertia bootstrap

### `createArqelApp(options)`

Inertia bootstrapper with Arqel defaults — the main client entry function.

```ts
function createArqelApp(options: {
  appName: string;
  pages: Record<string, () => Promise<unknown>>;        // import.meta.glob('./Pages/**/*.tsx')
  layout?: ComponentType<{ children: ReactNode }>;      // root layout (default: <AppShell>)
  defaultTheme?: 'light' | 'dark' | 'system';           // default: 'system'
  progress?: { color?: string; showSpinner?: boolean } | false;
  ssrRender?: boolean;
  resolve?: (name: string) => unknown;
}): void
```

The `pages` registry should combine:

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

Spread order matters: app pages override defaults if the name collides (e.g., the app overriding `arqel-dev/auth/Login`).

Internal defaults: title callback `${page} — ${appName}`, `<ArqelProvider>` + `<ThemeProvider>` wrap, configurable progress bar, SSR hydrate vs createRoot, `<Toaster>` for flash.

### `resolveArqelPage(registries, name)`

Accepts multiple `import.meta.glob` registries (Resource pages + app pages + auth pages) and tries to resolve in order.

## Providers

### `<ArqelProvider>` 

Default wrapper: combines `<PanelContext.Provider>` + `<TenantContext.Provider>` + `<ThemeProvider>`.

```tsx
<ArqelProvider page={page}>
  <App />
</ArqelProvider>
```

### `<ThemeProvider>` + `useTheme()`

Light/dark/system with `localStorage` persist (`arqel-theme`). `prefers-color-scheme` listener. SSR-safe (localStorage only read after mount). Lives in `@arqel-dev/theme` and is re-exported by `@arqel-dev/react/providers`. `<ArqelProvider>` already includes it — no manual wrap needed.

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

`useRequired*` throw a descriptive error when the context is not bound — useful for components that assume they're inside a Resource page.

## Utilities

### `route(name, params?, absolute?)`

Typed wrapper over Ziggy. Throws a clear error when Ziggy is not installed.

```ts
route('arqel.resources.index', { resource: 'users' });
route('arqel.resources.edit', { resource: 'users', id: 42 }, true);
```

### `translate(dict, key, options?)` + `useTranslator(dict)`

Placeholders `:name`. Returns `key` as fallback (never undefined).

```ts
const t = useTranslator(translations.arqel.actions);
t('confirm', { name: 'Delete' });
```

### `buildInitialFormState(fields, record?)`

Seeds form state with type-appropriate defaults:

| Field type | Default |
|---|---|
| `boolean`, `toggle` | `false` |
| `multiSelect`, `hasMany` | `[]` |
| `number`, `currency` | `null` |
| others | `''` |

When `record` is passed, it uses the existing values.

### `indexFieldsByName(fields)`, `fieldsVisibleIn(fields, context)`

Helpers to iterate/filter the field list from the Inertia payload.

## Related

- SKILL: [`packages-js/react/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages-js/react/SKILL.md)
- Next: [`@arqel-dev/hooks`](/reference/typescript/hooks)

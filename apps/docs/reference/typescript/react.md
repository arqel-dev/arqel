# `@arqel/react` — API Reference

React + Inertia bindings, providers, contexts, utilities. 5 entry points subpath.

```ts
import { createArqelApp } from '@arqel/react/inertia';
import { ArqelProvider, ThemeProvider, useTheme } from '@arqel/react/providers';
import { usePanel, useResourceContext, useTenant } from '@arqel/react/context';
import { route, translate, useTranslator, buildInitialFormState } from '@arqel/react/utils';
```

`peerDependencies`: React 19+, `@inertiajs/react` 2+.

## Inertia bootstrap

### `createArqelApp(options)`

Bootstrapper de Inertia com defaults Arqel.

```ts
function createArqelApp(options: {
  appName: string;
  pages: Record<string, () => Promise<unknown>>;        // import.meta.glob('./Pages/**/*.tsx')
  ssrRender?: boolean;
  progressColor?: string;
  resolve?: (name: string) => unknown;
}): void
```

Defaults: title callback `${page} — ${appName}`, page resolver com fallback paths Vite-style, `<ArqelProvider>` wrap, progress bar configurável, SSR hydrate vs createRoot.

### `resolveArqelPage(registries, name)`

Aceita múltiplos `import.meta.glob` registries (Resource pages + app pages) e tenta resolver na ordem.

## Providers

### `<ArqelProvider>` 

Wrapper padrão: combina `<PanelContext.Provider>` + `<TenantContext.Provider>` + `<ThemeProvider>`.

```tsx
<ArqelProvider page={page}>
  <App />
</ArqelProvider>
```

### `<ThemeProvider>` + `useTheme()`

Light/dark/system com `localStorage` persist (`arqel-theme`). `prefers-color-scheme` listener. SSR-safe (localStorage só lido após mount).

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

- SKILL: [`packages-js/react/SKILL.md`](https://github.com/arqel/arqel/blob/main/packages-js/react/SKILL.md)
- Próximo: [`@arqel/hooks`](/reference/typescript/hooks)

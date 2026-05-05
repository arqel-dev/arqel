# `@arqel-dev/react` — Referencia de API

Bindings React + Inertia, providers, contextos, utilidades. 5 entry points por subpath.

```ts
import { createArqelApp } from '@arqel-dev/react/inertia';
import { ArqelProvider, ThemeProvider, useTheme } from '@arqel-dev/react/providers';
import { usePanel, useResourceContext, useTenant } from '@arqel-dev/react/context';
import { route, translate, useTranslator, buildInitialFormState } from '@arqel-dev/react/utils';
```

`peerDependencies`: React 19+, `@inertiajs/react` 2+.

## Bootstrap de Inertia

### `createArqelApp(options)`

Bootstrapper de Inertia con defaults de Arqel — la función principal de entrada del cliente.

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

El registry `pages` debería combinar:

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

El orden del spread importa: las páginas de la app sobrescriben los defaults si el nombre colisiona (e.g., la app sobrescribiendo `arqel-dev/auth/Login`).

Defaults internos: callback de título `${page} — ${appName}`, wrap `<ArqelProvider>` + `<ThemeProvider>`, barra de progreso configurable, hidratación SSR vs createRoot, `<Toaster>` para flash.

### `resolveArqelPage(registries, name)`

Acepta múltiples registries de `import.meta.glob` (páginas de Resource + páginas de la app + páginas de auth) e intenta resolver en orden.

## Providers

### `<ArqelProvider>` 

Wrapper por defecto: combina `<PanelContext.Provider>` + `<TenantContext.Provider>` + `<ThemeProvider>`.

```tsx
<ArqelProvider page={page}>
  <App />
</ArqelProvider>
```

### `<ThemeProvider>` + `useTheme()`

Light/dark/system con persistencia en `localStorage` (`arqel-theme`). Listener de `prefers-color-scheme`. SSR-safe (localStorage solo se lee tras mount). Vive en `@arqel-dev/theme` y se re-exporta desde `@arqel-dev/react/providers`. `<ArqelProvider>` ya lo incluye — no necesitas wrap manual.

```ts
const { theme, resolved, setTheme, toggle } = useTheme();
// theme: 'light' | 'dark' | 'system'
// resolved: 'light' | 'dark'
// setTheme(t): void
// toggle(): void
```

## Contextos

| Context | Hook | `useRequired*` (lanza) |
|---|---|---|
| `PanelContext` | `usePanel(): PanelMeta \| null` | `useRequiredPanel()` |
| `ResourceContext` | `useResourceContext(): ResourceContextValue \| null` | `useRequiredResource()` |
| `TenantContext` | `useTenant(): TenantMeta \| null` | (Phase 2) |

`useRequired*` lanza un error descriptivo cuando el contexto no está vinculado — útil para componentes que asumen estar dentro de una página de Resource.

## Utilidades

### `route(name, params?, absolute?)`

Wrapper tipado sobre Ziggy. Lanza un error claro cuando Ziggy no está instalado.

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

Inicializa el estado del form con defaults apropiados al tipo:

| Tipo de Field | Default |
|---|---|
| `boolean`, `toggle` | `false` |
| `multiSelect`, `hasMany` | `[]` |
| `number`, `currency` | `null` |
| otros | `''` |

Cuando se pasa `record`, usa los valores existentes.

### `indexFieldsByName(fields)`, `fieldsVisibleIn(fields, context)`

Helpers para iterar/filtrar la lista de fields del payload de Inertia.

## Relacionado

- SKILL: [`packages-js/react/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages-js/react/SKILL.md)
- Siguiente: [`@arqel-dev/hooks`](/es/reference/typescript/hooks)

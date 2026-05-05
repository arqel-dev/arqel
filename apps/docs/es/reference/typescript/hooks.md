# `@arqel-dev/hooks` — Referencia de API

10 Hooks reutilizables. 11 entry points tree-shakeables por subpath.

```ts
import { useResource, useArqelForm, useCanAccess, useFlash, useTable,
         useAction, useFieldDependencies, useNavigation, useBreakpoint,
         useArqelOptimistic } from '@arqel-dev/hooks';
```

## `useResource<T>()`

Lee props de la página + ResourceContext, retorna una forma unificada.

```ts
const { resource, record, fields, form, table } = useResource<Post>();
```

## `useArqelForm({ fields, record? })`

Wrapper del `useForm` de Inertia con defaults vía `buildInitialFormState`.

```ts
const form = useArqelForm({ fields, record });
form.data.title       // typed via FormValues
form.setData(name, v)
form.processing
form.errors
form.submit('post', route('arqel.resources.store'), { onSuccess: () => {} });
```

> **Nota técnica:** El `useForm<T>` de Inertia sufre de "type instantiation excessively deep" con `Record<string, FormDataConvertible>` — `useArqelForm` hace un cast puente `useForm as unknown as (data: FormValues) => InertiaFormProps<FormValues>` para evitar TS2589 preservando el tipo de retorno público.

## `useCanAccess(ability, record?)`

```ts
const canEdit = useCanAccess('update', post);
const canExport = useCanAccess('exportData');           // global ability
```

Las abilities a nivel de registro tienen precedencia sobre las globales (solo UX — ADR-017).

## `useFlash({ onMessage })`

Callback once-per-new-message vía `useRef`.

```ts
useFlash({
  onMessage: ({ kind, text }) => toast(text, { type: kind })
});
```

## `useTable()`

Sort/filters/selección locales (URL sync diferido al follow-up HOOKS-004).

```ts
const { sort, setSort, filters, setFilter, selection, toggleSelection, clearSelection } = useTable();
```

## `useAction(action)`

Wrapper de `router.visit` con un flag `processing`.

```ts
const { invoke, processing } = useAction(action);
<button onClick={() => invoke({ record })} disabled={processing}>...
```

## `useFieldDependencies()`

Recarga parcial de Inertia con debounce de 300ms para `fields.<name>.options`.

```ts
useFieldDependencies();   // injected in <FieldRenderer>
```

Se dispara cuando un field marcado con `dependsOn(['country'])` re-resuelve options server-side vía `Resource.handleDependencyUpdate`.

## `useNavigation()`

Lee el prop compartido de Inertia `panel.navigation` (poblado server-side por `HandleArqelInertiaRequests::buildNavigation()`).

```ts
const { items } = useNavigation();
// items: NavigationItemPayload[]
```

`NavigationItemPayload`:

```ts
type NavigationItemPayload = {
  label: string;
  url: string;
  icon: string | null;       // lucide-react ID
  group: string | null;      // grouping label, null = top-level
  sort: number;
  active: boolean;           // resolved server-side via current request
};
```

Consumido por `<Sidebar>` (bloque shadcn `sidebar-07`) — los items se agrupan por `group` al renderizar.

## `useTheme()`

Re-exportado desde `@arqel-dev/theme`. **Requiere** `<ThemeProvider>` en el árbol (incluido automáticamente en `<ArqelProvider>` / `createArqelApp`). Sin el Provider, lanza un error descriptivo.

```ts
const { theme, resolved, setTheme, toggle } = useTheme();
```

## `useBreakpoint()`

Breakpoint de Tailwind v4 vía `matchMedia`. SSR-safe.

```ts
const bp = useBreakpoint();
// { current: 'sm' | 'md' | 'lg' | 'xl' | '2xl', isMobile: boolean }
```

## `useArqelOptimistic()`

Wrapper del `useOptimistic` de React 19.

```ts
const [optimisticPosts, addOptimistic] = useArqelOptimistic(posts, (state, newPost) => [...state, newPost]);
```

## Relacionado

- SKILL: [`packages-js/hooks/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages-js/hooks/SKILL.md)
- Siguiente: [`@arqel-dev/ui`](/es/reference/typescript/ui)

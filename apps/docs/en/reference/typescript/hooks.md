# `@arqel-dev/hooks` — API Reference

10 reusable hooks. 11 tree-shakeable subpath entry points.

```ts
import { useResource, useArqelForm, useCanAccess, useFlash, useTable,
         useAction, useFieldDependencies, useNavigation, useBreakpoint,
         useArqelOptimistic } from '@arqel-dev/hooks';
```

## `useResource<T>()`

Reads page props + ResourceContext, returns a unified shape.

```ts
const { resource, record, fields, form, table } = useResource<Post>();
```

## `useArqelForm({ fields, record? })`

Wrapper of Inertia `useForm` with defaults via `buildInitialFormState`.

```ts
const form = useArqelForm({ fields, record });
form.data.title       // typed via FormValues
form.setData(name, v)
form.processing
form.errors
form.submit('post', route('arqel.resources.store'), { onSuccess: () => {} });
```

> **Technical note:** Inertia's `useForm<T>` suffers from "type instantiation excessively deep" with `Record<string, FormDataConvertible>` — `useArqelForm` does a bridge cast `useForm as unknown as (data: FormValues) => InertiaFormProps<FormValues>` to avoid TS2589 while preserving the public return type.

## `useCanAccess(ability, record?)`

```ts
const canEdit = useCanAccess('update', post);
const canExport = useCanAccess('exportData');           // global ability
```

Record-level abilities take precedence over globals (UX-only — ADR-017).

## `useFlash({ onMessage })`

Once-per-new-message callback via `useRef`.

```ts
useFlash({
  onMessage: ({ kind, text }) => toast(text, { type: kind })
});
```

## `useTable()`

Local sort/filters/selection (URL sync deferred to HOOKS-004 follow-up).

```ts
const { sort, setSort, filters, setFilter, selection, toggleSelection, clearSelection } = useTable();
```

## `useAction(action)`

`router.visit` wrapper with a `processing` flag.

```ts
const { invoke, processing } = useAction(action);
<button onClick={() => invoke({ record })} disabled={processing}>...
```

## `useFieldDependencies()`

Debounced 300ms partial Inertia reload of `fields.<name>.options`.

```ts
useFieldDependencies();   // injected in <FieldRenderer>
```

Triggers when a field marked `dependsOn(['country'])` re-resolves options server-side via `Resource.handleDependencyUpdate`.

## `useNavigation()`

Reads the Inertia shared prop `panel.navigation` (populated server-side by `HandleArqelInertiaRequests::buildNavigation()`).

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

Consumed by `<Sidebar>` (shadcn `sidebar-07` block) — items are grouped by `group` on render.

## `useTheme()`

Re-exported from `@arqel-dev/theme`. **Requires** `<ThemeProvider>` in the tree (included automatically in `<ArqelProvider>` / `createArqelApp`). Without the provider, throws a descriptive error.

```ts
const { theme, resolved, setTheme, toggle } = useTheme();
```

## `useBreakpoint()`

Tailwind v4 breakpoint via `matchMedia`. SSR-safe.

```ts
const bp = useBreakpoint();
// { current: 'sm' | 'md' | 'lg' | 'xl' | '2xl', isMobile: boolean }
```

## `useArqelOptimistic()`

React 19 `useOptimistic` wrapper.

```ts
const [optimisticPosts, addOptimistic] = useArqelOptimistic(posts, (state, newPost) => [...state, newPost]);
```

## Related

- SKILL: [`packages-js/hooks/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages-js/hooks/SKILL.md)
- Next: [`@arqel-dev/ui`](/reference/typescript/ui)

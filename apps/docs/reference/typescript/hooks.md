# `@arqel-dev/hooks` — API Reference

10 hooks reusáveis. 11 entry points subpath tree-shakeable.

```ts
import { useResource, useArqelForm, useCanAccess, useFlash, useTable,
         useAction, useFieldDependencies, useNavigation, useBreakpoint,
         useArqelOptimistic } from '@arqel-dev/hooks';
```

## `useResource<T>()`

Lê page props + ResourceContext, retorna shape unificado.

```ts
const { resource, record, fields, form, table } = useResource<Post>();
```

## `useArqelForm({ fields, record? })`

Wrapper de Inertia `useForm` com defaults via `buildInitialFormState`.

```ts
const form = useArqelForm({ fields, record });
form.data.title       // typed via FormValues
form.setData(name, v)
form.processing
form.errors
form.submit('post', route('arqel.resources.store'), { onSuccess: () => {} });
```

> **Nota técnica:** Inertia `useForm<T>` sofre de "type instantiation excessively deep" com `Record<string, FormDataConvertible>` — `useArqelForm` faz cast bridge `useForm as unknown as (data: FormValues) => InertiaFormProps<FormValues>` para evitar TS2589 mantendo o tipo de retorno público.

## `useCanAccess(ability, record?)`

```ts
const canEdit = useCanAccess('update', post);
const canExport = useCanAccess('exportData');           // global ability
```

Record-level abilities têm precedência sobre globals (UX-only — ADR-017).

## `useFlash({ onMessage })`

Callback once-per-new-message via `useRef`.

```ts
useFlash({
  onMessage: ({ kind, text }) => toast(text, { type: kind })
});
```

## `useTable()`

Sort/filters/selection local (URL sync deferido para HOOKS-004 follow-up).

```ts
const { sort, setSort, filters, setFilter, selection, toggleSelection, clearSelection } = useTable();
```

## `useAction(action)`

`router.visit` wrapper com `processing` flag.

```ts
const { invoke, processing } = useAction(action);
<button onClick={() => invoke({ record })} disabled={processing}>...
```

## `useFieldDependencies()`

Debounced 300ms partial reload Inertia de `fields.<name>.options`.

```ts
useFieldDependencies();   // injetado em <FieldRenderer>
```

Triggera quando um field marcado `dependsOn(['country'])` re-resolve options server-side via `Resource.handleDependencyUpdate`.

## `useNavigation()`

Lê `panel.navigation` shared prop.

```ts
const navigation = useNavigation();
// array<{kind: 'item' | 'group' | 'divider', ...}>
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
- Próximo: [`@arqel-dev/ui`](/reference/typescript/ui)

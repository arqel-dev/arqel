# SKILL.md — @arqel-dev/hooks

> Contexto canónico para AI agents.

## Purpose

`@arqel-dev/hooks` consolida hooks reusáveis para Resource pages, formulários, tabelas, ações, navegação e UI utilities. Consome `@arqel-dev/react` (contexts/utils) e `@inertiajs/react` (page props, useForm, router) — não tem componentes, só lógica. Cada hook é um wrapper stateless sobre Inertia shared props ou page props.

## Status

**Entregue (HOOKS-001..006):**

- 11 entry points subpath (tree-shakeable)
- `useResource<T>()` — page props + ResourceContext merged
- `useArqelForm({ fields, record, defaults })` — wrap `useForm` Inertia com `buildInitialFormState`
- `useCanAccess(ability, record?)` — record-level abilities têm precedência sobre globais
- `useFlash({ onMessage })` — fires callback uma vez por nova mensagem
- `useTable({ defaultSort, defaultFilters, defaultSelection })` — estado local (URL sync = Phase 2)
- `useAction(action)` — invoke via `router.visit`
- `useFieldDependencies({ fields, values, debounceMs })` — partial reload `fields.<name>.options`, debounce 300ms
- `useNavigation()` — itens de menu derivados de shared props
- `useBreakpoint()` — matchMedia wrapper SSR-safe
- `useArqelOptimistic()` (alias `useOptimistic`) — optimistic state com rollback on failure
- `useResourceUpdates()` — assinatura a updates Inertia partial reload por resource
- 30 testes Vitest passando

**Por chegar (Phase 2):** Zod validation em `useArqelForm.validate()`, URL sync em `useTable`, real Inertia progress events em `useAction`.

## Key Contracts

### `useNavigation()`

Lê `panel.navigation` da shared prop Inertia e devolve a árvore tipada de items de menu.

```ts
import { useNavigation } from '@arqel-dev/hooks';

const { items } = useNavigation();
// items: NavigationItemPayload[]
```

`NavigationItemPayload`:

```ts
type NavigationItemPayload = {
  label: string;
  url: string;
  icon?: string;          // nome lucide-react
  group?: string;         // header/section visual
  sort?: number;          // ordenação
  active?: boolean;       // server marca match contra request atual
  children?: NavigationItemPayload[];
};
```

Items vêm 100% do backend (`Panel::navigation()`); o hook só faz coerção (defaults para `[]` quando ausente/inválido).

### `useResource<T>()`

```ts
const { records, resource, filters, pagination } = useResource<User>();
```

Merge de `usePage().props` + `useResourceContext()`.

### `useArqelForm`

```ts
const form = useArqelForm({
  fields: schema.fields,
  record: editingUser,
});
form.setData('email', 'a@b.c');
form.post('/admin/users');
```

### `useTable`

```ts
const table = useTable({ defaultSort: { column: 'created_at', direction: 'desc' } });
table.setSort('name', 'asc');
table.toggleSelection(record.id);
```

### `useFlash`

```ts
useFlash({ onMessage: (kind, msg) => toast[kind](msg) });
```

### Outros

```ts
useCanAccess('users.update', record);          // record precedence > global
useAction(action);                              // { invoke, processing, progress }
useFieldDependencies({ fields, values });       // partial reload com debounce
useBreakpoint();                                // { isMobile, isTablet, isDesktop }
useArqelOptimistic(initial, reducer);
useResourceUpdates(resourceName);
```

## Conventions

- Subpath imports preferidos: `@arqel-dev/hooks/useTable` evita arrastar deps de outros hooks
- Hooks acessam page props via `usePage()` — apps devem garantir que estão dentro do `<App>` Inertia
- `useCanAccess` é UX-only: enforcement real é server-side (ADR-017)
- Stateless: cada hook deriva do Inertia shared/page props; não mantém cache próprio

## Anti-patterns

- ❌ Usar `useResource` fora de uma página Inertia — `usePage()` lança
- ❌ Confiar em `useCanAccess` para esconder dados sensíveis — só esconde da UI
- ❌ Re-renderizar `useArqelForm` com novo array de `fields` a cada render — memoize antes
- ❌ Múltiplos `useFlash({ onMessage })` em árvores irmãs — duplica toasts; um único callsite no AppShell
- ❌ Mutar `items` retornado por `useNavigation` — é o payload server, tratar como readonly

## Related

- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §HOOKS-001..006
- API: [`PLANNING/06-api-react.md`](../../PLANNING/06-api-react.md) §9
- Source: [`packages-js/hooks/src/`](src/)
- Tests: [`packages-js/hooks/tests/`](tests/)

# SKILL.md — @arqel/hooks

> Contexto canónico para AI agents.

## Purpose

`@arqel/hooks` consolida hooks reusáveis para Resource pages, formulários, tabelas, ações e UI. Consome `@arqel/react` (contexts/utils) e `@inertiajs/react` (page props, useForm, router) — não tem componentes, só lógica.

## Status

**Entregue (HOOKS-001):**

- Esqueleto do pacote com 11 entry points (tree-shakeable)
- `useResource<T>()` — page props + ResourceContext merged
- `useArqelForm({ fields, record, defaults })` — wrap `useForm` Inertia com `buildInitialFormState`
- `useCanAccess(ability, record?)` — record-level abilities têm precedência sobre globais
- `useFlash({ onMessage })` — fires callback uma vez por nova mensagem
- `useTable({ defaultSort, defaultFilters, defaultSelection })` — estado local (URL sync vem em HOOKS-004)
- `useAction(action)` — invoke via `router.visit`
- `useFieldDependencies({ fields, values, debounceMs })` — partial reload `fields.<name>.options` debounce 300ms
- `useNavigation()` / `useBreakpoint()` / `useArqelOptimistic()`

**Entregue depois (HOOKS-002..006):**

- HOOKS-002: API surface dos 10 hooks já está consolidada em HOOKS-001 (build/typecheck/lint passam, são tree-shakeable per-subpath)
- HOOKS-003: `useCanAccess` + `useFlash` cobertos com testes unitários
- HOOKS-004: `useTable` cobertura de filtros/sort/seleção; `useAction` thin wrapper testado via smoke; URL sync em `useTable` + real progress events em `useAction` ficam como Phase 2 follow-up (nada quebrante exposto na API atual)
- HOOKS-005: `useNavigation`/`useBreakpoint`/`useArqelOptimistic` exportados; `useNavigation` coberto, `useBreakpoint` coberto via smoke (matchMedia no jsdom)
- HOOKS-006: **30 testes Vitest passando** (era 4): `useTable.test.tsx` (8 — sort defaults/explicit/clear, filters add/remove/clear, selection toggle/all/clear/isSelected), `useFlash.test.tsx` (4 — payload presente, fallback empty, onMessage once-per-new-value, multi-kind dispatch), `useCanAccess.test.tsx` (6 — no auth.can = false, global resolution, record precedence, fallback to global, null/undefined record, non-bool coerced), `useNavigation.test.tsx` (3 — empty, items present, non-array coercion), `useResource.test.tsx` (5 — empty shape, records list, single record, server filters, raw props escape hatch), `smoke.test.tsx` (4 — table seeds + breakpoint match)
- Mock de `@inertiajs/react` em `tests/setup.ts` via `vi.mock` + helpers `setMockPage`/`resetMockPage` (executa antes de qualquer test, ordem de imports não importa)

**Por chegar (Phase 2):**

- Zod validation em `useArqelForm.validate()` — ValidationBridge server emite o schema, mas o consumidor client ainda é manual
- URL sync em `useTable` (`router.get` com debounce + history replace)
- Real Inertia progress events em `useAction` (substituir o stub `progress: 0`)

## Key Contracts

```ts
import { useResource, useArqelForm, useCanAccess, useFlash } from '@arqel/hooks';

function UsersIndex() {
  const { records, resource } = useResource<User>();
  const canCreate = useCanAccess('users.create');
  useFlash({ onMessage: (kind, msg) => toast[kind](msg) });
  // ...
}
```

```ts
import { useArqelForm } from '@arqel/hooks';

const form = useArqelForm({
  fields: schema.fields,
  record: editingUser,
});

form.setData('email', 'a@b.c');
form.post('/admin/users');
```

```ts
import { useTable } from '@arqel/hooks';

const table = useTable({ defaultSort: { column: 'created_at', direction: 'desc' } });

table.setSort('name', 'asc');
table.toggleSelection(record.id);
```

## Conventions

- Subpath imports preferidos: `@arqel/hooks/useTable` evita arrastar deps de outros hooks
- Hooks acessam page props via `usePage()` — apps devem garantir que estão dentro do `<App>` Inertia
- `useCanAccess` é UX-only: enforcement real é server-side (ADR-017)

## Anti-patterns

- ❌ Usar `useResource` fora de uma página Inertia — `usePage()` lança
- ❌ Confiar em `useCanAccess` para esconder dados sensíveis — só esconde da UI
- ❌ Re-renderizar `useArqelForm` com novo array de `fields` a cada render — memoize antes
- ❌ Múltiplos `useFlash({ onMessage })` em árvores irmãs — duplica toasts; prefere um único callsite no AppShell

## Related

- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §HOOKS-001..006
- API: [`PLANNING/06-api-react.md`](../../PLANNING/06-api-react.md) §9
- Source: [`packages-js/hooks/src/`](src/)
- Tests: [`packages-js/hooks/tests/`](tests/)

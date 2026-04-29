# @arqel/hooks

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)
[![React](https://img.shields.io/badge/react-%5E19-61dafb.svg)](https://react.dev)
[![Status](https://img.shields.io/badge/status-pre--alpha-orange.svg)](#)

Reusable React hooks for [Arqel](https://arqel.dev) admin panels.

## Status

🚧 **Pre-alpha** — HOOKS-001 entregue.

## Install

```bash
pnpm add @arqel/hooks @arqel/react @arqel/types
pnpm add @inertiajs/react react react-dom
```

## Hooks

| Hook | Purpose |
|---|---|
| `useResource<T>()` | Typed page props + ResourceContext |
| `useArqelForm({ fields, record })` | Inertia `useForm` com defaults baseados em fields |
| `useCanAccess(ability, record?)` | Record-level / global ability check (UX only) |
| `useFlash({ onMessage })` | Flash messages com callback once-per-new-message |
| `useTable()` | Local state: sort, filters, selection |
| `useAction(action)` | Invoca Action via `router.visit` |
| `useFieldDependencies()` | Debounced partial reload em mudanças de campos |
| `useNavigation()` | Lê `panel.navigation` dos shared props |
| `useBreakpoint()` | Tailwind v4 breakpoint atual via `matchMedia` |
| `useArqelOptimistic()` | Wrap de `useOptimistic` React 19 |

## Subpath imports

```ts
import { useTable } from '@arqel/hooks/useTable';
```

## Links

- [Documentação](https://arqel.dev/docs/hooks) — em construção
- [PLANNING](../../PLANNING/08-fase-1-mvp.md) — tickets `HOOKS-*`

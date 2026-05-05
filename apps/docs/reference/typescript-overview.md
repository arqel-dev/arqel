# API Reference — TypeScript

A curated per-package reference (TypeScript 5.6+ strict). Each page documents types, hooks, components, and utilities.

> **Recent note:** `@arqel-dev/ui` was migrated to [shadcn/ui](https://ui.shadcn.com/) (variant `new-york`) with primitives based on [Radix UI](https://www.radix-ui.com/) (the `radix-ui` package). The shadcn components are copied to the app via `@arqel-dev/ui` re-export; CSS tokens follow the shadcn convention (`--background`, `--foreground`, `--primary`, `--radius`, etc.).

## Packages

| Package | Contents | Page |
|---|---|---|
| `@arqel-dev/types` | Discriminated unions for FieldType/ColumnType/FilterType, FormSchema, ResourceMeta, SharedProps | [Types →](/reference/typescript/types) |
| `@arqel-dev/react` | `createArqelApp`, `<ArqelProvider>`, contexts, utilities | [React →](/reference/typescript/react) |
| `@arqel-dev/hooks` | 10+ hooks (`useResource`, `useArqelForm`, `useTable`, `useNavigation`, `useFlash`, `useCanAccess`, etc.) | [Hooks →](/reference/typescript/hooks) |
| `@arqel-dev/ui` | Shell, Table, Form, Action, Flash, Utility components + shadcn (Radix) primitives + FieldRegistry | [UI →](/reference/typescript/ui) |
| `@arqel-dev/auth` | Inertia pages (`Login`, `Register`, `ForgotPassword`, `ResetPassword`, `VerifyEmail`) + form helpers | (stub) |
| `@arqel-dev/fields` | 21 rich inputs registered via FieldRegistry + `slugify` helper | [Fields →](/reference/typescript/fields) |
| `@arqel-dev/theme` | `<ThemeProvider>`, `useTheme()`, shadcn tokens (light/dark/system) | (stub) |

Total: **7 JS packages** (types, react, hooks, ui, auth, fields, theme).

## General conventions

- **`strict: true` + `noUncheckedIndexedAccess: true` + `exactOptionalPropertyTypes: true`** in every tsconfig (extends `tsconfig.base.json`)
- **Subpath exports** declared in `package.json` → tree-shake friendly. Never import from `dist/` directly
- **`peerDependencies`** for React 19+ and `@inertiajs/react` 2+ — apps control the version
- **Build via `tsup`** — ESM only, `.d.ts` generated, sourcemaps included
- **Side-effects: `false`** in every package except `@arqel-dev/fields` (which declares `sideEffects: ['./dist/register.js']` for automatic registration)
- **Tests via Vitest + jsdom + `@testing-library/react`** — type-level via `expect-type`

## Auto-generation (TODO)

As with the PHP reference, this documentation is **manually curated**. Auto-generation via [TypeDoc](https://typedoc.org/) ships as a follow-up:

```yaml
# .github/workflows/docs-deploy.yml (future)
- name: Generate TypeDoc
  run: typedoc --out apps/docs/reference/typescript/_generated packages-js/*/src/index.ts
```

The DOCS-006 criteria ("Types/interfaces/hooks/components documented via TSDoc", "integrated search") covered by auto-generation remain pending until that PR.

## Related

- PHP: [API Reference PHP](/reference/php-overview)
- Source: [`packages-js/`](https://github.com/arqel-dev/arqel/tree/main/packages-js)

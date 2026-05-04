# API Reference — TypeScript

Referência curada por pacote (TypeScript 5.6+ strict). Cada página documenta types, hooks, components e utilities.

## Pacotes

| Pacote | Conteúdo | Página |
|---|---|---|
| `@arqel-dev/types` | Discriminated unions de FieldType/ColumnType/FilterType, FormSchema, ResourceMeta, SharedProps | [Types →](/reference/typescript/types) |
| `@arqel-dev/react` | `createArqelApp`, `<ArqelProvider>`, `<ThemeProvider>`, contexts, utilities | [React →](/reference/typescript/react) |
| `@arqel-dev/hooks` | 10 hooks (`useResource`, `useArqelForm`, `useTable`, `useFlash`, `useCanAccess`, etc.) | [Hooks →](/reference/typescript/hooks) |
| `@arqel-dev/ui` | Shell, Table, Form, Action, Flash, Utility components + FieldRegistry + tokens CSS | [UI →](/reference/typescript/ui) |
| `@arqel-dev/fields` | 21 rich inputs registrados via FieldRegistry + helper `slugify` | [Fields →](/reference/typescript/fields) |

## Convenções gerais

- **`strict: true` + `noUncheckedIndexedAccess: true` + `exactOptionalPropertyTypes: true`** em todos os tsconfigs (extends `tsconfig.base.json`)
- **Subpath exports** declarados em `package.json` → tree-shake friendly. Nunca importar de `dist/` directamente
- **`peerDependencies`** para React 19+ e `@inertiajs/react` 2+ — apps controlam a versão
- **Build via `tsup`** — ESM only, `.d.ts` gerado, sourcemaps incluídos
- **Side-effects: `false`** em todos os pacotes excepto `@arqel-dev/fields` (que declara `sideEffects: ['./dist/register.js']` para o registo automático)
- **Testes via Vitest + jsdom + `@testing-library/react`** — type-level via `expect-type`

## Geração automática (TODO)

Como na referência PHP, esta documentação é **curada manualmente**. Auto-geração via [TypeDoc](https://typedoc.org/) chega como follow-up:

```yaml
# .github/workflows/docs-deploy.yml (futuro)
- name: Generate TypeDoc
  run: typedoc --out apps/docs/reference/typescript/_generated packages-js/*/src/index.ts
```

Os critérios de DOCS-006 ("Types/interfaces/hooks/components documentados via TSDoc", "search integrado") cobertos pela auto-geração ficam pendentes até esse PR.

## Related

- PHP: [API Reference PHP](/reference/php-overview)
- Source: [`packages-js/`](https://github.com/arqel-dev/arqel/tree/main/packages-js)

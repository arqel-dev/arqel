# Referencia de API — TypeScript

Una referencia curada por paquete (TypeScript 5.6+ strict). Cada página documenta tipos, hooks, componentes y utilidades.

> **Nota reciente:** `@arqel-dev/ui` fue migrado a [shadcn/ui](https://ui.shadcn.com/) (variante `new-york`) con primitivas basadas en [Radix UI](https://www.radix-ui.com/) (el paquete `radix-ui`). Los componentes shadcn se copian a la app vía re-export de `@arqel-dev/ui`; los tokens CSS siguen la convención shadcn (`--background`, `--foreground`, `--primary`, `--radius`, etc.).

## Paquetes

| Paquete | Contenido | Página |
|---|---|---|
| `@arqel-dev/types` | Discriminated unions de FieldType/ColumnType/FilterType, FormSchema, ResourceMeta, SharedProps | [Types →](/es/reference/typescript/types) |
| `@arqel-dev/react` | `createArqelApp`, `<ArqelProvider>`, contextos, utilidades | [React →](/es/reference/typescript/react) |
| `@arqel-dev/hooks` | 10+ Hooks (`useResource`, `useArqelForm`, `useTable`, `useNavigation`, `useFlash`, `useCanAccess`, etc.) | [Hooks →](/es/reference/typescript/hooks) |
| `@arqel-dev/ui` | Componentes Shell, Table, Form, Action, Flash, Utility + primitivas shadcn (Radix) + FieldRegistry | [UI →](/es/reference/typescript/ui) |
| `@arqel-dev/auth` | Páginas Inertia (`Login`, `Register`, `ForgotPassword`, `ResetPassword`, `VerifyEmail`) + helpers de form | (stub) |
| `@arqel-dev/fields` | 21 inputs ricos registrados vía FieldRegistry + helper `slugify` | [Fields →](/es/reference/typescript/fields) |
| `@arqel-dev/theme` | `<ThemeProvider>`, `useTheme()`, tokens shadcn (light/dark/system) | (stub) |

Total: **7 paquetes JS** (types, react, hooks, ui, auth, fields, theme).

## Convenciones generales

- **`strict: true` + `noUncheckedIndexedAccess: true` + `exactOptionalPropertyTypes: true`** en cada tsconfig (extiende `tsconfig.base.json`)
- **Subpath exports** declarados en `package.json` → tree-shake friendly. Nunca importes desde `dist/` directamente
- **`peerDependencies`** para React 19+ y `@inertiajs/react` 2+ — las apps controlan la versión
- **Build vía `tsup`** — solo ESM, `.d.ts` generado, sourcemaps incluidos
- **Side-effects: `false`** en cada paquete excepto `@arqel-dev/fields` (que declara `sideEffects: ['./dist/register.js']` para registro automático)
- **Tests vía Vitest + jsdom + `@testing-library/react`** — a nivel de tipo vía `expect-type`

## Auto-generación (TODO)

Como con la referencia PHP, esta documentación está **curada manualmente**. La auto-generación vía [TypeDoc](https://typedoc.org/) llegará como un follow-up:

```yaml
# .github/workflows/docs-deploy.yml (future)
- name: Generate TypeDoc
  run: typedoc --out apps/docs/reference/typescript/_generated packages-js/*/src/index.ts
```

Los criterios de DOCS-006 ("Types/interfaces/hooks/components documentados vía TSDoc", "búsqueda integrada") cubiertos por la auto-generación quedan pendientes hasta ese PR.

## Relacionado

- PHP: [Referencia de API PHP](/es/reference/php-overview)
- Source: [`packages-js/`](https://github.com/arqel-dev/arqel/tree/main/packages-js)

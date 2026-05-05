# SKILL.md — @arqel-dev/ui

> Contexto canónico para AI agents.

## Purpose

`@arqel-dev/ui` é a "casca" estrutural do admin panel Arqel: shell (AppShell + Sidebar + Topbar), página de listagem (ResourceIndex + DataTable), formulário polimórfico (FormRenderer + FieldRenderer), botões e modais de Action, flash toasts, blocos de auth e utilitários (Breadcrumbs, PageHeader, EmptyState, ErrorState, LoadingSkeleton).

A partir da migração para shadcn, o pacote é construído sobre **shadcn (style `new-york`) + Radix UI**, integrado via `shadcn@4.6` CLI. Todas as primitivas oficiais vivem em `src/shadcn/` (gerado pela CLI; **read-only**) e são re-exportadas via `src/primitives/index.ts` + barrel top-level `src/index.ts`. Componentes custom (shell, table, form renderer, etc.) consomem essas primitivas; estado vive nos hooks/Inertia.

## Status

**Stack base:**

- shadcn CLI v4.6, registry default `new-york`
- Radix UI (`radix-ui`) — substitui Base UI (`@base-ui-components/react`) em Dialog, DropdownMenu, Sheet, Tooltip, Select, Checkbox, Separator, Label
- `class-variance-authority` (cva) + `tailwind-merge` + `clsx` (via `cn()`)
- Tailwind v4 (`@import 'tailwindcss';`) + `tw-animate-css`
- `globals.css` agora usa **shadcn CSS vars** (`--background`, `--foreground`, `--primary`, `--secondary`, `--muted`, `--accent`, `--destructive`, `--border`, `--input`, `--ring`, `--card`, `--popover`, `--sidebar*`, `--radius`, `--chart-*`) com `@theme inline` bridge para mapear vars → tokens Tailwind. `.dark` override aplicado por `<ThemeProvider>` de `@arqel-dev/react`.

**Primitivas re-exportadas** (de `@arqel-dev/ui`):

- `Button` (cva) — variants `default | destructive | outline | secondary | ghost | link`; sizes `default | xs | sm | lg | icon | icon-xs | icon-sm | icon-lg`
- `Input`, `Textarea`, `Label`, `Checkbox`, `Separator`, `Skeleton`
- `Card` + `CardHeader`, `CardTitle`, `CardDescription`, `CardContent`, `CardFooter`, `CardAction`
- `Alert` + `AlertTitle`, `AlertDescription`
- `Badge` (cva) — variants `default | secondary | destructive | outline` (success/warning foram removidas)
- `Select` + `SelectTrigger`, `SelectContent`, `SelectItem`, `SelectValue`, `SelectGroup`, `SelectLabel`, `SelectSeparator`
- `Field` + `FieldGroup`, `FieldLabel`, `FieldError`, `FieldDescription`, `FieldSeparator`
- `Dialog`, `DropdownMenu`, `Sheet`, `Tooltip` (Radix-based)

**Blocks shadcn integrados:**

- `sidebar-07` — base do `<Sidebar>` / `<AppShell>` (com `<SidebarProvider>`, `<SidebarTrigger>`, `<SidebarInset>`, colapsável para ícone, persistência via cookie)
- `login-04` — split-screen card + hero illustration; usado por `LoginPage` em `@arqel-dev/auth`
- `signup-04` — análogo para `RegisterPage`

**Custom components (sobre as primitivas):**

- `<AppShell variant>`: 4 variants (`sidebar-left | sidebar-right | topbar-only | full-width`) com `SidebarProvider` interno
- `<Sidebar>` (block `sidebar-07` + collapsible icon + items via `useNavigation()` ou prop `items`, groups, badges, `aria-current="page"`)
- `<Topbar>`: brand + theme toggle + `SidebarTrigger` mobile + slots search/userMenu/tenantSwitcher
- `<MainContent>` (`SidebarInset` wrapper) com padding responsivo + `maxWidth` (`md..7xl | none`)
- `<DataTable>` (TanStack Table v8) — 9 cell renderers, seleção controlada, sticky header, `aria-sort`
- `<TableFilters>` / `<TablePagination>` / `<TableToolbar>` / `<ResourceIndex>`
- `<FormRenderer>` + `<FieldRenderer>` + `FieldRegistry` + `<FormSection>` / `<FormFieldset>` / `<FormGrid>` / `<FormTabs>` / `<FormActions>`
- `<ActionButton>` / `<ActionMenu>` (Radix DropdownMenu) / `<ConfirmDialog>` (Radix Dialog) / `<ActionFormModal>` (Radix Dialog hospedando `<FormRenderer>`)
- `<FlashContainer>` + `<FlashToast>` (4 posições, `role=alert/status`)
- `<Breadcrumbs>`, `<PageHeader>`, `<EmptyState>`, `<ErrorState>`, `<LoadingSkeleton>`
- `<CanAccess>` wrapper sobre `useCanAccess`

## Key Contracts

```tsx
import '@arqel-dev/ui/styles.css';
import {
  AppShell, Sidebar, Topbar, MainContent, FlashContainer,
  ResourceIndex, FormRenderer, ActionButton, CanAccess,
  Breadcrumbs, PageHeader,
  Button, Card, CardHeader, CardContent, Field, FieldLabel, Input,
} from '@arqel-dev/ui';

export default function UsersIndex(props: ResourceIndexProps<User>) {
  return (
    <AppShell sidebar={<Sidebar />} topbar={<Topbar />}>
      <MainContent breadcrumbs={<Breadcrumbs />}>
        <ResourceIndex
          {...props}
          rowActions={(record) => (
            <ActionButton
              action={editAction}
              onInvoke={() => router.visit(`/admin/users/${record.id}/edit`)}
            />
          )}
        />
      </MainContent>
      <FlashContainer />
    </AppShell>
  );
}
```

## Conventions

- **Subpath imports** preferidos para tree-shaking (`@arqel-dev/ui/shell`, `/table`, `/form`, `/action`, `/auth`, `/flash`, `/utility`, `/utils`, `/primitives`)
- CSS exposto como `@arqel-dev/ui/styles.css` — apps importam uma vez
- Design tokens via **shadcn CSS vars** (`--background`, `--primary`, `--sidebar`, `--radius`, etc.) — mapeados em `@theme inline`. Honorar tema = nunca hardcodar cor.
- `.dark` class flip aplicada por `<ThemeProvider>` de `@arqel-dev/react`
- Componentes presentational — callbacks lifted (sem fetch interno)
- `peerDependencies`: `radix-ui`, `@tanstack/react-table`, `lucide-react`, `@arqel-dev/{react,hooks}`
- Props com `undefined` explícito quando opcionais (`exactOptionalPropertyTypes: true`)

## Anti-patterns

- ❌ **Editar `src/shadcn/*`** — gerado/atualizado pela `shadcn` CLI, ignorado pelo Biome lint. Para customizar uma primitiva: faz fork do componente shadcn para `src/<feature>/<Component>.tsx` próprio e re-exporta dali, mantendo `src/shadcn/` pristine.
- ❌ **Importar `@base-ui-components/react`** — substituído por `radix-ui`. Code legacy deve migrar.
- ❌ **Hardcode de cor** — usa CSS vars shadcn (`bg-primary`, `text-foreground`, etc.) para honrar tema
- ❌ **Bundle Tailwind no dist** — apps trazem o próprio Tailwind; expomos só os tokens via `globals.css`
- ❌ **Importar de `dist/`** — usa exports declarados (`@arqel-dev/ui` ou subpaths)
- ❌ **Estado local em ResourceIndex/DataTable** — sempre lifted via callbacks
- ❌ **Duplicar `useFlash({ onMessage })`** — um único `<FlashContainer>` no AppShell
- ❌ **Recriar variants `success`/`warning` no `Badge`** — foram removidas; usa `default`/`secondary`/`outline` ou compõe com `className`

## Testing

- 70+ testes Vitest passando (jsdom + @testing-library/react + @testing-library/user-event)
- `pnpm test` watch, `pnpm exec vitest run --coverage` para relatório
- Coverage atual: ~67% global (barrels e overlays mobile do Sidebar baixam o número)

## Related

- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §UI-001..007
- API: [`PLANNING/06-api-react.md`](../../PLANNING/06-api-react.md) §8, §12
- shadcn registry: `components.json` na raiz do pacote
- Source: [`packages-js/ui/src/`](src/) — `shadcn/` (gerado), `primitives/` (barrel), `shell/`, `table/`, `form/`, `action/`, `auth/`, `flash/`, `utility/`
- Tests: [`packages-js/ui/tests/`](tests/)

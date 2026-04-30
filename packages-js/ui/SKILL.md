# SKILL.md — @arqel/ui

> Contexto canónico para AI agents.

## Purpose

`@arqel/ui` é a "casca" estrutural do admin panel: shell (AppShell + Sidebar + Topbar), página de listagem (ResourceIndex + DataTable), formulário polimórfico (FormRenderer + FieldRenderer), botões e modais de Action, flash toasts e utilitários (Breadcrumbs, PageHeader, EmptyState, ErrorState, LoadingSkeleton). Tudo é presentational — estado vive nos hooks/Inertia.

## Status

**Entregue (UI-001..006):**

- 9 entry points subpath para tree-shaking (`shell`, `resource`, `table`, `form`, `action`, `auth`, `flash`, `utility`, `utils`)
- `globals.css` com Tailwind v4 `@import` + design tokens em `oklch` + `.dark` override (exposto como `@arqel/ui/styles.css`)
- `cn()` utility (clsx + tailwind-merge)

**Shell** (UI-002):
- `<AppShell variant>`: 4 variants (`sidebar-left | sidebar-right | topbar-only | full-width`)
- `<Sidebar>`: rail desktop fixo (`--sidebar-width`, default 240px) + overlay mobile via Base UI Dialog quando `open`/`onOpenChange` fornecidos. Items via `useNavigation()` ou prop `items`. Groups, badges, `aria-current="page"`, focus ring
- `<Topbar>`: brand + theme toggle (`useTheme`) + mobile menu trigger + slots search/userMenu/tenantSwitcher
- `<MainContent>`: padding responsivo + `maxWidth` configurável (`md..7xl | none`) + slots breadcrumbs/header
- `<Footer>` minimal

**Table** (UI-003):
- `<DataTable>` TanStack Table v8 com 9 cell renderers (text/badge/boolean/date/number/icon/image/relationship/computed), seleção controlada (Shift+click range), sticky header, sort visual + `aria-sort`, loading/empty/rowActions
- `<TableFilters>` 4 tipos (select/multiSelect/text/ternary) + chip "Clear filters (n)"
- `<TablePagination>` prev/next + range "11–20 of 47" + per-page picker
- `<TableToolbar>` search + filters + bulk-action bar condicional
- `<ResourceIndex>` page-level wrapper costurando `ResourceIndexProps` server payload

**Form** (UI-004):
- `<FormRenderer>` recursivo sobre `FormSchema` (Section/Fieldset/Grid/Columns/Group/Tabs/Tab + Field)
- `<FieldRenderer>` com `FieldRegistry` (`registerField('TextInput', RichInput)`) + native HTML fallback (17 dos 21 field types). Label + asterisco required, helper/erro com `aria-describedby` + `role="alert"`
- `<FormSection>` (collapsible + aside), `<FormFieldset>`, `<FormGrid>`, `<FormTabs>` (keyboard-accessible WAI-ARIA pattern)
- `<FormActions>` submit/cancel/processing

**Action** (UI-005):
- `<Button>` primitive cva (default/outline/ghost/destructive)
- `<ActionButton>` matriz (confirm-only / form-only / ambos / direto)
- `<ActionMenu>` inline ≤ threshold, Base UI Menu acima
- `<ConfirmDialog>` Base UI Dialog com "type to confirm" + Enter submit
- `<ActionFormModal>` Base UI Dialog hospedando `<FormRenderer>` inline de `action.form`

**Flash + utility** (UI-006):
- `<FlashContainer>` consome `useFlash()` + `<FlashToast>` self-rendered (4 posições, role=alert/status, durationMs configurável)
- `<Breadcrumbs>` auto via `panel.breadcrumbs` ou explicit, `aria-current="page"`
- `<PageHeader>`, `<EmptyState>`, `<ErrorState>` (role=alert), `<LoadingSkeleton>` (line/block/circle, count)

**Auth** (UI-001):
- `<CanAccess ability="..." record={...} fallback={...}>` wrapper sobre `useCanAccess`

## Key Contracts

```tsx
import '@arqel/ui/styles.css';
import {
  AppShell, Sidebar, Topbar, MainContent, FlashContainer,
  ResourceIndex, FormRenderer, ActionButton, CanAccess,
  Breadcrumbs, PageHeader,
} from '@arqel/ui';

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

- Subpath imports preferidos para tree-shaking
- CSS exposto como `@arqel/ui/styles.css` — apps importam uma vez
- Design tokens via CSS vars (`--color-arqel-*`, `--radius-arqel-*`)
- `.dark` class flip aplicada por `<ThemeProvider>` de `@arqel/react`
- Componentes presentational — callbacks lifted (sem fetch interno)
- `peerDependencies`: Base UI / TanStack Table / lucide-react / @arqel/{react,hooks}
- Props com `undefined` explícito quando opcionais (necessário por `exactOptionalPropertyTypes: true`)

## Anti-patterns

- ❌ **Hardcode de cor** — usa CSS vars para honrar tema
- ❌ **Bundle Tailwind** — apps trazem o próprio Tailwind; expomos só os tokens
- ❌ **Importar de `dist/`** — usa exports declarados (`@arqel/ui` ou subpaths)
- ❌ **Estado local em ResourceIndex/DataTable** — sempre lifted via callbacks
- ❌ **Duplicar `useFlash({ onMessage })`** — um único `<FlashContainer>` no AppShell

## Testing

- 70 testes Vitest passando (jsdom + @testing-library/react + @testing-library/user-event)
- `pnpm test` para watch, `pnpm exec vitest run --coverage` para relatório
- Coverage atual: ~67% global; barrels (`index.ts`) e overlay mobile do Sidebar (Base UI Portal) baixam o número

### Coverage

- Total: 126 testes Vitest passando (inclui surface tests para `widgets/` e `palette/` garantindo o contrato de import dos consumidores)

## Related

- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §UI-001..007
- API: [`PLANNING/06-api-react.md`](../../PLANNING/06-api-react.md) §8, §12
- Source: [`packages-js/ui/src/`](src/)
- Tests: [`packages-js/ui/tests/`](tests/)

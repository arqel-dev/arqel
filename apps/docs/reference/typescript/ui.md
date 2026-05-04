# `@arqel-dev/ui` — API Reference

Componentes estruturais. 9 entry points subpath.

```ts
import '@arqel-dev/ui/styles.css';                                    // Tailwind v4 + tokens oklch
import { cn } from '@arqel-dev/ui/utils';
import { Button, CanAccess } from '@arqel-dev/ui';
import { AppShell, Sidebar, Topbar, MainContent, Footer } from '@arqel-dev/ui/shell';
import { ResourceIndex } from '@arqel-dev/ui/resource';
import { DataTable, TableFilters, TablePagination, TableToolbar } from '@arqel-dev/ui/table';
import { FormRenderer, FieldRenderer, FormSection, FormGrid, FormTabs,
         registerField, getFieldComponent, getRegisteredFields } from '@arqel-dev/ui/form';
import { ActionButton, ActionMenu, ConfirmDialog, ActionFormModal } from '@arqel-dev/ui/action';
import { FlashContainer, FlashToast } from '@arqel-dev/ui/flash';
import { Breadcrumbs, PageHeader, EmptyState, ErrorState, LoadingSkeleton } from '@arqel-dev/ui/utility';
```

## Action

| Component | Props chave |
|---|---|
| `<Button>` | cva: `variant: 'default' \| 'outline' \| 'ghost' \| 'destructive'`, `size: 'sm' \| 'md' \| 'lg' \| 'icon'` |
| `<CanAccess>` | `ability: string`, `record?`, `fallback?: ReactNode` |

## Shell

| Component | Props chave |
|---|---|
| `<AppShell>` | `variant: 'sidebar-left' \| 'sidebar-right' \| 'topbar-only' \| 'full-width'` |
| `<Sidebar>` | `items?: NavigationEntry[]` (lazy `useNavigation()` se omitido), `open?`, `onOpenChange?` |
| `<Topbar>` | `brand?`, `mobileMenuOnly?`, slots `search`/`userMenu`/`tenantSwitcher` |
| `<MainContent>` | `maxWidth: 'md' \| 'lg' \| 'xl' \| '2xl' \| ... \| '7xl' \| 'none'`, slots `breadcrumbs`/`header` |
| `<Footer>` | minimal layout |

## Table

| Component | Props |
|---|---|
| `<DataTable<T>>` | `data: T[]`, `columns: ColumnSchema[]`, `selection?`, `onSelectionChange?`, `sort?`, `onSortChange?`, `loading?`, `empty?` |
| `<TableFilters>` | `filters: FilterSchema[]`, `values`, `onChange` |
| `<TablePagination>` | `page`, `perPage`, `total`, `onPageChange`, `onPerPageChange` |
| `<TableToolbar>` | `search?`, `filters?`, `bulkActions?`, `selectedCount?` |
| `<ResourceIndex<T>>` | costura `ResourceIndexProps<T>` em toda a stack |

`<DataTable>` usa TanStack Table v8, suporta `Shift+click` range select, sticky header, `aria-sort`.

## Form

| Component | Props chave |
|---|---|
| `<FormRenderer>` | `schema: FormSchema`, `values`, `onChange`, `errors?` |
| `<FieldRenderer>` | `field: FieldSchema`, `value`, `onChange`, `errors?`, `disabled?`, `inputId`, `describedBy?` |
| `<FormSection>` | `heading?`, `description?`, `collapsible?`, `aside?` |
| `<FormFieldset>` | semantic com `<legend>` |
| `<FormGrid>` | `config: { columns: number \| Record<string, number> }` |
| `<FormTabs>` | WAI-ARIA roving tabindex, Arrow/Home/End |
| `<FormActions>` | submit/cancel/processing |

### FieldRegistry

```ts
function registerField(name: string, component: FieldComponent): void
function getFieldComponent(name: string): FieldComponent | undefined
function getRegisteredFields(): string[]                  // ordenados
function clearFieldRegistry(): void                       // testes
```

`<FieldRenderer>` resolve via `field.component ? getFieldComponent(field.component) : undefined`, fallback para 17 nativos em `nativeFields.tsx`.

## Action (interaction)

| Component | Props chave |
|---|---|
| `<ActionButton>` | `action: ActionSchema`, `record?`. Matriz: confirm + form + nada |
| `<ActionMenu>` | `actions: ActionSchema[]`, `inlineThreshold?: number = 3` |
| `<ConfirmDialog>` | `open`, `onOpenChange`, `title`, `description?`, `requiresText?`, `color?` |
| `<ActionFormModal>` | hospeda `<FormRenderer>` inline com `action.form` |

## Flash

| Component | Props |
|---|---|
| `<FlashContainer>` | `position?: 'top-right' \| 'top-left' \| 'bottom-right' \| 'bottom-left'` |
| `<FlashToast>` | `kind: 'success' \| 'error' \| 'info' \| 'warning'`, `text`, `durationMs?` (`0` opta-out auto-dismiss) |

`role="alert"`+`aria-live="assertive"` para erros, `role="status"`+`aria-live="polite"` outros.

## Utility

| Component | Props |
|---|---|
| `<Breadcrumbs>` | `items?: BreadcrumbItem[]` (lazy `usePage()` se omitido) |
| `<PageHeader>` | `title`, `description?`, `actions?` |
| `<EmptyState>` | `icon?`, `title`, `description?`, `action?` |
| `<ErrorState>` | `kind: '404' \| '403' \| '500' \| 'generic'`, role="alert" |
| `<LoadingSkeleton>` | `variant: 'line' \| 'block' \| 'circle'`, `count?` |

## `cn(...inputs)`

`clsx` + `tailwind-merge` em uma só função.

## Tokens CSS

`@arqel-dev/ui/styles.css` declara em `oklch`:

- `--color-arqel-{bg,fg,primary,secondary,destructive,success,warning,muted,border,ring}`
- `--radius-arqel`, `--radius-arqel-sm`, `--radius-arqel-lg`
- `.dark` flip override

## Related

- SKILL: [`packages-js/ui/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages-js/ui/SKILL.md)
- Próximo: [`@arqel-dev/fields`](/reference/typescript/fields)

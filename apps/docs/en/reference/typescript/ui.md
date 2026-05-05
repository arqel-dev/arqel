# `@arqel-dev/ui` — API Reference

Structural components based on [shadcn/ui](https://ui.shadcn.com/) (variant `new-york`) + [Radix UI](https://www.radix-ui.com/) primitives (the `radix-ui` package). Tailwind v4 + shadcn tokens in `oklch`.

```ts
import '@arqel-dev/ui/styles.css';                                    // Tailwind v4 + shadcn tokens
import { cn } from '@arqel-dev/ui/utils';
import { Button, CanAccess } from '@arqel-dev/ui';
import { AppShell, Sidebar, SidebarProvider, SidebarTrigger, SidebarInset,
         Topbar, MainContent, Footer } from '@arqel-dev/ui/shell';
import { ResourceIndex } from '@arqel-dev/ui/resource';
import { DataTable, TableFilters, TablePagination, TableToolbar } from '@arqel-dev/ui/table';
import { FormRenderer, FieldRenderer, FormSection, FormGrid, FormTabs,
         registerField, getFieldComponent, getRegisteredFields } from '@arqel-dev/ui/form';
import { ActionButton, ActionMenu, ConfirmDialog, ActionFormModal } from '@arqel-dev/ui/action';
import { FlashContainer, FlashToast } from '@arqel-dev/ui/flash';
import { Breadcrumbs, PageHeader, EmptyState, ErrorState, LoadingSkeleton } from '@arqel-dev/ui/utility';
// Re-exported shadcn primitives
import { Input, Label, Card, CardHeader, CardContent, Alert, Badge, Select,
         Textarea, Checkbox, Separator, Skeleton, Field, Dialog, DropdownMenu,
         Sheet, Tooltip } from '@arqel-dev/ui/primitives';
```

## Re-exported shadcn primitives

`@arqel-dev/ui/primitives` (and the top-level re-exports) expose the shadcn components copied into the internal registry. Consumer apps don't need to run `npx shadcn add` — they are already covered.

### Form primitives

| Component | Sub-components | Notes |
|---|---|---|
| `Input` | — | native text/email/password |
| `Label` | — | Radix Label |
| `Textarea` | — | resize-none by default |
| `Checkbox` | — | Radix Checkbox |
| `Select` | `SelectTrigger`, `SelectContent`, `SelectItem`, `SelectValue`, `SelectGroup`, `SelectLabel`, `SelectSeparator` | Radix Select |
| `Field` | `FieldGroup`, `FieldLabel`, `FieldError`, `FieldDescription`, `FieldSeparator` | shadcn composable wrapper (`field` block) — used by `<FieldRenderer>` |

### Layout & display

| Component | Sub-components |
|---|---|
| `Card` | `CardHeader`, `CardTitle`, `CardDescription`, `CardContent`, `CardFooter`, `CardAction` |
| `Alert` | `AlertTitle`, `AlertDescription` |
| `Badge` | — (cva variants: `default`, `secondary`, `destructive`, `outline`) |
| `Separator` | — (Radix Separator) |
| `Skeleton` | — |

### Overlays (Radix-based)

| Component | Notes |
|---|---|
| `Dialog` (+ `DialogTrigger`/`Content`/`Header`/`Footer`/`Title`/`Description`/`Close`) | Radix Dialog |
| `DropdownMenu` (+ `DropdownMenuTrigger`/`Content`/`Item`/`CheckboxItem`/`RadioItem`/`Label`/`Separator`/`Sub`/`SubTrigger`/`SubContent`) | Radix DropdownMenu |
| `Sheet` (+ `SheetTrigger`/`Content`/`Header`/`Footer`/`Title`/`Description`) | Radix Dialog with side variants |
| `Tooltip` (+ `TooltipTrigger`/`Content`/`Provider`) | Radix Tooltip |

### High-level Arqel wrappers

| Component | Function |
|---|---|
| `<ConfirmDialog>` | Wrap of `Dialog` with confirmation flow (optional typed-text gate) |
| `<ActionMenu>` | Wrap of `DropdownMenu` for action lists with inline threshold |
| `<ActionFormModal>` | Wrap of `Dialog` that hosts `<FormRenderer>` for actions with `form()` |

## Action (button-level)

| Component | Key props |
|---|---|
| `<Button>` | shadcn cva: `variant: 'default' \| 'destructive' \| 'outline' \| 'secondary' \| 'ghost' \| 'link'`, `size: 'default' \| 'sm' \| 'lg' \| 'icon'` |
| `<CanAccess>` | `ability: string`, `record?`, `fallback?: ReactNode` |

## Shell

Based on the shadcn block **`sidebar-07`** (collapsible icon sidebar with sub-items).

| Component | Key props |
|---|---|
| `<AppShell>` | `variant: 'sidebar-left' \| 'sidebar-right' \| 'topbar-only' \| 'full-width'`. Already wraps `<SidebarProvider>` |
| `<SidebarProvider>` | shadcn root — controls open/closed state via cookie + context |
| `<Sidebar>` | shadcn `sidebar-07` block. `items?: NavigationItemPayload[]` (lazy `useNavigation()` if omitted), `collapsible: 'icon' \| 'offcanvas' \| 'none'` |
| `<SidebarTrigger>` | hamburger button (mobile + desktop collapse) |
| `<SidebarInset>` | wrapper for the main content — applies dynamic margin based on the sidebar state |
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
| `<ResourceIndex<T>>` | wires `ResourceIndexProps<T>` across the stack |

`<DataTable>` uses TanStack Table v8, supports `Shift+click` range select, sticky header, `aria-sort`.

## Form

| Component | Key props |
|---|---|
| `<FormRenderer>` | `schema: FormSchema`, `values`, `onChange`, `errors?` |
| `<FieldRenderer>` | `field: FieldSchema`, `value`, `onChange`, `errors?`, `disabled?`, `inputId`, `describedBy?` |
| `<FormSection>` | `heading?`, `description?`, `collapsible?`, `aside?` |
| `<FormFieldset>` | semantic with `<legend>` |
| `<FormGrid>` | `config: { columns: number \| Record<string, number> }` |
| `<FormTabs>` | WAI-ARIA roving tabindex, Arrow/Home/End |
| `<FormActions>` | submit/cancel/processing |

### FieldRegistry

```ts
function registerField(name: string, component: FieldComponent): void
function getFieldComponent(name: string): FieldComponent | undefined
function getRegisteredFields(): string[]                  // sorted
function clearFieldRegistry(): void                       // tests
```

`<FieldRenderer>` resolves via `field.component ? getFieldComponent(field.component) : undefined`, falling back to 17 native ones in `nativeFields.tsx`.

## Action (interaction)

| Component | Key props |
|---|---|
| `<ActionButton>` | `action: ActionSchema`, `record?`. Matrix: confirm + form + nothing |
| `<ActionMenu>` | `actions: ActionSchema[]`, `inlineThreshold?: number = 3` |
| `<ConfirmDialog>` | `open`, `onOpenChange`, `title`, `description?`, `requiresText?`, `color?` |
| `<ActionFormModal>` | hosts `<FormRenderer>` inline with `action.form` |

## Flash

| Component | Props |
|---|---|
| `<FlashContainer>` | `position?: 'top-right' \| 'top-left' \| 'bottom-right' \| 'bottom-left'` |
| `<FlashToast>` | `kind: 'success' \| 'error' \| 'info' \| 'warning'`, `text`, `durationMs?` (`0` opts out of auto-dismiss) |

`role="alert"`+`aria-live="assertive"` for errors, `role="status"`+`aria-live="polite"` for the rest.

## Utility

| Component | Props |
|---|---|
| `<Breadcrumbs>` | `items?: BreadcrumbItem[]` (lazy `usePage()` if omitted) |
| `<PageHeader>` | `title`, `description?`, `actions?` |
| `<EmptyState>` | `icon?`, `title`, `description?`, `action?` |
| `<ErrorState>` | `kind: '404' \| '403' \| '500' \| 'generic'`, role="alert" |
| `<LoadingSkeleton>` | `variant: 'line' \| 'block' \| 'circle'`, `count?` |

## `cn(...inputs)`

`clsx` + `tailwind-merge` in a single function.

## CSS tokens (shadcn)

`@arqel-dev/ui/styles.css` declares them in `oklch`, following the shadcn convention:

- `--background`, `--foreground`
- `--primary`, `--primary-foreground`
- `--secondary`, `--secondary-foreground`
- `--destructive`, `--destructive-foreground`
- `--muted`, `--muted-foreground`
- `--accent`, `--accent-foreground`
- `--card`, `--card-foreground`
- `--popover`, `--popover-foreground`
- `--border`, `--input`, `--ring`
- `--sidebar`, `--sidebar-foreground`, `--sidebar-primary`, `--sidebar-accent`, `--sidebar-border`, `--sidebar-ring`
- `--radius` + derived scale `--radius-sm`, `--radius-md`, `--radius-lg`, `--radius-xl`
- `.dark` flip override

Chart tokens (`--chart-1` … `--chart-5`) are also present for future visualizations.

## Related

- SKILL: [`packages-js/ui/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages-js/ui/SKILL.md)
- Next: [`@arqel-dev/fields`](/reference/typescript/fields)

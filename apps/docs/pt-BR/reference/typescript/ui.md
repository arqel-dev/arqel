# `@arqel-dev/ui` — API Reference

Componentes estruturais baseados em [shadcn/ui](https://ui.shadcn.com/) (variante `new-york`) + primitivas [Radix UI](https://www.radix-ui.com/) (`radix-ui` package). Tailwind v4 + tokens shadcn em `oklch`.

```ts
import '@arqel-dev/ui/styles.css';                                    // Tailwind v4 + tokens shadcn
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
// Primitivas shadcn re-exportadas
import { Input, Label, Card, CardHeader, CardContent, Alert, Badge, Select,
         Textarea, Checkbox, Separator, Skeleton, Field, Dialog, DropdownMenu,
         Sheet, Tooltip } from '@arqel-dev/ui/primitives';
```

## Primitivas shadcn re-exportadas

`@arqel-dev/ui/primitives` (e re-exports do top-level) expõem os componentes shadcn copiados ao registry interno. Apps consumidoras não precisam rodar `npx shadcn add` — já vêm cobertos.

### Form primitives

| Componente | Sub-componentes | Notas |
|---|---|---|
| `Input` | — | text/email/password native |
| `Label` | — | Radix Label |
| `Textarea` | — | resize-none por defeito |
| `Checkbox` | — | Radix Checkbox |
| `Select` | `SelectTrigger`, `SelectContent`, `SelectItem`, `SelectValue`, `SelectGroup`, `SelectLabel`, `SelectSeparator` | Radix Select |
| `Field` | `FieldGroup`, `FieldLabel`, `FieldError`, `FieldDescription`, `FieldSeparator` | wrapper composable shadcn (`field` block) — usado pelo `<FieldRenderer>` |

### Layout & display

| Componente | Sub-componentes |
|---|---|
| `Card` | `CardHeader`, `CardTitle`, `CardDescription`, `CardContent`, `CardFooter`, `CardAction` |
| `Alert` | `AlertTitle`, `AlertDescription` |
| `Badge` | — (cva variants: `default`, `secondary`, `destructive`, `outline`) |
| `Separator` | — (Radix Separator) |
| `Skeleton` | — |

### Overlays (Radix-based)

| Componente | Notas |
|---|---|
| `Dialog` (+ `DialogTrigger`/`Content`/`Header`/`Footer`/`Title`/`Description`/`Close`) | Radix Dialog |
| `DropdownMenu` (+ `DropdownMenuTrigger`/`Content`/`Item`/`CheckboxItem`/`RadioItem`/`Label`/`Separator`/`Sub`/`SubTrigger`/`SubContent`) | Radix DropdownMenu |
| `Sheet` (+ `SheetTrigger`/`Content`/`Header`/`Footer`/`Title`/`Description`) | Radix Dialog com side variants |
| `Tooltip` (+ `TooltipTrigger`/`Content`/`Provider`) | Radix Tooltip |

### High-level wrappers Arqel

| Componente | Função |
|---|---|
| `<ConfirmDialog>` | Wrap de `Dialog` com confirmation flow (typed-text gate opcional) |
| `<ActionMenu>` | Wrap de `DropdownMenu` para listas de actions com inline threshold |
| `<ActionFormModal>` | Wrap de `Dialog` que hospeda `<FormRenderer>` para actions com `form()` |

## Action (button-level)

| Component | Props chave |
|---|---|
| `<Button>` | shadcn cva: `variant: 'default' \| 'destructive' \| 'outline' \| 'secondary' \| 'ghost' \| 'link'`, `size: 'default' \| 'sm' \| 'lg' \| 'icon'` |
| `<CanAccess>` | `ability: string`, `record?`, `fallback?: ReactNode` |

## Shell

Baseado no block shadcn **`sidebar-07`** (collapsible icon sidebar com sub-items).

| Component | Props chave |
|---|---|
| `<AppShell>` | `variant: 'sidebar-left' \| 'sidebar-right' \| 'topbar-only' \| 'full-width'`. Já wrapa `<SidebarProvider>` |
| `<SidebarProvider>` | shadcn root — controla open/closed state via cookie + context |
| `<Sidebar>` | shadcn `sidebar-07` block. `items?: NavigationItemPayload[]` (lazy `useNavigation()` se omitido), `collapsible: 'icon' \| 'offcanvas' \| 'none'` |
| `<SidebarTrigger>` | hamburguer button (mobile + collapse desktop) |
| `<SidebarInset>` | wrapper do conteúdo principal — aplica margem dinâmica baseada no estado da sidebar |
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

## Tokens CSS (shadcn)

`@arqel-dev/ui/styles.css` declara em `oklch`, seguindo a convenção shadcn:

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
- `--radius` + escala derivada `--radius-sm`, `--radius-md`, `--radius-lg`, `--radius-xl`
- `.dark` flip override

Tokens chart (`--chart-1` … `--chart-5`) também presentes para futuras visualizações.

## Related

- SKILL: [`packages-js/ui/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages-js/ui/SKILL.md)
- Próximo: [`@arqel-dev/fields`](/pt-BR/reference/typescript/fields)

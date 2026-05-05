# `@arqel-dev/ui` — Referencia de API

Componentes estructurales basados en [shadcn/ui](https://ui.shadcn.com/) (variante `new-york`) + primitivas de [Radix UI](https://www.radix-ui.com/) (el paquete `radix-ui`). Tailwind v4 + tokens shadcn en `oklch`.

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

## Primitivas shadcn re-exportadas

`@arqel-dev/ui/primitives` (y los re-exports de top-level) exponen los componentes shadcn copiados al registry interno. Las apps consumidoras no necesitan ejecutar `npx shadcn add` — ya están cubiertos.

### Primitivas de Form

| Componente | Sub-componentes | Notas |
|---|---|---|
| `Input` | — | text/email/password nativo |
| `Label` | — | Radix Label |
| `Textarea` | — | resize-none por defecto |
| `Checkbox` | — | Radix Checkbox |
| `Select` | `SelectTrigger`, `SelectContent`, `SelectItem`, `SelectValue`, `SelectGroup`, `SelectLabel`, `SelectSeparator` | Radix Select |
| `Field` | `FieldGroup`, `FieldLabel`, `FieldError`, `FieldDescription`, `FieldSeparator` | wrapper componible shadcn (bloque `field`) — usado por `<FieldRenderer>` |

### Layout & display

| Componente | Sub-componentes |
|---|---|
| `Card` | `CardHeader`, `CardTitle`, `CardDescription`, `CardContent`, `CardFooter`, `CardAction` |
| `Alert` | `AlertTitle`, `AlertDescription` |
| `Badge` | — (variantes cva: `default`, `secondary`, `destructive`, `outline`) |
| `Separator` | — (Radix Separator) |
| `Skeleton` | — |

### Overlays (basados en Radix)

| Componente | Notas |
|---|---|
| `Dialog` (+ `DialogTrigger`/`Content`/`Header`/`Footer`/`Title`/`Description`/`Close`) | Radix Dialog |
| `DropdownMenu` (+ `DropdownMenuTrigger`/`Content`/`Item`/`CheckboxItem`/`RadioItem`/`Label`/`Separator`/`Sub`/`SubTrigger`/`SubContent`) | Radix DropdownMenu |
| `Sheet` (+ `SheetTrigger`/`Content`/`Header`/`Footer`/`Title`/`Description`) | Radix Dialog con variantes de lado |
| `Tooltip` (+ `TooltipTrigger`/`Content`/`Provider`) | Radix Tooltip |

### Wrappers de Arqel de alto nivel

| Componente | Función |
|---|---|
| `<ConfirmDialog>` | Wrap de `Dialog` con flujo de confirmación (gate opcional con texto tipado) |
| `<ActionMenu>` | Wrap de `DropdownMenu` para listas de Action con threshold inline |
| `<ActionFormModal>` | Wrap de `Dialog` que hospeda `<FormRenderer>` para Actions con `form()` |

## Action (a nivel de botón)

| Componente | Props clave |
|---|---|
| `<Button>` | cva shadcn: `variant: 'default' \| 'destructive' \| 'outline' \| 'secondary' \| 'ghost' \| 'link'`, `size: 'default' \| 'sm' \| 'lg' \| 'icon'` |
| `<CanAccess>` | `ability: string`, `record?`, `fallback?: ReactNode` |

## Shell

Basado en el bloque shadcn **`sidebar-07`** (Sidebar colapsable de íconos con sub-items).

| Componente | Props clave |
|---|---|
| `<AppShell>` | `variant: 'sidebar-left' \| 'sidebar-right' \| 'topbar-only' \| 'full-width'`. Ya envuelve `<SidebarProvider>` |
| `<SidebarProvider>` | raíz shadcn — controla el estado open/closed vía cookie + context |
| `<Sidebar>` | bloque shadcn `sidebar-07`. `items?: NavigationItemPayload[]` (lazy `useNavigation()` si se omite), `collapsible: 'icon' \| 'offcanvas' \| 'none'` |
| `<SidebarTrigger>` | botón hamburguesa (mobile + collapse desktop) |
| `<SidebarInset>` | wrapper para el contenido principal — aplica margin dinámico según el estado del Sidebar |
| `<Topbar>` | `brand?`, `mobileMenuOnly?`, slots `search`/`userMenu`/`tenantSwitcher` |
| `<MainContent>` | `maxWidth: 'md' \| 'lg' \| 'xl' \| '2xl' \| ... \| '7xl' \| 'none'`, slots `breadcrumbs`/`header` |
| `<Footer>` | layout mínimo |

## Table

| Componente | Props |
|---|---|
| `<DataTable<T>>` | `data: T[]`, `columns: ColumnSchema[]`, `selection?`, `onSelectionChange?`, `sort?`, `onSortChange?`, `loading?`, `empty?` |
| `<TableFilters>` | `filters: FilterSchema[]`, `values`, `onChange` |
| `<TablePagination>` | `page`, `perPage`, `total`, `onPageChange`, `onPerPageChange` |
| `<TableToolbar>` | `search?`, `filters?`, `bulkActions?`, `selectedCount?` |
| `<ResourceIndex<T>>` | conecta `ResourceIndexProps<T>` a través del stack |

`<DataTable>` usa TanStack Table v8, soporta selección de rango con `Shift+click`, header sticky, `aria-sort`.

## Form

| Componente | Props clave |
|---|---|
| `<FormRenderer>` | `schema: FormSchema`, `values`, `onChange`, `errors?` |
| `<FieldRenderer>` | `field: FieldSchema`, `value`, `onChange`, `errors?`, `disabled?`, `inputId`, `describedBy?` |
| `<FormSection>` | `heading?`, `description?`, `collapsible?`, `aside?` |
| `<FormFieldset>` | semántico con `<legend>` |
| `<FormGrid>` | `config: { columns: number \| Record<string, number> }` |
| `<FormTabs>` | tabindex roving WAI-ARIA, Arrow/Home/End |
| `<FormActions>` | submit/cancel/processing |

### FieldRegistry

```ts
function registerField(name: string, component: FieldComponent): void
function getFieldComponent(name: string): FieldComponent | undefined
function getRegisteredFields(): string[]                  // sorted
function clearFieldRegistry(): void                       // tests
```

`<FieldRenderer>` resuelve vía `field.component ? getFieldComponent(field.component) : undefined`, haciendo fallback a 17 nativos en `nativeFields.tsx`.

## Action (interacción)

| Componente | Props clave |
|---|---|
| `<ActionButton>` | `action: ActionSchema`, `record?`. Matriz: confirm + form + nada |
| `<ActionMenu>` | `actions: ActionSchema[]`, `inlineThreshold?: number = 3` |
| `<ConfirmDialog>` | `open`, `onOpenChange`, `title`, `description?`, `requiresText?`, `color?` |
| `<ActionFormModal>` | hospeda `<FormRenderer>` inline con `action.form` |

## Flash

| Componente | Props |
|---|---|
| `<FlashContainer>` | `position?: 'top-right' \| 'top-left' \| 'bottom-right' \| 'bottom-left'` |
| `<FlashToast>` | `kind: 'success' \| 'error' \| 'info' \| 'warning'`, `text`, `durationMs?` (`0` opta por no auto-dismiss) |

`role="alert"`+`aria-live="assertive"` para errores, `role="status"`+`aria-live="polite"` para el resto.

## Utility

| Componente | Props |
|---|---|
| `<Breadcrumbs>` | `items?: BreadcrumbItem[]` (lazy `usePage()` si se omite) |
| `<PageHeader>` | `title`, `description?`, `actions?` |
| `<EmptyState>` | `icon?`, `title`, `description?`, `action?` |
| `<ErrorState>` | `kind: '404' \| '403' \| '500' \| 'generic'`, role="alert" |
| `<LoadingSkeleton>` | `variant: 'line' \| 'block' \| 'circle'`, `count?` |

## `cn(...inputs)`

`clsx` + `tailwind-merge` en una sola función.

## Tokens CSS (shadcn)

`@arqel-dev/ui/styles.css` los declara en `oklch`, siguiendo la convención shadcn:

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
- override flip `.dark`

Los tokens de chart (`--chart-1` … `--chart-5`) también están presentes para futuras visualizaciones.

## Relacionado

- SKILL: [`packages-js/ui/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages-js/ui/SKILL.md)
- Siguiente: [`@arqel-dev/fields`](/es/reference/typescript/fields)

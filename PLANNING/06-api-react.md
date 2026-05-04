# 06 — API React / TypeScript

> Contratos do lado React de Arqel. Espelha os contratos PHP (`05-api-php.md`) do lado cliente.

## 1. Stack React

- **React 19.2.3+** (Compiler habilitado)
- **TypeScript 5.5+** strict mode
- **Inertia.js 3 React adapter** (`@inertiajs/react`)
- **Tailwind CSS v4** + CSS variables
- **ShadCN CLI v4** primitives (Base UI default)
- **TanStack Table v8** para DataTable
- **Zod 4** para schema validation (espelha Laravel rules)
- **Lucide React** para icons (Heroicons compat via map)
- **Recharts** para charts em widgets

## 2. Shared Props globais (Inertia)

Toda a página Inertia recebe `SharedProps` via `usePage().props`:

```typescript
// packages-js/types/src/inertia.ts

import type { User } from './user'
import type { Panel, Tenant } from './resources'
import type { Flash } from './flash'

export interface SharedProps {
    auth: {
        user: User | null
        can: Record<string, boolean>           // Global abilities
    }
    panel: Panel                                // Current panel config
    tenant: Tenant | null                       // Current tenant (Fase 2+)
    flash: Flash
    translations: Record<string, string>
    arqel: {
        version: string
        build: string
    }
}

// Extend Inertia's PageProps
declare module '@inertiajs/core' {
    interface PageProps extends SharedProps {}
}
```

**Acesso em components:**

```tsx
import { usePage } from '@inertiajs/react'

function MyComponent() {
    const { auth, panel, flash } = usePage<SharedProps>().props
    
    if (!auth.user) return null
    return <div>Welcome, {auth.user.name}</div>
}
```

## 3. Resource page props

### 3.1 Index page (listagem)

```typescript
import type { RecordType, PaginatedRecords, FieldSchema, ActionSchema, FilterSchema } from '@arqel-dev/types'

export interface ResourceIndexProps<T extends RecordType = RecordType> {
    resource: ResourceMeta
    records: PaginatedRecords<T>
    columns: ColumnSchema[]
    filters: FilterSchema[]
    search: string | null
    sort: { column: string; direction: 'asc' | 'desc' } | null
    selectedIds: (string | number)[]
    actions: {
        row: ActionSchema[]
        bulk: ActionSchema[]
        toolbar: ActionSchema[]
    }
    can: {
        create: boolean
        viewAny: boolean
    }
}

export interface ResourceMeta {
    name: string                            // 'User'
    pluralName: string                      // 'Users'
    slug: string                            // 'users'
    icon: string | null
    urls: {
        index: string
        create: string
        show: (id: string | number) => string
        edit: (id: string | number) => string
        destroy: (id: string | number) => string
    }
}

export interface PaginatedRecords<T> {
    data: T[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number
    to: number
    links: { url: string | null; label: string; active: boolean }[]
}
```

### 3.2 Create/Edit page

```typescript
export interface ResourceCreateProps<T extends RecordType = RecordType> {
    resource: ResourceMeta
    fields: FieldSchema[]
    form: FormSchema
    defaults: Partial<T>
    errors: Record<string, string[]>
    can: { create: boolean }
}

export interface ResourceEditProps<T extends RecordType = RecordType> 
    extends ResourceCreateProps<T> {
    record: T
    can: {
        update: boolean
        delete: boolean
        restore?: boolean
    }
}
```

### 3.3 Detail page

```typescript
export interface ResourceDetailProps<T extends RecordType = RecordType> {
    resource: ResourceMeta
    record: T
    fields: FieldSchema[]
    actions: ActionSchema[]                 // Record-level actions
    relationships?: Record<string, PaginatedRecords<RecordType>>
    can: {
        view: boolean
        update: boolean
        delete: boolean
    }
}
```

## 4. FieldSchema

Representação JSON-serializada dos Fields PHP.

```typescript
export interface FieldSchema {
    type: FieldType                         // 'text' | 'select' | 'belongsTo' | ...
    name: string                            // 'email'
    label: string                           // 'Email Address'
    component: string                       // 'EmailInput' (React component name)
    
    // Common properties
    required?: boolean
    readonly?: boolean
    disabled?: boolean
    placeholder?: string | null
    helperText?: string | null
    defaultValue?: unknown
    
    // Validation
    validation?: {
        rules: string[]                     // ['required', 'email', 'max:255']
        zodSchema?: string                  // Serialized Zod schema
    }
    
    // Layout
    columnSpan?: number | 'full'
    
    // Visibility
    hiddenOnCreate?: boolean
    hiddenOnEdit?: boolean
    hiddenOnDetail?: boolean
    visibleIf?: {                           // Conditional visibility
        field: string
        value: unknown
        operator?: '=' | '!=' | 'in' | 'notIn'
    }
    
    // Live/reactive
    live?: boolean
    liveDebounce?: number
    dependsOn?: string[]
    
    // Authorization
    canSee?: boolean                        // Resolved server-side
    canEdit?: boolean
    
    // Type-specific props (discriminated union)
    props: FieldProps<FieldType>
}

export type FieldType =
    | 'text'
    | 'textarea'
    | 'number'
    | 'currency'
    | 'boolean'
    | 'toggle'
    | 'select'
    | 'multiSelect'
    | 'radio'
    | 'email'
    | 'url'
    | 'password'
    | 'slug'
    | 'date'
    | 'dateTime'
    | 'belongsTo'
    | 'hasMany'
    | 'file'
    | 'image'
    | 'color'
    | 'hidden'
    // Fase 2+
    | 'richText'
    | 'markdown'
    | 'code'
    | 'repeater'
    | 'builder'
    | 'keyValue'
    | 'tags'
    | 'wizard'
    | 'tabs'
```

### 4.1 Field props por tipo (exemplos)

```typescript
// Discriminated union types
export type FieldProps<T extends FieldType> =
    T extends 'text' ? TextFieldProps :
    T extends 'select' ? SelectFieldProps :
    T extends 'belongsTo' ? BelongsToFieldProps :
    T extends 'image' ? ImageFieldProps :
    Record<string, unknown>

export interface TextFieldProps {
    maxLength?: number
    minLength?: number
    pattern?: string
    autocomplete?: string
}

export interface SelectFieldProps {
    options: Array<{ value: string | number; label: string }>
    searchable?: boolean
    multiple?: boolean
    native?: boolean
    creatable?: boolean
}

export interface BelongsToFieldProps {
    relatedResource: string                 // 'UserResource'
    searchRoute: string                     // '/admin/api/resources/user/search'
    searchColumns: string[]
    preload: boolean
    optionLabel?: string | null             // Template: "{{name}} ({{email}})"
    createRoute?: string                    // If createOptionForm enabled
}

export interface ImageFieldProps {
    disk: string
    directory: string | null
    visibility: 'public' | 'private'
    maxSize: number                         // KB
    acceptedTypes: string[]                 // MIME types
    multiple: boolean
    reorderable: boolean
    aspectRatio?: string                    // '1:1', '16:9'
    resizeTargetWidth?: number
}
```

## 5. ActionSchema

```typescript
export interface ActionSchema {
    name: string
    label: string
    icon?: string
    color: ActionColor                      // 'primary' | 'destructive' | 'success' | ...
    variant: 'default' | 'outline' | 'ghost' | 'destructive'
    type: 'row' | 'bulk' | 'toolbar' | 'header'
    
    // Invocation
    url?: string                            // For simple GET/redirect actions
    method?: 'GET' | 'POST' | 'PUT' | 'DELETE'
    routeName?: string                      // Named route fallback
    
    // Confirmation
    requiresConfirmation?: boolean
    confirmationModal?: {
        heading: string
        description?: string
        icon?: string
        color: 'destructive' | 'warning' | 'info'
        confirmButtonLabel: string
        cancelButtonLabel: string
        requiresText?: string               // "DELETE" → user must type to confirm
    }
    
    // Form modal (actions with forms)
    form?: FieldSchema[]
    
    // Async/queued
    queued?: boolean
    progress?: boolean                      // Progress tracking via Reverb
    
    // Authorization (resolved server-side)
    can: boolean
    
    // Success notification
    successNotification?: string
}

export type ActionColor = 
    | 'primary' 
    | 'secondary' 
    | 'destructive' 
    | 'success' 
    | 'warning' 
    | 'info'
```

## 6. FilterSchema

```typescript
export interface FilterSchema {
    name: string
    label: string
    type: FilterType
    defaultValue?: unknown
    persist?: boolean                       // Persist in URL / localStorage
    props: FilterProps<FilterType>
}

export type FilterType = 
    | 'select' 
    | 'multiSelect'
    | 'dateRange'
    | 'text'
    | 'ternary'                             // 3-state: true | false | null
    | 'queryBuilder'                        // Fase 2

export interface SelectFilterProps {
    options: Array<{ value: string | number; label: string }>
}

export interface DateRangeFilterProps {
    minDate?: string
    maxDate?: string
    defaultRange?: { from: string; to: string }
}

// ... etc
```

## 7. ColumnSchema

```typescript
export interface ColumnSchema {
    name: string
    label: string
    type: ColumnType
    sortable: boolean
    searchable: boolean
    copyable?: boolean
    hidden?: boolean
    hiddenOnMobile?: boolean
    alignment?: 'start' | 'center' | 'end'
    width?: string                          // '200px', '15%'
    props: ColumnProps<ColumnType>
}

export type ColumnType =
    | 'text'
    | 'badge'
    | 'boolean'
    | 'date'
    | 'dateTime'
    | 'image'
    | 'icon'
    | 'computed'
    | 'relationship'
```

## 8. Componentes React principais

### 8.1 AppShell

```tsx
import { AppShell } from '@arqel-dev/ui'

export default function AdminLayout({ children }: { children: ReactNode }) {
    return (
        <AppShell
            sidebar={<Sidebar />}
            topbar={<Topbar />}
            footer={<Footer />}
        >
            {children}
        </AppShell>
    )
}
```

### 8.2 ResourceIndex

```tsx
import { ResourceIndex } from '@arqel-dev/ui'
import type { ResourceIndexProps } from '@arqel-dev/types'

export default function UsersIndex(props: ResourceIndexProps<User>) {
    return <ResourceIndex {...props} />
}
```

Declarative — todo o rendering é handled internamente pelo component a partir dos props Inertia. Customização via slots:

```tsx
<ResourceIndex 
    {...props}
    toolbar={<CustomToolbar />}
    emptyState={<CustomEmpty />}
    renderRow={(record, defaultRenderer) => (
        record.is_vip ? <VipRow record={record} /> : defaultRenderer(record)
    )}
/>
```

### 8.3 DataTable (lower-level)

```tsx
import { DataTable } from '@arqel-dev/ui'

<DataTable
    data={records.data}
    columns={columns}
    filters={filters}
    sort={sort}
    onSortChange={handleSort}
    onFilterChange={handleFilter}
    onSelectionChange={setSelectedIds}
    selectedIds={selectedIds}
    actions={actions.row}
    bulkActions={actions.bulk}
    searchable
    virtualScrolling={false}               // Fase 2
/>
```

### 8.4 FormRenderer

```tsx
import { FormRenderer, useArqelForm } from '@arqel-dev/ui'
import type { ResourceCreateProps } from '@arqel-dev/types'

export default function UsersCreate({ resource, fields, form, defaults }: ResourceCreateProps<User>) {
    const inertiaForm = useArqelForm(defaults, fields)

    return (
        <form onSubmit={(e) => {
            e.preventDefault()
            inertiaForm.post(resource.urls.index)
        }}>
            <FormRenderer 
                form={form}
                fields={fields}
                data={inertiaForm.data}
                errors={inertiaForm.errors}
                onChange={inertiaForm.setData}
                processing={inertiaForm.processing}
            />
            <FormActions 
                submitLabel="Create"
                onCancel={() => router.visit(resource.urls.index)}
                processing={inertiaForm.processing}
            />
        </form>
    )
}
```

### 8.5 FieldRenderer

Renderização polimórfica baseada em `field.type`:

```tsx
import { FieldRenderer } from '@arqel-dev/ui'

<FieldRenderer
    field={field}
    value={data[field.name]}
    onChange={(value) => setData(field.name, value)}
    error={errors[field.name]}
    record={record}                        // For context-aware fields
/>
```

Internamente resolve via FieldRegistry:

```typescript
import { getFieldComponent } from '@arqel-dev/fields'

const Component = getFieldComponent(field.component)  // 'EmailInput' → EmailInput
```

### 8.6 CanAccess

```tsx
import { CanAccess } from '@arqel-dev/ui'

<CanAccess ability="users.create">
    <Button onClick={handleCreate}>Create User</Button>
</CanAccess>

// Com fallback
<CanAccess ability="users.delete" record={user} fallback={<DisabledButton />}>
    <DeleteButton />
</CanAccess>
```

**Nota crítica:** `CanAccess` é UX-only. Server-side enforcement é sempre via Policies (ADR-017).

### 8.7 ActionButton + ActionMenu

```tsx
import { ActionButton, ActionMenu } from '@arqel-dev/ui'

// Single action
<ActionButton action={actionSchema} record={record} />

// Dropdown menu
<ActionMenu actions={actions.row} record={record} />
```

### 8.8 ConfirmDialog

```tsx
import { ConfirmDialog } from '@arqel-dev/ui'
import { useState } from 'react'

const [open, setOpen] = useState(false)

<ConfirmDialog
    open={open}
    onOpenChange={setOpen}
    heading="Delete user?"
    description="This action cannot be undone."
    variant="destructive"
    confirmLabel="Yes, delete"
    requiresText="DELETE"
    onConfirm={handleDelete}
/>
```

## 9. Hooks

### 9.1 useResource

Typed access aos props da página Resource.

```typescript
import { useResource } from '@arqel-dev/hooks'

function MyComponent() {
    const { resource, records, filters, actions } = useResource<User>()
    // ...
}
```

### 9.2 useArqelForm

Wrap do `useForm` Inertia com awareness de fields.

```typescript
import { useArqelForm } from '@arqel-dev/hooks'

const form = useArqelForm(defaults, fields)

form.data                                   // Record<string, unknown>
form.errors                                 // Record<string, string[]>
form.processing
form.setData(name, value)
form.post(url)
form.put(url)
form.delete(url)
form.submit(method, url, options)
form.reset()
form.clearErrors()

// Zod validation client-side (opt-in)
form.validate()                             // Returns true if valid
form.validateField('email')                 // Single field
```

### 9.3 useCanAccess

```typescript
import { useCanAccess } from '@arqel-dev/hooks'

const canDelete = useCanAccess('users.delete', user)
```

### 9.4 useFlash

```typescript
import { useFlash } from '@arqel-dev/hooks'

function Layout() {
    const { success, error, info, warning } = useFlash()
    
    useEffect(() => {
        if (success) toast.success(success)
        if (error) toast.error(error)
    }, [success, error])
}
```

### 9.5 useTable

Estado de tabela (sort, filter, selection) com URL sync.

```typescript
import { useTable } from '@arqel-dev/hooks'

const table = useTable({
    defaultSort: { column: 'created_at', direction: 'desc' },
    persistInUrl: true,
})

table.sort                                  // { column, direction }
table.setSort(column, direction)
table.filters                               // Record<string, unknown>
table.setFilter(name, value)
table.clearFilters()
table.selectedIds
table.toggleSelection(id)
table.selectAll()
table.clearSelection()
```

### 9.6 useAction

Executa Actions via Inertia.

```typescript
import { useAction } from '@arqel-dev/hooks'

const { invoke, processing, progress } = useAction(actionSchema)

await invoke(record, { additionalData })
```

### 9.7 useFieldDependencies

Handles `dependsOn` Field reactivity.

```typescript
import { useFieldDependencies } from '@arqel-dev/hooks'

useFieldDependencies(form, fields, {
    onDependencyChange: (fieldName, newOptions) => {
        // React to server-side refresh
    }
})
```

## 10. FieldRegistry (custom fields)

```typescript
// resources/js/app.tsx
import { createInertiaApp } from '@inertiajs/react'
import { createArqelApp } from '@arqel-dev/react'
import { registerField } from '@arqel-dev/fields'
import { MyCustomField } from './fields/MyCustomField'

registerField('MyCustomField', MyCustomField)

createArqelApp({
    setup: ({ el, App, props }) => createRoot(el).render(<App {...props} />)
})
```

### 10.1 Custom field component contract

```tsx
import type { FieldComponentProps } from '@arqel-dev/fields'

export function MyCustomField(props: FieldComponentProps<MyCustomFieldProps>) {
    const { field, value, onChange, error, disabled, readonly, record } = props
    
    return (
        <div>
            <label>{field.label}</label>
            <input
                value={value as string ?? ''}
                onChange={(e) => onChange(e.target.value)}
                disabled={disabled}
                readOnly={readonly}
            />
            {error && <span className="error">{error}</span>}
        </div>
    )
}

export interface MyCustomFieldProps {
    someExtraProp: string
}
```

## 11. Navigation

### 11.1 Sidebar auto-rendered

`<Sidebar />` reads from shared props `panel.navigation`:

```tsx
import { Sidebar } from '@arqel-dev/ui'

<Sidebar />                                 // Uses panel.navigation
```

### 11.2 Custom nav items

```tsx
import { Sidebar, NavGroup, NavItem } from '@arqel-dev/ui'

<Sidebar>
    <NavGroup label="Custom" icon="star">
        <NavItem href="/custom" icon="zap">Custom page</NavItem>
    </NavGroup>
    {/* Auto-rendered navigation still shows below */}
</Sidebar>
```

## 12. Theme & customization

### 12.1 CSS Variables (Tailwind v4)

```css
/* resources/css/app.css */
@import 'tailwindcss';
@import '@arqel-dev/ui/styles.css';

:root {
    --color-primary: oklch(63% 0.19 269);
    --color-secondary: oklch(96% 0.02 269);
    --color-destructive: oklch(62% 0.24 27);
    --radius: 0.5rem;
    --sidebar-width: 260px;
}

.dark {
    --color-primary: oklch(70% 0.19 269);
    /* ... */
}
```

### 12.2 ThemeProvider

```tsx
import { ThemeProvider } from '@arqel-dev/react'

<ThemeProvider defaultTheme="system" storageKey="arqel-theme">
    <App />
</ThemeProvider>
```

### 12.3 Layout variants

```tsx
<AppShell variant="sidebar-left">           // Default
<AppShell variant="sidebar-right">
<AppShell variant="topbar-only">
<AppShell variant="full-width">
```

## 13. Inertia integration patterns

### 13.1 Partial reloads

```tsx
import { router } from '@inertiajs/react'

// Reload only records (após delete)
router.reload({ only: ['records'] })

// Reload dependsOn field options
router.reload({ 
    only: [`fields.${fieldName}.options`],
    preserveScroll: true,
})
```

### 13.2 Optimistic updates (Inertia 3)

```tsx
import { useForm } from '@inertiajs/react'

const form = useForm({ /* ... */ })

form.post('/records', {
    optimistic: (data) => ({
        records: {
            ...existingRecords,
            data: [newRecord, ...existingRecords.data]
        }
    })
})
```

### 13.3 Deferred props (Fase 2)

Para widgets pesados:

```php
// PHP side
return Inertia::render('dashboard', [
    'stats' => Inertia::defer(fn () => $this->computeStats()),
]);
```

```tsx
// React side
import { Deferred } from '@inertiajs/react'

<Deferred data="stats" fallback={<StatsSkeleton />}>
    <StatsWidget data={stats} />
</Deferred>
```

### 13.4 Infinite scroll (Inertia 3)

```tsx
import { router } from '@inertiajs/react'

<button onClick={() => router.reload({
    only: ['records'],
    merge: ['records.data'],                // Append to existing
    data: { page: currentPage + 1 }
})}>
    Load more
</button>
```

## 14. Testing React components

### 14.1 Vitest + Testing Library

```tsx
import { render, screen } from '@testing-library/react'
import { describe, it, expect } from 'vitest'
import { FieldRenderer } from '@arqel-dev/ui'

describe('FieldRenderer', () => {
    it('renders text field with label', () => {
        const field = {
            type: 'text',
            name: 'email',
            label: 'Email',
            component: 'TextInput',
            // ...
        }
        
        render(
            <FieldRenderer 
                field={field}
                value="test@example.com"
                onChange={vi.fn()}
            />
        )
        
        expect(screen.getByLabelText('Email')).toHaveValue('test@example.com')
    })
})
```

### 14.2 Inertia mock provider

```tsx
import { InertiaTestProvider } from '@arqel-dev/testing/react'

render(
    <InertiaTestProvider sharedProps={{ auth: { user: mockUser } }}>
        <MyComponent />
    </InertiaTestProvider>
)
```

## 15. TypeScript type generation

PHP Resources → TypeScript types via `spatie/laravel-typescript-transformer` (opt-in).

```bash
php artisan typescript:transform
```

Gera:

```typescript
// resources/js/types/generated/records.ts (auto-generated)
export interface User {
    id: number
    name: string
    email: string
    role_id: number | null
    is_active: boolean
    created_at: string
    updated_at: string
    role?: Role                             // When loaded
}

export interface Role {
    id: number
    name: string
    permissions: string[]
}
```

Estes types são usados em Resource props:

```tsx
import type { User } from '@/types/generated/records'

export default function UsersIndex(props: ResourceIndexProps<User>) {
    // records.data is User[]
}
```

## 16. Bundle optimization

### 16.1 Code splitting por resource

```typescript
// app.tsx
createArqelApp({
    resolve: (name) => {
        const pages = import.meta.glob<any>('./pages/**/*.tsx')
        return pages[`./pages/${name}.tsx`]()
    }
})
```

Cada resource page é lazy-loaded.

### 16.2 Shared chunks

`vite.config.ts`:

```typescript
build: {
    rollupOptions: {
        output: {
            manualChunks: {
                'arqel-core': ['@arqel-dev/react', '@arqel-dev/hooks', '@inertiajs/react'],
                'arqel-ui': ['@arqel-dev/ui', '@arqel-dev/fields'],
                'react-vendor': ['react', 'react-dom'],
                'tanstack': ['@tanstack/react-table'],
            }
        }
    }
}
```

### 16.3 React 19.2 Compiler

`vite.config.ts`:

```typescript
import react from '@vitejs/plugin-react'

export default {
    plugins: [
        react({
            babel: {
                plugins: [['babel-plugin-react-compiler']]
            }
        })
    ]
}
```

## 17. A11y requirements (RNF-A-*)

Todos os componentes `@arqel-dev/ui`:

- Keyboard navigation completa (Tab, Shift+Tab, Arrow keys em menus/tabs)
- Focus visible (outline via CSS var `--ring`)
- ARIA attributes corretos (via Base UI primitives)
- `prefers-reduced-motion` respeitado em transitions
- Color contrast WCAG AA (4.5:1 text)
- Screen reader testing (NVDA, VoiceOver, JAWS) em Fase 2

## 18. Próximos documentos

- **`07-roadmap-fases.md`** — plano mestre das 4 fases (M1-M6+)
- **`08-fase-1-mvp.md`** — tickets detalhados Fase 1

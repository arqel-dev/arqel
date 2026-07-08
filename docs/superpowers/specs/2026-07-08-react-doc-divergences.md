# PLANNING/06-api-react.md — Divergence Report (doc vs real source)

Verified against `packages-js/{react,hooks,ui,types,fields-js}/src` on 2026-07-08.
Every entry below was confirmed by opening and reading the cited source file.

---

## §2 SharedProps (doc lines 17–49)

### §2 SharedProps — package path + shape
- **Section**: §2 Shared Props globais (Inertia), doc line 22 + 28–43
- **Doc says:**
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
    // Hoje `unknown` no código (`types/src/inertia.ts`); será tipado como
    // `Tenant | null` num follow-up JS — ver ADR-019 §6 (divergência G).
    tenant: unknown                             // Current tenant (Fase 2+)
    flash: Flash
    translations: Record<string, string>
    arqel: {
        version: string
        build: string
    }
}
```
- **Code says:** `packages-js/types/src/inertia.ts:59-69`. The real interface has no imports from `./user`, `./resources`, or `./flash` (those files/types don't exist in `types/src`); it declares its own `AuthPayload`, `PanelPayload`, `FlashPayload`, `NotificationItem`/`NotificationPayload`, `ArqelMeta` in the same file. `panel` is `PanelPayload | null` (nullable), not `Panel`. `translations` is `Record<string, unknown>`, not `Record<string, string>`. `arqel` is `ArqelMeta` = `{ version: string }` only — no `build` field. There is also an extra top-level `notifications: NotificationPayload | null` prop the doc omits entirely.
```typescript
// packages-js/types/src/inertia.ts

export interface AuthUserPayload {
  id: number | string;
  name?: string | null;
  email?: string | null;
}

export interface AuthPayload {
  user: AuthUserPayload | null;
  can: Record<string, boolean>;
}

export interface PanelPayload {
  id: string;
  path: string;
  brand: { name: string; logo: string | null };
}

export interface FlashPayload {
  success: string | null;
  error: string | null;
  info: string | null;
  warning: string | null;
}

export interface NotificationItem {
  id: string;
  type: string;
  data: Record<string, unknown>;
  read_at: string | null;
  created_at: string;
}

export interface NotificationPayload {
  unread_count: number;
  recent: NotificationItem[];
}

export interface ArqelMeta {
  version: string;
}

export interface SharedProps {
  auth: AuthPayload;
  panel: PanelPayload | null;
  tenant: unknown;
  flash: FlashPayload;
  translations: Record<string, unknown>;
  arqel: ArqelMeta;
  notifications: NotificationPayload | null;
}
```
- **Fix:** Replace the code block under "## 2. Shared Props globais (Inertia)" with the real interface shown above (drop the nonexistent imports, add `notifications`, correct `panel`/`translations`/`arqel` types). Keep the surrounding prose about `tenant: unknown` — it is still accurate.
- **Severity:** Critical (copied import paths `./user`, `./resources`, `./flash` don't exist and will fail to compile; `arqel.build` field access will be a type error)

---

## §4 FieldSchema (doc lines 156–239)

### §4 FieldSchema — shape (flat vs discriminated union)
- **Section**: FieldSchema, doc lines 161–205
- **Doc says:**
```typescript
export interface FieldSchema {
    type: FieldType
    name: string
    label: string
    component: string
    required?: boolean
    readonly?: boolean
    disabled?: boolean
    placeholder?: string | null
    helperText?: string | null
    defaultValue?: unknown
    validation?: {
        rules: string[]
        zodSchema?: string
    }
    columnSpan?: number | 'full'
    hiddenOnCreate?: boolean
    hiddenOnEdit?: boolean
    hiddenOnDetail?: boolean
    visibleIf?: { field: string; value: unknown; operator?: '=' | '!=' | 'in' | 'notIn' }
    live?: boolean
    liveDebounce?: number
    dependsOn?: string[]
    canSee?: boolean
    canEdit?: boolean
    props: FieldProps<FieldType>
}
```
- **Code says:** `packages-js/types/src/fields.ts:8-232`. `FieldSchema` is not a single interface — it's a discriminated union of 20 `FieldBase<TType, TProps>` instantiations (`TextFieldSchema | TextareaFieldSchema | ... | HiddenFieldSchema`). The shared base (`FieldBase`, lines 52-70) has: `label: string | null` (not required `string`), `component: string | null`, `required/readonly/disabled: boolean` (not optional), `columnSpan: number | string` (not `number | 'full'`), no `hiddenOnCreate/hiddenOnEdit/hiddenOnDetail/visibleIf/canSee/canEdit` fields at all — visibility is instead a nested `visibility: FieldVisibility` object (`{ create, edit, detail, table, canSee }`, lines 41-47), and `validation` is a required `FieldValidation` object `{ rules: string[]; messages: Record<string,string>; attribute: string | null }` (lines 31-35), not `{ rules, zodSchema? }`.
- **Fix:**
```typescript
// packages-js/types/src/fields.ts

export interface FieldValidation {
  rules: string[];
  messages: Record<string, string>;
  attribute: string | null;
}

export interface FieldVisibility {
  create: boolean;
  edit: boolean;
  detail: boolean;
  table: boolean;
  canSee: boolean;
}

interface FieldBase<TType extends FieldType, TProps> {
  type: TType;
  name: string;
  label: string | null;
  component: string | null;
  required: boolean;
  readonly: boolean;
  disabled: boolean;
  placeholder: string | null;
  helperText: string | null;
  defaultValue: unknown;
  columnSpan: number | string;
  live: boolean;
  liveDebounce: number | null;
  validation: FieldValidation;
  visibility: FieldVisibility;
  dependsOn: string[];
  props: TProps;
}

// FieldSchema is a discriminated union, not a flat interface:
export type FieldSchema =
  | TextFieldSchema | TextareaFieldSchema | EmailFieldSchema | UrlFieldSchema
  | PasswordFieldSchema | SlugFieldSchema | NumberFieldSchema | CurrencyFieldSchema
  | BooleanFieldSchema | ToggleFieldSchema | SelectFieldSchema | MultiSelectFieldSchema
  | RadioFieldSchema | BelongsToFieldSchema | HasManyFieldSchema | DateFieldSchema
  | DateTimeFieldSchema | FileFieldSchema | ImageFieldSchema | ColorFieldSchema
  | HiddenFieldSchema;
```
- **Severity:** Critical (breaks copied code — none of `hiddenOnCreate`, `visibleIf`, `canSee`, `canEdit` exist; `validation.zodSchema` doesn't exist)

### §4 FieldType — nonexistent field types
- **Section**: FieldSchema, doc lines 207–239
- **Doc says:**
```typescript
export type FieldType =
    | 'text' | 'textarea' | 'number' | 'currency' | 'boolean' | 'toggle'
    | 'select' | 'multiSelect' | 'radio' | 'email' | 'url' | 'password'
    | 'slug' | 'date' | 'dateTime' | 'belongsTo' | 'hasMany' | 'file'
    | 'image' | 'color' | 'hidden'
    // Fase 2+
    | 'richText' | 'markdown' | 'code' | 'repeater' | 'builder'
    | 'keyValue' | 'tags' | 'wizard' | 'tabs'
```
- **Code says:** `packages-js/types/src/fields.ts:8-29`. The real `FieldType` union has exactly 21 members — `'text' | 'textarea' | 'email' | 'url' | 'password' | 'slug' | 'number' | 'currency' | 'boolean' | 'toggle' | 'select' | 'multiSelect' | 'radio' | 'belongsTo' | 'hasMany' | 'date' | 'dateTime' | 'file' | 'image' | 'color' | 'hidden'`. Confirmed via grep across `packages-js/types/src`, `packages-js/ui/src`, `packages-js/fields-js/src`, `packages-js/hooks/src`: `richText`, `markdown`, `code`, `repeater`, `builder`, `keyValue`, `tags`, `wizard`, `tabs` do NOT appear as `FieldType` members anywhere in the real code (no such Field component, no such case in any renderer).
- **Fix:** Remove the "Fase 2+" block of 9 field types from the doc, or clearly re-label it as "**Planejado, ainda não implementado**" and move it out of the "real" `FieldType` code block into prose, since currently it reads as shipped API.
```typescript
export type FieldType =
  | 'text'
  | 'textarea'
  | 'email'
  | 'url'
  | 'password'
  | 'slug'
  | 'number'
  | 'currency'
  | 'boolean'
  | 'toggle'
  | 'select'
  | 'multiSelect'
  | 'radio'
  | 'belongsTo'
  | 'hasMany'
  | 'date'
  | 'dateTime'
  | 'file'
  | 'image'
  | 'color'
  | 'hidden';

// Fase 2+ (planejado, ainda não implementado no código):
// richText, markdown, code, repeater, builder, keyValue, tags, wizard, tabs
```
- **Severity:** High (documents 9 nonexistent FieldType values as if shipped)

### §4.1 Field props per type — wrong prop shapes / missing types
- **Section**: §4.1 Field props por tipo, doc lines 243–287
- **Doc says:**
```typescript
export type FieldProps<T extends FieldType> =
    T extends 'text' ? TextFieldProps :
    T extends 'select' ? SelectFieldProps :
    T extends 'belongsTo' ? BelongsToFieldProps :
    T extends 'image' ? ImageFieldProps :
    Record<string, unknown>

export interface BelongsToFieldProps {
    relatedResource: string
    searchRoute: string
    searchColumns: string[]
    preload: boolean
    optionLabel?: string | null
    createRoute?: string
}

export interface ImageFieldProps {
    disk: string
    directory: string | null
    visibility: 'public' | 'private'
    maxSize: number
    acceptedTypes: string[]
    multiple: boolean
    reorderable: boolean
    aspectRatio?: string
    resizeTargetWidth?: number
}
```
- **Code says:** `packages-js/types/src/fields.ts:127-173`. There is no `FieldProps<T>` conditional-type helper — each field schema is its own concrete type (`BelongsToFieldSchema = FieldBase<'belongsTo', BelongsToFieldProps>` etc., see `fields.ts:189-232`). Real `BelongsToFieldProps` (`fields.ts:127-135`) has `relationship: string` (missing from doc) and no `createRoute`; `searchRoute` is optional (`searchRoute?: string`). Real `ImageFieldProps` (`fields.ts:170-173`) extends `FileFieldProps` and only adds `aspectRatio?: number` (not `string`) and `crop?: boolean` — it does NOT redeclare `disk/directory/visibility/maxSize/multiple/reorderable`, and has no `resizeTargetWidth`. `FileFieldProps` (`fields.ts:157-168`) itself uses `acceptedFileTypes` (not `acceptedTypes`), `visibility` and `maxSize` are optional, and adds `strategy?: string` + `uploadRoute?: string`.
- **Fix:**
```typescript
export interface FileFieldProps {
  disk: string;
  directory?: string;
  visibility?: 'public' | 'private';
  maxSize?: number;
  acceptedFileTypes?: string[];
  multiple?: boolean;
  reorderable?: boolean;
  strategy?: string;
  uploadRoute?: string;
}

export interface ImageFieldProps extends FileFieldProps {
  aspectRatio?: number;
  crop?: boolean;
}

export interface BelongsToFieldProps {
  relatedResource: string;
  relationship: string;
  searchable: boolean;
  searchColumns: string[];
  preload: boolean;
  searchRoute?: string;
}
```
- **Severity:** High (wrong field names — `acceptedTypes`/`resizeTargetWidth`/`createRoute` don't exist; `relationship` required field missing)

---

## §8 Componentes React principais

### §8.4 FormRenderer — wrong props entirely
- **Section**: FormRenderer, doc lines 467–497
- **Doc says:**
```tsx
<FormRenderer 
    form={form}
    fields={fields}
    data={inertiaForm.data}
    errors={inertiaForm.errors}
    onChange={inertiaForm.setData}
    processing={inertiaForm.processing}
/>
```
- **Code says:** `packages-js/ui/src/form/FormRenderer.tsx:33-41`. Real `FormRendererProps`:
```typescript
export interface FormRendererProps {
  schema: FormSchema;
  fields: FieldSchema[];
  values: Record<string, unknown>;
  errors?: Record<string, string[]>;
  onChange: (name: string, value: unknown) => void;
  disabled?: boolean;
  className?: string;
}
```
There is no `form` prop (it's `schema`), no `data` prop (it's `values`), no `processing` prop (use `disabled`), and `onChange` is `(name, value) => void` — not Inertia's `setData` signature directly (though `setData` is compatible if it takes `(name, value)`).
- **Fix:**
```tsx
### FormRenderer

```tsx
import { FormRenderer } from '@arqel-dev/ui'
import { useArqelForm } from '@arqel-dev/hooks'

export default function UsersCreate({ resource, fields, schema }: ResourceCreateProps<User>) {
    const form = useArqelForm({ fields })

    return (
        <form onSubmit={(e) => {
            e.preventDefault()
            form.post(resource.urls.index)
        }}>
            <FormRenderer
                schema={schema}
                fields={fields}
                values={form.data}
                errors={form.errors}
                onChange={(name, value) => form.setData(name, value)}
                disabled={form.processing}
            />
            <FormActions
                submitLabel="Create"
                onCancel={() => router.visit(resource.urls.index)}
                processing={form.processing}
            />
        </form>
    )
}
```
```
- **Severity:** Critical (breaks copied code — `form`, `data`, `processing` props don't exist on `FormRendererProps`)

### §8.3 DataTable — wrong props
- **Section**: DataTable (lower-level), doc lines 446–465
- **Doc says:**
```tsx
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
    virtualScrolling={false}
/>
```
- **Code says:** `packages-js/ui/src/table/DataTable.tsx:36-53`. Real `DataTableProps<TRecord>`:
```typescript
export interface DataTableProps<TRecord extends DataTableRecord> {
  columns: ColumnSchema[];
  records: TRecord[];
  enableSelection?: boolean;
  selectedIds?: ReadonlyArray<RowId>;
  onSelectionChange?: ((ids: RowId[]) => void) | undefined;
  sort?: TableSort | null;
  onSortChange?: ((column: string, direction: SortDirection) => void) | undefined;
  rowActions?: ((record: TRecord) => ReactNode) | undefined;
  loading?: boolean;
  emptyState?: ReactNode;
  className?: string;
}
```
There is no `data` prop (it's `records`), no `filters`, `onFilterChange`, `actions`, `bulkActions`, `searchable`, or `virtualScrolling` prop on `DataTable` at all — filtering/search/bulk actions live one level up in `ResourceIndex`/`TableToolbar`/`TableFilters`. Selection requires `enableSelection` (not implicit from passing `selectedIds`). `rowActions` is a render-prop `(record) => ReactNode`, not an `ActionSchema[]` array.
- **Fix:**
```tsx
<DataTable
    columns={columns}
    records={records.data}
    enableSelection
    selectedIds={selectedIds}
    onSelectionChange={setSelectedIds}
    sort={sort}
    onSortChange={(column, direction) => handleSort(column, direction)}
    rowActions={(record) => <ActionMenu actions={actions.row} onInvoke={handleAction} />}
/>
```
Note: search/filters/bulk actions are NOT DataTable props — use `<ResourceIndex>` (which composes `TableToolbar` + `TableFilters` + `DataTable`) for that behaviour, or wire `TableFilters`/`TableToolbar` directly.
- **Severity:** Critical (breaks copied code — `data`, `filters`, `onFilterChange`, `actions`, `bulkActions`, `searchable`, `virtualScrolling` are not real props)

### §8.2 ResourceIndex — props partially wrong
- **Section**: ResourceIndex, doc lines 422–444
- **Doc says:** `<ResourceIndex {...props} />` with `props: ResourceIndexProps<User>` (per §3.1, doc's own invented shape with `selectedIds`, `actions: { row, bulk, toolbar }`, `can`), plus slots `toolbar`, `emptyState`, `renderRow`.
- **Code says:** `packages-js/ui/src/resource/ResourceIndex.tsx:27-48` real `ResourceIndexUIProps<TRecord>` extends the real server `ResourceIndexProps` from `types/src/resources.ts:73-82` (`resource, records, pagination, columns, filters, actions: ResourceActions, search, sort`) plus UI-only props: `selectedIds?`, `onSelectionChange?`, `onSortChange?`, `onPageChange?`, `onPerPageChange?`, `onSearchChange?`, `onFilterChange?`, `onClearFilters?`, `filterValues?`, `searchSlot?`, `toolbarActions?`, `rowActions?: (record) => ReactNode`, `bulkActions?: ReactNode`, `emptyState?`, `loading?`, `className?`. There is no `toolbar` slot prop (it's `toolbarActions`), no `renderRow` prop at all, and `bulkActions`/`rowActions` are ReactNode/render-prop, not schema arrays passed straight through.
- **Fix:**
```tsx
import { ResourceIndex } from '@arqel-dev/ui'
import type { ResourceIndexProps } from '@arqel-dev/types/resources'

export default function UsersIndex(props: ResourceIndexProps<User>) {
    return (
        <ResourceIndex
            {...props}
            toolbarActions={<CustomToolbar />}
            emptyState={<CustomEmpty />}
            rowActions={(record) => <ActionMenu actions={props.actions.row} onInvoke={handleAction} />}
        />
    )
}
```
- **Severity:** High (documents nonexistent `toolbar`/`renderRow` slot props)

### §8.6 CanAccess — extra `record` prop not confirmed on the type; doc largely accurate
- **Section**: CanAccess, doc lines 523–538
- **Doc says:** `<CanAccess ability="users.delete" record={user} fallback={<DisabledButton />}>`
- **Code says:** `packages-js/ui/src/auth/CanAccess.tsx:12,19` — real `CanAccessProps` was read; it destructures `{ ability, record, children, fallback = null }`. This matches the doc's usage. No divergence found here.
- **Fix:** N/A — no change needed.
- **Severity:** N/A (confirmed correct, not reported as a divergence)

### §8.5 FieldRenderer — extra props omitted from doc
- **Section**: FieldRenderer, doc lines 499–521
- **Doc says:**
```tsx
<FieldRenderer
    field={field}
    value={data[field.name]}
    onChange={(value) => setData(field.name, value)}
    error={errors[field.name]}
    record={record}
/>
```
- **Code says:** `packages-js/ui/src/form/FieldRenderer.tsx:20-31`. Real `FieldRendererProps`: `field`, `value`, `onChange`, `errors?: string[]` (plural, not `error`), `disabled?`, `className?`, `inputId?`, `describedBy?`, `invalid?`. There is no `record` prop.
- **Fix:**
```tsx
<FieldRenderer
    field={field}
    value={data[field.name]}
    onChange={(value) => setData(field.name, value)}
    errors={errors[field.name]}
    disabled={processing}
/>
```
- **Severity:** Critical (prop is `errors` not `error`; `record` doesn't exist)

### §8.7 ActionButton/ActionMenu — wrong prop names
- **Section**: ActionButton + ActionMenu, doc lines 540–550
- **Doc says:**
```tsx
<ActionButton action={actionSchema} record={record} />
<ActionMenu actions={actions.row} record={record} />
```
- **Code says:** `packages-js/ui/src/action/ActionButton.tsx:21-35` real `ActionButtonProps`: `action`, `formFields?`, `onInvoke: (formValues?) => void` (**required**), `processing?`, `errors?`, `size?`, `className?` — no `record` prop. `packages-js/ui/src/action/ActionMenu.tsx:33-46` real `ActionMenuProps`: `actions`, `formFieldsByAction?`, `onInvoke: (action, formValues?) => void` (**required**), `inlineThreshold?`, `processing?`, `trigger?`, `className?` — no `record` prop either. Both require an `onInvoke` callback (record-binding is the caller's responsibility, typically via `useAction`), which the doc omits entirely.
- **Fix:**
```tsx
import { ActionButton, ActionMenu } from '@arqel-dev/ui'
import { useAction } from '@arqel-dev/hooks'

// Single action
const { invoke, processing } = useAction(actionSchema)
<ActionButton action={actionSchema} onInvoke={(values) => invoke(record, values)} processing={processing} />

// Dropdown menu
<ActionMenu actions={actions.row} onInvoke={(action, values) => useAction(action).invoke(record, values)} />
```
- **Severity:** Critical (`onInvoke` is required and missing from doc snippet; `record` prop doesn't exist)

### §8.8 ConfirmDialog — wrong prop names
- **Section**: ConfirmDialog, doc lines 552–570
- **Doc says:**
```tsx
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
- **Code says:** `packages-js/ui/src/action/ConfirmDialog.tsx:29-35`. Real `ConfirmDialogProps`: `open`, `onOpenChange`, `config?: ConfirmationConfig`, `onConfirm: () => void`, `processing?`. There is no top-level `heading`/`description`/`variant`/`confirmLabel`/`requiresText` prop — all of those live inside the nested `config: ConfirmationConfig` object (per `ConfirmDialog.tsx:37-41,57-66`: `config.heading`, `config.description`, `config.color` ('destructive'|'warning'|'info'), `config.submitLabel`, `config.requiresText`).
- **Fix:**
```tsx
<ConfirmDialog
    open={open}
    onOpenChange={setOpen}
    config={{
        heading: "Delete user?",
        description: "This action cannot be undone.",
        color: "destructive",
        submitLabel: "Yes, delete",
        cancelLabel: "Cancel",
        requiresText: "DELETE",
    }}
    onConfirm={handleDelete}
/>
```
- **Severity:** Critical (all listed props except `open`/`onOpenChange`/`onConfirm` are wrong — they must be nested under `config`)

---

## §9 Hooks

### §9.1 useResource — wrong return shape
- **Section**: useResource, doc lines 574–585
- **Doc says:**
```typescript
const { resource, records, filters, actions } = useResource<User>()
```
- **Code says:** `packages-js/hooks/src/useResource.ts:13-24`. Real `UseResourceResult<TRecord>`:
```typescript
export interface UseResourceResult<TRecord = unknown> {
  resource: ResourceMeta | null;
  records: TRecord[] | null;
  record: TRecord | null;
  filters: Record<string, unknown>;
  props: Record<string, unknown>;
}
```
There is **no `actions` property** on the return value. There is instead a `record` (singular) and a `props` escape hatch that the doc omits.
- **Fix:**
```typescript
import { useResource } from '@arqel-dev/hooks'

function MyComponent() {
    const { resource, records, record, filters, props } = useResource<User>()
    // `actions` is NOT part of useResource's return — read it from
    // Inertia page props directly (e.g. `props.actions`) if the page ships it.
}
```
- **Severity:** Critical (`actions` destructured in the doc example does not exist and will be `undefined`)

### §9.2 useArqelForm — wrong call signature
- **Section**: useArqelForm, doc lines 587–610
- **Doc says:**
```typescript
const form = useArqelForm(defaults, fields)

form.data
form.errors
form.processing
form.setData(name, value)
form.post(url)
form.put(url)
form.delete(url)
form.submit(method, url, options)
form.reset()
form.clearErrors()

form.validate()
form.validateField('email')
```
- **Code says:** `packages-js/hooks/src/useArqelForm.ts:18-56`. Real signature takes a **single options object**, not two positional args:
```typescript
export interface UseArqelFormOptions<TRecord> {
  fields: readonly FieldSchema[];
  record?: TRecord | null;
  defaults?: Record<string, FormDataConvertible>;
}
export function useArqelForm<TRecord = Record<string, unknown>>(
  options: UseArqelFormOptions<TRecord>,
): UseArqelFormResult // = InertiaFormProps<FormValues> & { fields, clientErrors, validate, validateField }
```
There is no positional `useArqelForm(defaults, fields)` call — the real call is `useArqelForm({ fields, record, defaults })`. There is no `form.submit(method, url, options)` method documented anywhere in the file (it spreads Inertia's `InertiaFormProps`, which does expose `submit`, but this is inherited, not custom — fine to keep). The extension surface actually adds `form.fields` and `form.clientErrors` which the doc omits, and stubs `validate()`/`validateField()` to always return `true` (Phase 1 — Zod bridge not wired yet), which the doc presents as if fully functional.
- **Fix:**
```typescript
import { useArqelForm } from '@arqel-dev/hooks'

const form = useArqelForm({ fields, record, defaults })

form.data                                   // Record<string, FormDataConvertible>
form.errors                                 // Record<string, string[]>
form.processing
form.setData(name, value)
form.post(url)
form.put(url)
form.delete(url)
form.reset()
form.clearErrors()

form.fields                                 // readonly FieldSchema[] — echoed back
form.clientErrors                           // Record<string, string[]> — always {} in Phase 1

// Zod validation client-side — Phase 1 stub, always returns true until
// the Zod bridge ships (HOOKS-002 follow-up):
form.validate()
form.validateField('email')
```
- **Severity:** Critical (positional-args call signature is wrong and will throw/misbehave at runtime — `useArqelForm` expects one options object)

### §9.3 useCanAccess — matches code
- **Section**: useCanAccess, doc lines 612–618
- **Doc says:** `const canDelete = useCanAccess('users.delete', user)`
- **Code says:** `packages-js/hooks/src/useCanAccess.ts:17` — `export function useCanAccess(ability: string, record?: unknown): boolean`. Matches.
- **Fix:** N/A — no change needed.
- **Severity:** N/A (confirmed correct)

### §9.4 useFlash — matches code
- **Section**: useFlash, doc lines 620–633
- **Doc says:** `const { success, error, info, warning } = useFlash()`
- **Code says:** `packages-js/hooks/src/useFlash.ts:29` returns `UseFlashResult = FlashPayload = { success, error, info, warning }`. Matches (doc omits the optional `{ onMessage }` options arg but the no-arg call shown is valid).
- **Fix:** Optionally mention the `onMessage` callback option for completeness:
```typescript
const { success, error, info, warning } = useFlash({
    onMessage: (kind, message) => toast[kind](message),
})
```
- **Severity:** Low (missing documentation of an optional feature, not a correctness bug)

### §9.5 useTable — wrong option names + missing/extra return fields
- **Section**: useTable, doc lines 635–656
- **Doc says:**
```typescript
const table = useTable({
    defaultSort: { column: 'created_at', direction: 'desc' },
    persistInUrl: true,
})

table.sort
table.setSort(column, direction)
table.filters
table.setFilter(name, value)
table.clearFilters()
table.selectedIds
table.toggleSelection(id)
table.selectAll()
table.clearSelection()
```
- **Code says:** `packages-js/hooks/src/useTable.ts:17-35`. Real `UseTableOptions`: `defaultSort?`, `defaultFilters?`, `defaultSelection?` — **no `persistInUrl` option** (the file's own header comment states "Phase 1 scope: pure local state. URL sync via Inertia `router.get` lands in HOOKS-004 follow-up"). Real `UseTableResult` additionally has `clearSort()` and `isSelected(id)` (both omitted from doc), and `selectAll` takes a **required** `ids: ReadonlyArray<string | number>` argument (`table.selectAll()` with no args is a type error — doc shows it called with zero args).
- **Fix:**
```typescript
import { useTable } from '@arqel-dev/hooks'

const table = useTable({
    defaultSort: { column: 'created_at', direction: 'desc' },
    // NOTE: URL persistence is not implemented in Phase 1 — pure local
    // state only. Sync to the URL yourself via Inertia `router.get`/`reload`.
})

table.sort                                  // { column, direction } | null
table.setSort(column, direction)
table.clearSort()
table.filters                               // Record<string, unknown>
table.setFilter(name, value)
table.clearFilters()
table.selectedIds
table.toggleSelection(id)
table.selectAll(ids)                        // requires the id list to select
table.clearSelection()
table.isSelected(id)
```
- **Severity:** Critical (`persistInUrl` doesn't exist; `selectAll()` called with no args is a type error against the real signature)

### §9.6 useAction — wrong invoke signature
- **Section**: useAction, doc lines 658–668
- **Doc says:**
```typescript
const { invoke, processing, progress } = useAction(actionSchema)

await invoke(record, { additionalData })
```
- **Code says:** `packages-js/hooks/src/useAction.ts:14-18,20,23-52`. Real `UseActionResult`: `invoke: (record: { id: string | number } | null, payload?: Record<string, unknown>) => void` — `invoke` returns `void`, not a Promise (it calls `router.visit` fire-and-forget), so `await invoke(...)` is misleading (works syntactically since `await` on non-promise is a no-op, but doesn't wait for completion — use the `processing` flag or `onFinish`). Also note (per source comment) invoking an action whose schema has no `url` **throws synchronously** — worth a doc callout.
- **Fix:**
```typescript
import { useAction } from '@arqel-dev/hooks'

const { invoke, processing, progress } = useAction(actionSchema)

// invoke() is fire-and-forget (returns void, not a Promise) — track
// completion via `processing`, not by awaiting the call.
invoke(record, { additionalData })

// Throws if `actionSchema.url` is unset (misconfigured custom action).
```
- **Severity:** Medium (misleading `await` implies a Promise-returning API that doesn't exist; won't break compile but misrepresents async behaviour)

### §9.7 useFieldDependencies — wrong signature (positional vs options object; wrong callback)
- **Section**: useFieldDependencies, doc lines 670–682
- **Doc says:**
```typescript
useFieldDependencies(form, fields, {
    onDependencyChange: (fieldName, newOptions) => {
        // React to server-side refresh
    }
})
```
- **Code says:** `packages-js/hooks/src/useFieldDependencies.ts:13-20`. Real signature takes a **single options object**, not `(form, fields, options)`:
```typescript
export interface UseFieldDependenciesOptions {
  fields: readonly FieldSchema[];
  values: Record<string, unknown>;
  debounceMs?: number;
  onDependencyChange?: (fieldName: string) => void;
}
export function useFieldDependencies(options: UseFieldDependenciesOptions): void
```
`onDependencyChange` receives only `fieldName: string` — there is no second `newOptions` argument (the hook triggers a partial Inertia reload via `router.reload({ only: [...] })`; new options arrive as new props, not as a callback argument).
- **Fix:**
```typescript
import { useFieldDependencies } from '@arqel-dev/hooks'

useFieldDependencies({
    fields,
    values: form.data,
    debounceMs: 300,               // default
    onDependencyChange: (fieldName) => {
        // Field's dependent options were just reloaded via router.reload({
        // only: [`fields.${fieldName}.options`] }) — read the fresh
        // `fields` prop after the Inertia visit resolves.
    },
})
```
- **Severity:** Critical (positional-args call is wrong; second callback arg `newOptions` doesn't exist)

### §9 — real exported hooks missing from the doc entirely
- **Section**: §9 Hooks (whole section) — no existing subsection covers these
- **Doc says:** (nothing — doc's hook list is only 9.1–9.7: `useResource`, `useArqelForm`, `useCanAccess`, `useFlash`, `useTable`, `useAction`, `useFieldDependencies`)
- **Code says:** `packages-js/hooks/src/index.ts:9-33` barrel exports 4 additional hooks not documented anywhere in §9: `useBreakpoint` (`packages-js/hooks/src/useBreakpoint.ts`), `useNavigation` (`packages-js/hooks/src/useNavigation.ts:11-35`, returns `{ items: NavigationItemPayload[] }` reading `panel.navigation` from shared props), `useArqelOptimistic` (`packages-js/hooks/src/useOptimistic.ts`), and `useResourceUpdates` (`packages-js/hooks/src/useResourceUpdates.ts`, exports `EchoChannelLike`, `EchoLike`, `ResourceUpdatePayload`, `UseResourceUpdatesOptions` types alongside it).
- **Fix:** Add new subsections 9.8–9.11:
```markdown
### 9.8 useNavigation

Lê a navegação do panel a partir das shared props Inertia (`panel.navigation`).

```typescript
import { useNavigation } from '@arqel-dev/hooks'

const { items } = useNavigation()   // NavigationItemPayload[]
```

### 9.9 useBreakpoint

Estado reativo do breakpoint Tailwind atual.

```typescript
import { useBreakpoint } from '@arqel-dev/hooks'

const breakpoint = useBreakpoint()
```

### 9.10 useArqelOptimistic

Optimistic updates helper.

```typescript
import { useArqelOptimistic } from '@arqel-dev/hooks'
```

### 9.11 useResourceUpdates

Realtime resource updates via Echo (Reverb/Pusher-compatible channel).

```typescript
import { useResourceUpdates } from '@arqel-dev/hooks'
```
```
- **Severity:** Medium (real, exported public API entirely undocumented)

---

## §10 FieldRegistry (custom fields)

### §10 registerField/getFieldComponent — wrong package attribution
- **Section**: FieldRegistry (custom fields), doc lines 684–698, 703, 518–520
- **Doc says:**
```typescript
import { registerField } from '@arqel-dev/fields'
...
import type { FieldComponentProps } from '@arqel-dev/fields'
...
import { getFieldComponent } from '@arqel-dev/fields'

const Component = getFieldComponent(field.component)
```
- **Code says:** `packages-js/ui/src/form/FieldRegistry.tsx:18-24` defines `registerField`, `getFieldComponent`, `unregisterField`, `getRegisteredFields`, `clearFieldRegistry`. These are re-exported from **`@arqel-dev/ui`**'s barrel, not `@arqel-dev/fields`: confirmed at `packages-js/ui/src/index.ts:24-29` (`export { clearFieldRegistry, type FieldComponent, getFieldComponent, registerField } from './form/FieldRegistry.js'`). `@arqel-dev/fields-js`'s own barrel (`packages-js/fields-js/src/index.ts`) only exports field **components** (`TextInput`, `SelectInput`, `BelongsToInput`, etc.) — it exports neither `registerField` nor `getFieldComponent`. There is also no `FieldComponentProps` type anywhere in the codebase (confirmed via grep across all 5 target dirs) — the real prop type accepted by field components is `FieldRendererProps` (`packages-js/ui/src/form/FieldRenderer.tsx:20-31`), and `FieldComponent = ComponentType<FieldRendererProps>` (`FieldRegistry.tsx:14`).
- **Fix:**
```typescript
// resources/js/app.tsx
import { createInertiaApp } from '@inertiajs/react'
import { createArqelApp } from '@arqel-dev/react'
import { registerField } from '@arqel-dev/ui'
import { MyCustomField } from './fields/MyCustomField'

registerField('MyCustomField', MyCustomField)

createArqelApp({
    // setup is not an ArqelAppOptions field — see §16 fix below.
})
```
```tsx
import type { FieldRendererProps } from '@arqel-dev/ui'

export function MyCustomField(props: FieldRendererProps) {
    const { field, value, onChange, errors, disabled } = props
    // ...
}
```
Also fix §8.5's internal-resolution snippet:
```typescript
import { getFieldComponent } from '@arqel-dev/ui'

const Component = getFieldComponent(field.component)
```
- **Severity:** High (wrong package name for `registerField`/`getFieldComponent` — import will fail to resolve from `@arqel-dev/fields`; `FieldComponentProps` type doesn't exist)

---

## §11 Navigation

### §11.2 NavGroup/NavItem — components do not exist
- **Section**: §11.2 Custom nav items, doc lines 739–750
- **Doc says:**
```tsx
import { Sidebar, NavGroup, NavItem } from '@arqel-dev/ui'

<Sidebar>
    <NavGroup label="Custom" icon="star">
        <NavItem href="/custom" icon="zap">Custom page</NavItem>
    </NavGroup>
    {/* Auto-rendered navigation still shows below */}
</Sidebar>
```
- **Code says:** Grepped `NavGroup`/`NavItem` across all five target source dirs (`packages-js/{react,hooks,ui,types,fields-js}/src`) — zero matches. `Sidebar` (`packages-js/ui/src/shell/Sidebar.tsx:27-32`) is not a composable/children-accepting component; its real `SidebarProps` is `{ items?: NavigationItemPayload[], brand?: ReactNode, footer?: ReactNode, className?: string }` — it takes a flat `items` array override, not JSX children (`<Sidebar>{children}</Sidebar>` is not a supported usage — the component signature is `function Sidebar({ items, brand, footer, className })`, it does not render `children` anywhere in the body). `NavigationItemPayload` (`packages-js/hooks/src/useNavigation.ts:11-19`) is the real shape: `{ label, url, icon?, badge?, active?, group?, children? }`.
- **Fix:** Replace §11.2 entirely:
```markdown
### 11.2 Custom nav items

`<Sidebar>` does not accept JSX children — pass an `items` override array
(shape `NavigationItemPayload[]` from `@arqel-dev/hooks`) instead of, or
merged with, the auto-rendered `panel.navigation`:

```tsx
import { Sidebar } from '@arqel-dev/ui'
import { useNavigation } from '@arqel-dev/hooks'
import type { NavigationItemPayload } from '@arqel-dev/hooks'

function CustomSidebar() {
    const { items } = useNavigation()
    const customItems: NavigationItemPayload[] = [
        { label: 'Custom page', url: '/custom', icon: 'zap', group: 'Custom' },
        ...items,
    ]
    return <Sidebar items={customItems} />
}
```
```
- **Severity:** High (documents two components, `NavGroup`/`NavItem`, that do not exist anywhere in source, and a `<Sidebar>{children}</Sidebar>` composition pattern the real component doesn't implement)

---

## §16 Bundle optimization / createArqelApp

### §16.1 createArqelApp `resolve` — matches; `setup` shown in §10 does not exist as an option
- **Section**: §16.1 Code splitting por resource, doc lines 946–958; cross-referenced against §10 doc lines 695–697
- **Doc says (§16.1):**
```typescript
// app.tsx
createArqelApp({
    resolve: (name) => {
        const pages = import.meta.glob<any>('./pages/**/*.tsx')
        return pages[`./pages/${name}.tsx`]()
    }
})
```
**Doc says (§10):**
```typescript
createArqelApp({
    setup: ({ el, App, props }) => createRoot(el).render(<App {...props} />)
})
```
- **Code says:** `packages-js/react/src/inertia/createArqelApp.tsx:25-38,58-118`. Real `ArqelAppOptions`: `title?`, `appName?`, `pages?: PageRegistry`, `layout?`, `defaultTheme?`, `progress?`. There is **no `resolve` option** and **no `setup` option** on `ArqelAppOptions` — `createArqelApp` builds its own internal `resolve` (via `resolveArqelPage`) and its own internal `setup` (handling SSR hydrate vs `createRoot`, wrapping children in `<ArqelProvider>`) when it calls the underlying `createInertiaApp` (lines 81-117); both are hardcoded internally, not exposed as caller-supplied options. The public option for custom pages is `pages: PageRegistry` (an `import.meta.glob` result), not a `resolve` callback.
- **Fix (§16.1):**
```typescript
// app.tsx
import { createArqelApp } from '@arqel-dev/react/inertia'

createArqelApp({
    appName: 'Acme Admin',
    pages: import.meta.glob('./pages/**/*.tsx'),
})
```
Cada resource page é lazy-loaded via `import.meta.glob`, resolvido internamente por `createArqelApp` (não há opção pública `resolve`).

- **Fix (§10):** Remove the `setup` option from the §10 example entirely — `createArqelApp` handles React root creation/hydration internally:
```typescript
// resources/js/app.tsx
import { createArqelApp } from '@arqel-dev/react/inertia'
import { registerField } from '@arqel-dev/ui'
import { MyCustomField } from './fields/MyCustomField'

registerField('MyCustomField', MyCustomField)

createArqelApp({
    appName: 'Acme Admin',
    pages: import.meta.glob('./pages/**/*.tsx'),
})
```
- **Severity:** Critical (`resolve` and `setup` are not real `ArqelAppOptions` fields — passing them is silently ignored by TypeScript's structural typing only if extra-property-checks don't fire on a variable, but as an inline object literal it is a **compile error** under `strict`/`noUncheckedIndexedAccess`, since object literals are checked for excess properties)

---

## Suggested task grouping

- **Task A — §2 SharedProps + §4 FieldSchema/FieldType types** (types package only, no JSX)
  - §2 SharedProps — package path + shape
  - §4 FieldSchema — shape (flat vs discriminated union)
  - §4 FieldType — nonexistent field types
  - §4.1 Field props per type — wrong prop shapes / missing types

- **Task B — §8 Components (form + table + resource surfaces)**
  - §8.4 FormRenderer — wrong props entirely
  - §8.3 DataTable — wrong props
  - §8.2 ResourceIndex — props partially wrong
  - §8.5 FieldRenderer — extra props omitted from doc

- **Task C — §8 Action/confirmation components**
  - §8.7 ActionButton/ActionMenu — wrong prop names
  - §8.8 ConfirmDialog — wrong prop names

- **Task D — §9 Hooks correctness (signatures)**
  - §9.1 useResource — wrong return shape
  - §9.2 useArqelForm — wrong call signature
  - §9.5 useTable — wrong option names + missing/extra return fields
  - §9.6 useAction — wrong invoke signature
  - §9.7 useFieldDependencies — wrong signature
  - §9.4 useFlash — matches code (optional low-severity addition only)

- **Task E — §9 Hooks coverage gap + §10/§16 registry & app factory**
  - §9 — real exported hooks missing from the doc entirely (useBreakpoint, useNavigation, useArqelOptimistic, useResourceUpdates)
  - §10 registerField/getFieldComponent — wrong package attribution
  - §16.1 createArqelApp `resolve` — matches; `setup` shown in §10 does not exist as an option

- **Task F — §11 Navigation**
  - §11.2 NavGroup/NavItem — components do not exist

**Note for all implementers:** the headings `useResource`, `FormRenderer`, `FieldSchema`, `useArqelForm` are asserted by an mcp-server Vitest test and must be preserved verbatim — only the content beneath/around them may change.

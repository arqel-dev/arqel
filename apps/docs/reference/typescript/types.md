# `@arqel-dev/types` — API Reference

TypeScript-only package (zero runtime). 7 subpath entry points:

```ts
import type { FieldSchema, FieldType } from '@arqel-dev/types/fields';
import type { ResourceMeta, ResourceIndexProps } from '@arqel-dev/types/resources';
import type { ColumnType, FilterType } from '@arqel-dev/types/tables';
import type { FormSchema, SchemaEntry } from '@arqel-dev/types/forms';
import type { ActionSchema, ActionFormField } from '@arqel-dev/types/actions';
import type { SharedProps, AuthPayload } from '@arqel-dev/types/inertia';
```

## Fields

`FieldType` is a **discriminated union** over `type`:

```ts
type FieldType =
  | { type: 'text'; props: TextFieldProps }
  | { type: 'textarea'; props: TextareaFieldProps }
  | { type: 'email'; props: EmailFieldProps }
  | ... 18 more
```

`FieldSchema` is the canonical shape of the Inertia payload (aligned with `Arqel\Core\Support\FieldSchemaSerializer`):

```ts
type FieldSchema<T extends FieldType = FieldType> = {
  name: string;
  label: string;
  component: string | null;
  required: boolean;
  readonly: boolean;
  disabled: boolean;
  placeholder: string | null;
  helperText: string | null;
  defaultValue: unknown;
  columnSpan: number;
  live: boolean;
  liveDebounce: number | null;
  validation: { rules: string[]; messages: Record<string, string>; attribute: string | null };
  visibility: { create: boolean; edit: boolean; detail: boolean; table: boolean; canSee: boolean };
  dependsOn: string[];
} & T;
```

### Type guards

```ts
isFieldType<T extends FieldType['type']>(field: FieldSchema, type: T): field is FieldSchema<Extract<FieldType, { type: T }>>
isFieldEntry(entry: SchemaEntry): entry is FieldEntry
isLayoutEntry(entry: SchemaEntry): entry is LayoutEntry
resolveFieldEntry(entry: FieldEntry, fields: FieldSchema[]): FieldSchema | undefined
```

## Resources

| Type | Description |
|---|---|
| `ResourceMeta` | `{ slug, label, pluralLabel, navigationIcon, navigationGroup }` |
| `ResourceIndexProps<T>` | Props of `Pages/Arqel/Index.tsx`. Generic over RecordType |
| `ResourceCreateProps` | Props of `Pages/Arqel/Create.tsx` |
| `ResourceEditProps<T>` | Props of `Pages/Arqel/Edit.tsx` |
| `ResourceDetailProps<T>` | Props of `Pages/Arqel/Show.tsx` |

## Tables

| Type | Variants |
|---|---|
| `ColumnType` | 9: `text`, `badge`, `boolean`, `date`, `number`, `icon`, `image`, `relationship`, `computed` |
| `FilterType` | 6: `select`, `multiSelect`, `dateRange`, `text`, `ternary`, `scope` |
| `ColumnSchema<T>` | Discriminated by `type` |
| `FilterSchema<T>` | Discriminated by `type` |

## Forms

```ts
type SchemaEntry =
  | { kind: 'field'; name: string }
  | { kind: 'layout'; type: 'section' | 'fieldset' | 'grid' | 'columns' | 'group' | 'tabs' | 'tab'; ... }

type FormSchema = {
  schema: SchemaEntry[];
  fields: FieldSchema[];
  columns: number;
  model: string | null;
  inline: boolean;
  disabled: boolean;
};
```

## Actions

```ts
type ActionSchema =
  | { variant: 'row'; ... }
  | { variant: 'bulk'; chunkSize: number; ... }
  | { variant: 'toolbar'; ... }
  | { variant: 'header'; ... }

type ConfirmationConfig = {
  heading: string;
  description: string | null;
  icon: string | null;
  color: 'destructive' | 'warning' | 'info';
  requiresText: string | null;
  submitLabel: string;
  cancelLabel: string;
};

type ActionFormField = { name: string; type: FieldType['type'] };
```

## Inertia

`SharedProps` is the shape that `<HandleArqelInertiaRequests>` injects into every response:

```ts
type SharedProps = {
  auth: AuthPayload;
  panel: PanelMeta;
  tenant: TenantMeta | null;
  flash: FlashPayload;
  translations: Record<string, Record<string, string>>;
  arqel: { version: string };
};

type AuthPayload = {
  user: { id: number | string; name: string; email: string } | null;
  can: Record<string, boolean | undefined>;
};
```

## Conventions

- `sideEffects: false` — tree-shake friendly (zero runtime anyway)
- `strict: true` + `noUncheckedIndexedAccess: true` + `exactOptionalPropertyTypes: true` in `tsconfig.json`
- Generics over `RecordType` allow app-specific row types

## Related

- SKILL: [`packages-js/types/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages-js/types/SKILL.md)
- Next: [`@arqel-dev/react`](/reference/typescript/react)

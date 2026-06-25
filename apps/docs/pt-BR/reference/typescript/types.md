# `@arqel-dev/types` — API Reference

Pacote TypeScript-only (zero runtime). 7 entry points subpath:

```ts
import type { FieldSchema, FieldType } from '@arqel-dev/types/fields';
import type { ResourceMeta, ResourceIndexProps } from '@arqel-dev/types/resources';
import type { ColumnType, FilterType } from '@arqel-dev/types/tables';
import type { FormSchema, SchemaEntry } from '@arqel-dev/types/forms';
import type { ActionSchema, ActionFormField } from '@arqel-dev/types/actions';
import type { SharedProps, AuthPayload } from '@arqel-dev/types/inertia';
```

## Fields

`FieldType` é uma **union de literais string** de 21 tags de tipo:

```ts
type FieldType =
  | 'text' | 'textarea' | 'email' | 'url' | 'password' | 'slug'
  | 'number' | 'currency' | 'boolean' | 'toggle' | 'select' | 'multiSelect'
  | 'radio' | 'belongsTo' | 'hasMany' | 'date' | 'dateTime' | 'file'
  | 'image' | 'color' | 'hidden';
```

`FieldSchema` é a **discriminated union** (não-genérica) sobre `type` — uma union de membros `FieldBase<TType, TProps>` (ex.: `FieldBase<'text', TextFieldProps>`) — e o shape canônico do payload Inertia (alinha com `Arqel\Core\Support\FieldSchemaSerializer`):

```ts
interface FieldBase<TType extends FieldType, TProps> {
  type: TType;
  props: TProps;
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
  validation: { rules: string[]; messages: Record<string, string>; attribute: string | null };
  visibility: { create: boolean; edit: boolean; detail: boolean; table: boolean; canSee: boolean };
  dependsOn: string[];
}
```

### Type guards

```ts
isFieldType<T extends FieldType>(field: FieldSchema, type: T): field is Extract<FieldSchema, { type: T }>
isFieldEntry(entry: SchemaEntry): entry is FieldEntry
isLayoutEntry(entry: SchemaEntry): entry is LayoutEntry
resolveFieldEntry(entry: FieldEntry, fields: FieldSchema[]): FieldSchema | null
```

## Resources

| Type | Descrição |
|---|---|
| `ResourceMeta` | `{ slug, label, pluralLabel, navigationIcon, navigationGroup }` |
| `ResourceIndexProps<T>` | Props de `Pages/Arqel/Index.tsx`. Genérico sobre RecordType |
| `ResourceCreateProps` | Props de `Pages/Arqel/Create.tsx` |
| `ResourceEditProps<T>` | Props de `Pages/Arqel/Edit.tsx` |
| `ResourceDetailProps<T>` | Props de `Pages/Arqel/Show.tsx` |

## Tables

| Type | Variantes |
|---|---|
| `ColumnType` | 9: `text`, `badge`, `boolean`, `date`, `number`, `icon`, `image`, `relationship`, `computed` |
| `FilterType` | 6: `select`, `multiSelect`, `dateRange`, `text`, `ternary`, `scope` |
| `ColumnSchema<T>` | Discriminated por `type` |
| `FilterSchema<T>` | Discriminated por `type` |

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

`SharedProps` é o shape que `<HandleArqelInertiaRequests>` injeta em cada response:

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

## Convenções

- `sideEffects: false` — tree-shake friendly (zero runtime de qualquer forma)
- `strict: true` + `noUncheckedIndexedAccess: true` + `exactOptionalPropertyTypes: true` no `tsconfig.json`
- Genéricos sobre `RecordType` permitem app-specific row types

## Related

- SKILL: [`packages-js/types/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages-js/types/SKILL.md)
- Próximo: [`@arqel-dev/react`](/pt-BR/reference/typescript/react)

# SKILL.md — @arqel/types

> Contexto canónico para AI agents. Estrutura conforme `PLANNING/04-repo-structure.md` §11.

## Purpose

`@arqel/types` é o pacote base de TypeScript types compartilhados por todos os outros pacotes JS do ecossistema Arqel. Materializa em TS o que o PHP serializa (Fields, Tables, Forms, Actions, Resource payloads, Inertia shared props) — um único source of truth para o React side consumir sem reshape.

**Zero runtime** — apenas types + 4 type guards:
- `isFieldType(field, type)` — narrow `FieldSchema` por tipo discriminator
- `isFieldEntry(entry)` / `isLayoutEntry(entry)` — narrow `FormSchema` schema entries
- `resolveFieldEntry(entry, fields)` — lookup de `FieldEntry` no array flat de Fields

## Status

**Entregue (TYPES-001..002, 004 parcial):**

- Pacote npm `@arqel/types` com 7 entry points (`./`, `./fields`, `./resources`, `./tables`, `./forms`, `./actions`, `./inertia`)
- `tsup` build com `dts: true`, ESM, sourcemaps, tree-shake friendly (`sideEffects: false`)
- 21 Field types em discriminated union sobre `type`
- 9 Column types + 6 Filter types em discriminated unions
- 7 Layout components (Section/Fieldset/Grid/Columns/Group/Tabs/Tab)
- ResourceMeta, PaginationMeta, ResourceIndex/Create/Edit/Detail props (genéricos sobre `RecordType`)
- ActionSchema com discriminated `type`, ConfirmationConfig, ActionFormField
- SharedProps (Inertia) com auth/panel/tenant/flash/translations/arqel
- Vitest + expect-type para type-level assertions
- 19+ type-level assertions passando

**Por chegar:**

- Integração documentada com `spatie/laravel-typescript-transformer` (TYPES-003)

## Key Contracts

### FieldSchema (discriminated union)

```ts
import { type FieldSchema, isFieldType } from '@arqel/types/fields';

function render(field: FieldSchema) {
    if (isFieldType(field, 'belongsTo')) {
        // field.props.relatedResource é string (narrowed)
        return <BelongsToInput resource={field.props.relatedResource} />;
    }

    if (isFieldType(field, 'select')) {
        // field.props.options é SelectOption[] | Record<string, string>
        return <SelectInput options={field.props.options} />;
    }

    return <TextInput field={field} />;
}
```

Cada campo carrega `validation`, `visibility`, `dependsOn` e `props` (type-specific).

### FormSchema + SchemaEntry

```ts
import { isLayoutEntry, isFieldEntry, type FormSchema } from '@arqel/types/forms';

function renderSchema(form: FormSchema, fields: FieldSchema[]) {
    return form.schema.map((entry) => {
        if (isLayoutEntry(entry)) {
            // entry.type narrows entre 'section'|'fieldset'|...
            return <LayoutComponent {...entry} />;
        }

        const resolved = resolveFieldEntry(entry, fields);
        return resolved ? <FieldRenderer field={resolved} /> : null;
    });
}
```

### Resource page props

```ts
import type { ResourceIndexProps, RecordType } from '@arqel/types/resources';

interface User extends RecordType {
    id: number;
    email: string;
}

export default function UsersIndex({ resource, records, columns }: ResourceIndexProps<User>) {
    // records é User[]; columns é ColumnSchema[]
}
```

### Inertia SharedProps

```ts
import type { SharedProps } from '@arqel/types/inertia';
import { usePage } from '@inertiajs/react';

const { auth, flash } = usePage<SharedProps>().props;
// auth.user: AuthUserPayload | null
// auth.can.viewAdminPanel: boolean | undefined
```

## Conventions

- **Subpath imports** preferidos sobre o barrel para tree-shaking (`@arqel/types/fields` em vez de `@arqel/types`)
- Discriminated unions usam `type` como discriminator — usa `isFieldType` etc. para narrow
- `RecordType` é loose por defeito — apps com `spatie/laravel-typescript-transformer` (TYPES-003) substituem por interfaces strict
- Mantém-se sync com `Arqel\Core\Support\FieldSchemaSerializer` PHP — divergência causa bugs de runtime no React

## Anti-patterns

- ❌ **Casts (`as FieldSchema`)** — usa type guards. Se o PHP emitir um shape inválido, prefere falhar cedo
- ❌ **Manual reshape** dos payloads no React — o serializer já produz o shape canônico
- ❌ **`any` em vez de `unknown`** para `defaultValue` / `tenant` — mantém safety, força narrow
- ❌ **Duplicar Field types** em `@arqel/fields` (UI side) — aquele pacote consome estes types
- ❌ **Importar `dist/`** diretamente — usa apenas exports `./*` declarados em `package.json`

## Related

- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §TYPES-001..004
- API: [`PLANNING/06-api-react.md`](../../PLANNING/06-api-react.md) §3-7
- Source: [`packages-js/types/src/`](src/)
- Tests: [`packages-js/types/tests/`](tests/)

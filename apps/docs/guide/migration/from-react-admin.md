# Vindo de react-admin

> **TL;DR:** Você já sabe React. O ajuste é entender que **a UI é declarada server-side em PHP**, não no client. Inertia substitui REST/GraphQL — props vêm prontas do servidor, mutations voltam pro servidor.

## Quem é o público

Devs React/Next.js que conhecem [react-admin](https://marmelab.com/react-admin/) ou [Refine](https://refine.dev/) e querem **Laravel-first** sem reinventar API REST/GraphQL.

## Mental shift

### react-admin: client-driven

```tsx
// react-admin
<Resource name="posts" list={PostList} create={PostCreate} edit={PostEdit} />

const PostList = () => (
  <List filters={postFilters}>
    <Datagrid>
      <TextField source="title" />
      <DateField source="published_at" />
    </Datagrid>
  </List>
);
```

Os components React **declaram** o que renderizar; um `dataProvider` busca dados via REST/GraphQL.

### Arqel: server-driven

```php
// Arqel
final class PostResource extends Resource
{
    public function fields(): array
    {
        return [
            Field::text('title')->required(),
            Field::dateTime('published_at'),
        ];
    }

    public function table(): Table
    {
        return Table::make()->columns([
            TextColumn::make('title')->sortable(),
            DateColumn::make('published_at')->sortable(),
        ])->filters([...]);
    }
}
```

Os components React de `@arqel/ui` (`<DataTable>`, `<FormRenderer>`) recebem o **schema serializado** via Inertia props e renderizam dinamicamente. Você não escreve `<TextField source="title" />` — o servidor declara isso.

## Por que essa inversão?

- **Single source of truth:** validation rules, visibility, auth — tudo em PHP, próximo ao model
- **Type-safe end-to-end:** types compartilhados em `@arqel/types` espelham o schema PHP
- **Sem API REST/GraphQL custom:** Inertia é o transport
- **Custom React quando precisar:** override `Pages/Arqel/Index.tsx` ou registre um custom field component

## Mapping de conceitos

| react-admin | Arqel |
|---|---|
| `<Resource>` | `class PostResource extends Resource` (PHP) |
| `dataProvider` | Inertia (zero config) |
| `authProvider` | Laravel Auth + Policies + `AbilityRegistry` |
| `<List>` / `<Datagrid>` | `<DataTable>` recebe schema via Inertia |
| `<TextField source="x">` | `TextColumn::make('x')` (PHP) |
| `<TextInput source="x">` | `Field::text('x')` (PHP) |
| `<ReferenceInput>` | `Field::belongsTo('user_id', UserResource::class)` |
| `<SelectInput choices={...}>` | `Field::select('status')->options([...])` |
| `validate={[required(), maxLength(120)]}` | `->required()->maxLength(120)` (PHP) |
| `<EditButton>` | `Actions::edit()` (PHP) — mas `<ActionButton>` no client |
| Custom action via `useUpdate` hook | `RowAction::make('publish')->action(fn ($record) => ...)` |
| Custom filter | `SelectFilter::make()`, etc. + custom React via override |

## Quando você ainda escreve React

- **Custom Field type:** TSX em `resources/js/Arqel/Fields/RatingInput.tsx` + `registerField('RatingInput', RatingInput)`
- **Custom Page override:** `resources/js/Pages/Arqel/Posts/Index.tsx` substitui o default de Arqel
- **Widget de dashboard:** componentes próprios fora do Resource CRUD
- **Sub-tree custom dentro de FormRenderer:** wrapper React que usa hooks

## Quando você **não** escreve React

- Listar/filtrar/ordenar uma table → declarativo PHP
- Form de create/edit com 21 field types canónicos → declarativo PHP
- Auth (visibility + edit gates) → Policies PHP + `canSee/canEdit` Closures
- Bulk actions, confirmation modals, toasts → declarativo PHP

## Estado/data fetching

react-admin usa React Query/SWR sob o capô. Arqel proíbe essas libs no Resource CRUD ([ADR-001](https://github.com/arqel/arqel/blob/main/PLANNING/03-adrs.md)) e usa **partial reload Inertia** (`router.reload({ only: ['posts'] })`) para atualizar pedaços específicos do payload — sem cache local mexido, sem stale state.

Para fora do CRUD (ex: chart de analytics num dashboard widget), você pode usar `fetch`/Axios livremente.

## Próximos passos

- [Getting Started](/guide/getting-started)
- [Tutorial: primeiro CRUD](/guide/tutorial-first-crud)
- [Custom Fields](/advanced/custom-fields)
- ADR-001: [Inertia-only](https://github.com/arqel/arqel/blob/main/PLANNING/03-adrs.md)

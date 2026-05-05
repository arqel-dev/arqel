# Coming from react-admin

> **TL;DR:** You already know React. The adjustment is understanding that **the UI is declared server-side in PHP**, not on the client. Inertia replaces REST/GraphQL — props come ready from the server, mutations go back to the server.

## Who this is for

React/Next.js devs who know [react-admin](https://marmelab.com/react-admin/) or [Refine](https://refine.dev/) and want **Laravel-first** without reinventing a REST/GraphQL API.

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

The React components **declare** what to render; a `dataProvider` fetches data via REST/GraphQL.

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

The React components from `@arqel-dev/ui` (`<DataTable>`, `<FormRenderer>`) receive the **serialized schema** via Inertia props and render dynamically. You don't write `<TextField source="title" />` — the server declares that.

## Why this inversion?

- **Single source of truth:** validation rules, visibility, auth — all in PHP, close to the model
- **Type-safe end-to-end:** shared types in `@arqel-dev/types` mirror the PHP schema
- **No custom REST/GraphQL API:** Inertia is the transport
- **Custom React when needed:** override `Pages/Arqel/Index.tsx` or register a custom field component

## Concept mapping

| react-admin | Arqel |
|---|---|
| `<Resource>` | `class PostResource extends Resource` (PHP) |
| `dataProvider` | Inertia (zero config) |
| `authProvider` | Laravel Auth + Policies + `AbilityRegistry` |
| `<List>` / `<Datagrid>` | `<DataTable>` receives schema via Inertia |
| `<TextField source="x">` | `TextColumn::make('x')` (PHP) |
| `<TextInput source="x">` | `Field::text('x')` (PHP) |
| `<ReferenceInput>` | `Field::belongsTo('user_id', UserResource::class)` |
| `<SelectInput choices={...}>` | `Field::select('status')->options([...])` |
| `validate={[required(), maxLength(120)]}` | `->required()->maxLength(120)` (PHP) |
| `<EditButton>` | `Actions::edit()` (PHP) — but `<ActionButton>` on the client |
| Custom action via `useUpdate` hook | `RowAction::make('publish')->action(fn ($record) => ...)` |
| Custom filter | `SelectFilter::make()`, etc. + custom React via override |

## When you still write React

- **Custom Field type:** TSX in `resources/js/Arqel/Fields/RatingInput.tsx` + `registerField('RatingInput', RatingInput)`
- **Custom Page override:** `resources/js/Pages/Arqel/Posts/Index.tsx` replaces the Arqel default
- **Dashboard widget:** custom components outside the Resource CRUD
- **Custom sub-tree inside FormRenderer:** React wrapper that uses hooks

## When you **don't** write React

- List/filter/sort a table → declarative PHP
- Create/edit form with the 21 canonical field types → declarative PHP
- Auth (visibility + edit gates) → PHP Policies + `canSee/canEdit` Closures
- Bulk actions, confirmation modals, toasts → declarative PHP

## State / data fetching

react-admin uses React Query/SWR under the hood. Arqel forbids those libs in Resource CRUD ([ADR-001](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md)) and uses **Inertia partial reload** (`router.reload({ only: ['posts'] })`) to update specific pieces of the payload — no client cache to manage, no stale state.

Outside CRUD (e.g. an analytics chart in a dashboard widget), you can freely use `fetch`/Axios.

## Next steps

- [Getting Started](/guide/getting-started)
- [Tutorial: first CRUD](/guide/tutorial-first-crud)
- [Custom Fields](/advanced/custom-fields)
- ADR-001: [Inertia-only](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md)

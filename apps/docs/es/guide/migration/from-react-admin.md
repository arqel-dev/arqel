# Viniendo de react-admin

> **TL;DR:** Ya conoces React. El ajuste es entender que **la UI se declara del lado del servidor en PHP**, no en el cliente. Inertia reemplaza REST/GraphQL — las props vienen listas del servidor, las mutaciones vuelven al servidor.

## Para quién es esto

Devs React/Next.js que conocen [react-admin](https://marmelab.com/react-admin/) o [Refine](https://refine.dev/) y quieren **Laravel-first** sin reinventar una API REST/GraphQL.

## Cambio mental

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

Los componentes React **declaran** qué renderizar; un `dataProvider` hace fetch de los datos vía REST/GraphQL.

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

Los componentes React de `@arqel-dev/ui` (`<DataTable>`, `<FormRenderer>`) reciben el **schema serializado** vía props de Inertia y renderizan dinámicamente. No escribes `<TextField source="title" />` — el servidor lo declara.

## ¿Por qué esta inversión?

- **Single source of truth:** reglas de validación, visibilidad, auth — todo en PHP, cerca del modelo
- **Type-safe end-to-end:** types compartidos en `@arqel-dev/types` reflejan el schema PHP
- **Sin API REST/GraphQL personalizada:** Inertia es el transporte
- **React personalizado cuando se necesita:** sobrescribe `Pages/Arqel/Index.tsx` o registra un componente de field personalizado

## Mapeo de conceptos

| react-admin | Arqel |
|---|---|
| `<Resource>` | `class PostResource extends Resource` (PHP) |
| `dataProvider` | Inertia (zero config) |
| `authProvider` | Laravel Auth + Policies + `AbilityRegistry` |
| `<List>` / `<Datagrid>` | `<DataTable>` recibe schema vía Inertia |
| `<TextField source="x">` | `TextColumn::make('x')` (PHP) |
| `<TextInput source="x">` | `Field::text('x')` (PHP) |
| `<ReferenceInput>` | `Field::belongsTo('user_id', UserResource::class)` |
| `<SelectInput choices={...}>` | `Field::select('status')->options([...])` |
| `validate={[required(), maxLength(120)]}` | `->required()->maxLength(120)` (PHP) |
| `<EditButton>` | `Actions::edit()` (PHP) — pero `<ActionButton>` en el cliente |
| Action personalizado vía hook `useUpdate` | `RowAction::make('publish')->action(fn ($record) => ...)` |
| Filter personalizado | `SelectFilter::make()`, etc. + React personalizado vía override |

## Cuándo todavía escribes React

- **Tipo de Field personalizado:** TSX en `resources/js/Arqel/Fields/RatingInput.tsx` + `registerField('RatingInput', RatingInput)`
- **Override de Page personalizado:** `resources/js/Pages/Arqel/Posts/Index.tsx` reemplaza el default de Arqel
- **Widget de dashboard:** componentes personalizados fuera del CRUD del Resource
- **Sub-tree personalizado dentro de FormRenderer:** wrapper React que usa hooks

## Cuándo **no** escribes React

- List/filter/sort de una tabla → PHP declarativo
- Form de create/edit con los 21 tipos de field canónicos → PHP declarativo
- Auth (gates de visibilidad + edit) → Policies PHP + Closures `canSee/canEdit`
- Bulk actions, modales de confirmación, toasts → PHP declarativo

## State / data fetching

react-admin usa React Query/SWR por debajo. Arqel prohíbe esas libs en el CRUD del Resource ([ADR-001](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md)) y usa **partial reload de Inertia** (`router.reload({ only: ['posts'] })`) para actualizar piezas específicas del payload — sin client cache que gestionar, sin stale state.

Fuera del CRUD (p. ej. un chart de analytics en un widget de dashboard), puedes usar `fetch`/Axios libremente.

## Próximos pasos

- [Empezando](/es/guide/getting-started)
- [Tutorial: primer CRUD](/es/guide/tutorial-first-crud)
- [Custom Fields](/es/advanced/custom-fields)
- ADR-001: [Inertia-only](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md)

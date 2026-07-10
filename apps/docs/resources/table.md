# Table

`Arqel\Table\Table` is the fluent builder behind a Resource's index page: columns, filters, sorting, pagination, search, and row/bulk/toolbar actions. It serializes to a schema in the Inertia payload, which `@arqel-dev/ui`'s `<DataTable>` renders on the client.

You don't need to declare a `table()` method at all — Arqel derives a reasonable table straight from `Resource::fields()`. Reach for a custom `Table` when you need column types, filters, or behavior the auto-derivation can't express.

Full method reference: [`arqel-dev/table`](/reference/php/table).

## The minimum

```php
use Arqel\Table\Table;
use Arqel\Table\Columns\TextColumn;

public function table(): Table
{
    return Table::make()
        ->columns([
            TextColumn::make('title')->sortable()->searchable(),
            TextColumn::make('author.name')->label('Author'),
        ]);
}
```

## Column types

Columns follow the ADR-019 convention: concrete classes, not a factory alias (`TextColumn::make('name')`, not `Column::text('name')`).

| Class | Use case |
|---|---|
| `TextColumn` | Default string/text |
| `BadgeColumn` | Status with colors |
| `BooleanColumn` | Checkmark |
| `DateColumn` | Formatted dates (`date`, `dateTime`, `since`) |
| `NumberColumn` | Right-aligned numeric |
| `IconColumn` | Single icon |
| `ImageColumn` | Thumbnail |
| `RelationshipColumn` | Eager-loaded relation |
| `ComputedColumn` | Closure-derived value |
| `SelectColumn` | Editable select cell (inline edit) |
| `TextInputColumn` | Editable inline text-input cell |
| `ToggleColumn` | Editable toggle cell |

Every column shares a common setter surface: `label`, `sortable(bool)`, `searchable(bool)`, `hidden(bool)`, `hiddenOnMobile(bool)`, `align(string)`, `width(string)`, `tooltip(string|Closure)`.

```php
use Arqel\Table\Columns\{BadgeColumn, DateColumn, RelationshipColumn};

->columns([
    BadgeColumn::make('status')->colors([
        'draft' => 'gray',
        'published' => 'green',
    ]),
    DateColumn::make('created_at')->since(),
    RelationshipColumn::make('category')->display('name'),
])
```

## Filters

```php
use Arqel\Table\Filters\{SelectFilter, DateRangeFilter, TernaryFilter};

public function table(): Table
{
    return Table::make()
        ->columns([...])
        ->filters([
            SelectFilter::make('status')->options([
                'draft' => 'Draft',
                'published' => 'Published',
            ]),
            DateRangeFilter::make('created_at'),
            TernaryFilter::make('is_featured'),
        ]);
}
```

Eight filter types are available: `SelectFilter`, `MultiSelectFilter`, `DateRangeFilter`, `TextFilter`, `TernaryFilter`, `ScopeFilter` (wraps an Eloquent scope), `QueryBuilderFilter` (a visual AND/OR condition tree), and `TrashedFilter` (soft-delete three-state: without/with/only).

## Sort, search, pagination

```php
Table::make()
    ->defaultSort('created_at', 'desc')
    ->perPage(25)
    ->perPageOptions([10, 25, 50, 100])
    ->searchable()   // global cross-column search
    ->selectable()   // adds checkboxes + enables bulk actions
    ->striped()
    ->compact();
```

Sorting is whitelisted server-side against columns marked `sortable()` — arbitrary column names in the request are ignored. `per_page` is validated against `perPageOptions`, and eager loading is inferred automatically from any `RelationshipColumn` you declare, via `TableQueryBuilder`.

## Actions

Row, bulk, and toolbar actions attach directly to the table:

```php
use Arqel\Actions\Actions;

Table::make()
    ->columns([...])
    ->actions([Actions::edit(), Actions::delete()])
    ->bulkActions([Actions::deleteBulk()])
    ->toolbarActions([Actions::create()]);
```

`bulkActions()` only take effect when `selectable()` is also set. See [Actions](/resources/actions) for the full set of variants and how to write custom ones.

## Empty state

```php
Table::make()->emptyState([
    'icon' => 'inbox',
    'title' => 'No posts yet',
    'description' => 'Create your first post to get started.',
]);
```

## Anti-patterns

- Putting query scoping logic inside `table()` — use `Resource::indexQuery()` instead; `Table` only describes presentation.
- Reaching for `Column::make()` out of habit — always use the concrete class (`TextColumn::make`, `BadgeColumn::make`, ...); the factory-alias convention is a `Field`-only thing (ADR-019).
- Adding `bulkActions()` without `selectable()` — the checkboxes won't render and the actions become unreachable.

## Next steps

- [Resource](/resources/resource) — where `table()` is declared
- [Actions](/resources/actions) — row/bulk/toolbar buttons in depth
- Full API: [`arqel-dev/table` reference](/reference/php/table)

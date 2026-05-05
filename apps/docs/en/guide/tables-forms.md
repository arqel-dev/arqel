# Tables & Forms

`Arqel\Table\Table` and `Arqel\Form\Form` are fluent builders that produce schemas serialized to the Inertia payload. React renders them via `<DataTable>` and `<FormRenderer>` from `@arqel-dev/ui`.

## Tables

### The minimum

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

### Column types

| Class | Use |
|---|---|
| `TextColumn` | Default string/text |
| `BadgeColumn` | Status with colors |
| `BooleanColumn` | Checkmark |
| `DateColumn` | `displayFormat('d/m/Y')` |
| `NumberColumn` | Right-aligned numeric |
| `IconColumn` | Single icon |
| `ImageColumn` | Thumbnail |
| `RelationshipColumn` | Eager-loaded relation |
| `ComputedColumn` | Derived closure |

### Filters

```php
use Arqel\Table\Filters\{SelectFilter, DateRangeFilter, TernaryFilter};

->filters([
    SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published']),
    DateRangeFilter::make('created_at'),
    TernaryFilter::make('is_featured'),
])
```

6 types available: `SelectFilter`, `MultiSelectFilter`, `DateRangeFilter`, `TextFilter`, `TernaryFilter`, `ScopeFilter`.

### Sort, search, pagination

```php
Table::make()
    ->defaultSort('created_at', 'desc')
    ->perPage(25)
    ->searchable()       // global cross-column search
    ->selectable()       // checkbox + bulk actions
    ->striped();
```

### Actions in the table

```php
->actions([Actions::edit(), Actions::delete()])
->bulkActions([Actions::deleteBulk()])
->toolbarActions([Actions::create()]);
```

Actions [covered in depth here](/guide/actions).

## Forms

### The minimum

When you only need to render `Resource::fields()` in a 1-column form, you **don't need** to declare anything — Arqel auto-derives. For custom layouts:

```php
use Arqel\Form\Form;
use Arqel\Form\Layout\{Section, Grid, Tabs, Tab};

public function form(): Form
{
    return Form::make()->schema([
        Section::make('Content')
            ->description('Post title and body')
            ->schema([
                Field::text('title')->required()->columnSpan(2),
                Field::slug('slug')->fromField('title'),
            ])
            ->columns(2),

        Section::make('Publishing')
            ->aside()                       // side sidebar on desktop
            ->schema([
                Field::select('status')->options(['draft' => 'Draft', 'published' => 'Published']),
                Field::dateTime('published_at'),
            ]),
    ])->columns(3);
}
```

### Layout components

| Class | Setters |
|---|---|
| `Section` | `heading`, `description`, `icon`, `collapsible`, `collapsed`, `columns`, `compact`, `aside` |
| `Fieldset` | `legend`, `columns` |
| `Grid` | `columns(int)` or `columns(['sm' => 1, 'md' => 2, 'lg' => 4])` |
| `Columns` | semantic shortcut for `Grid::columns(2)` |
| `Group` | no visual chrome; `orientation('horizontal'\|'vertical')` |
| `Tabs` | `tabs(array<Tab>)`, `defaultTab(id)`, `vertical()`/`horizontal()` |
| `Tab` | `id`, `label`, `icon`, `badge(int\|Closure)` |

### Tabs with badge

```php
Tabs::make()->tabs([
    Tab::make()->id('content')->label('Content')->schema([...]),
    Tab::make()->id('seo')->label('SEO')->schema([...]),
    Tab::make()->id('comments')->label('Comments')
        ->badge(fn ($record) => $record?->comments_count ?? 0)
        ->schema([...]),
])->defaultTab('content');
```

`badge()` accepts `int` or `Closure(?Model)`. Non-int closures are gracefully discarded.

### Layout visibility

`Section`/`Fieldset`/`Group`/etc. expose `visibleIf` and `canSee` — they hide the entire block:

```php
Section::make('Admin only')
    ->canSee(fn ($user) => $user?->is_admin)
    ->schema([Field::text('internal_id')]),
```

### Generated form requests

`php artisan arqel:form-request PostResource` generates `app/Http/Requests/StorePostRequest.php` and `UpdatePostRequest.php`:

```php
final class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Post::class);
    }

    public function rules(): array
    {
        return app(FieldRulesExtractor::class)->extract(
            app(PostResource::class)->fields()
        );
    }
}
```

You can edit them freely — Arqel does not regenerate by default (`--force` to overwrite).

## Anti-patterns

- Query logic inside `table()` — use `indexQuery()` on the Resource
- Full form declaration when you only need 1 column — Arqel auto-derives when `form()` is omitted
- Layout components inside a Field — Layout is the parent, not the child

## Next steps

- [Actions](/guide/actions) — row/bulk/toolbar buttons
- [Auth](/guide/auth) — Policies + field-level
- API reference: [`packages/table/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/table/SKILL.md), [`packages/form/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/form/SKILL.md)

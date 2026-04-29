# Tables & Forms

`Arqel\Table\Table` e `Arqel\Form\Form` são builders fluentes que produzem schemas serializados para o Inertia payload. O React renderiza-os via `<DataTable>` e `<FormRenderer>` em `@arqel/ui`.

## Tables

### O mínimo

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
| `TextColumn` | String/text default |
| `BadgeColumn` | Status com cores |
| `BooleanColumn` | Checkmark |
| `DateColumn` | `displayFormat('d/m/Y')` |
| `NumberColumn` | Right-aligned numeric |
| `IconColumn` | Single ícone |
| `ImageColumn` | Thumbnail |
| `RelationshipColumn` | Eager-loaded relation |
| `ComputedColumn` | Closure derivada |

### Filters

```php
use Arqel\Table\Filters\{SelectFilter, DateRangeFilter, TernaryFilter};

->filters([
    SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published']),
    DateRangeFilter::make('created_at'),
    TernaryFilter::make('is_featured'),
])
```

6 tipos disponíveis: `SelectFilter`, `MultiSelectFilter`, `DateRangeFilter`, `TextFilter`, `TernaryFilter`, `ScopeFilter`.

### Sort, search, pagination

```php
Table::make()
    ->defaultSort('created_at', 'desc')
    ->perPage(25)
    ->searchable()       // busca global cross-column
    ->selectable()       // checkbox + bulk actions
    ->striped();
```

### Actions na table

```php
->actions([Actions::edit(), Actions::delete()])
->bulkActions([Actions::deleteBulk()])
->toolbarActions([Actions::create()]);
```

Ações [aprofundadas aqui](/guide/actions).

## Forms

### O mínimo

Quando você só precisa renderizar `Resource::fields()` num form 1-coluna, **não precisa** declarar nada — Arqel auto-deriva. Para layouts custom:

```php
use Arqel\Form\Form;
use Arqel\Form\Layout\{Section, Grid, Tabs, Tab};

public function form(): Form
{
    return Form::make()->schema([
        Section::make('Conteúdo')
            ->description('Título e corpo do post')
            ->schema([
                Field::text('title')->required()->columnSpan(2),
                Field::slug('slug')->fromField('title'),
            ])
            ->columns(2),

        Section::make('Publicação')
            ->aside()                       // sidebar lateral em desktop
            ->schema([
                Field::select('status')->options(['draft' => 'Draft', 'published' => 'Published']),
                Field::dateTime('published_at'),
            ]),
    ])->columns(3);
}
```

### Layout components

| Class | Atalhos |
|---|---|
| `Section` | `heading`, `description`, `icon`, `collapsible`, `collapsed`, `columns`, `compact`, `aside` |
| `Fieldset` | `legend`, `columns` |
| `Grid` | `columns(int)` ou `columns(['sm' => 1, 'md' => 2, 'lg' => 4])` |
| `Columns` | atalho semântico para `Grid::columns(2)` |
| `Group` | sem chrome visual; `orientation('horizontal'\|'vertical')` |
| `Tabs` | `tabs(array<Tab>)`, `defaultTab(id)`, `vertical()`/`horizontal()` |
| `Tab` | `id`, `label`, `icon`, `badge(int\|Closure)` |

### Tabs com badge

```php
Tabs::make()->tabs([
    Tab::make()->id('content')->label('Content')->schema([...]),
    Tab::make()->id('seo')->label('SEO')->schema([...]),
    Tab::make()->id('comments')->label('Comments')
        ->badge(fn ($record) => $record?->comments_count ?? 0)
        ->schema([...]),
])->defaultTab('content');
```

`badge()` aceita `int` ou `Closure(?Model)`. Closures não-int retornam descartam graciosamente.

### Visibilidade de layout

`Section`/`Fieldset`/`Group`/etc. expõem `visibleIf` e `canSee` — escondem o bloco inteiro:

```php
Section::make('Admin only')
    ->canSee(fn ($user) => $user?->is_admin)
    ->schema([Field::text('internal_id')]),
```

### Form requests gerados

`php artisan arqel:form-request PostResource` gera `app/Http/Requests/StorePostRequest.php` e `UpdatePostRequest.php`:

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

Você pode editar livremente — Arqel não regera por defeito (`--force` para overwrite).

## Anti-patterns

- ❌ **Lógica de query em `table()`** — use `indexQuery()` no Resource
- ❌ **Form completo quando só precisa de 1 coluna** — Arqel auto-deriva quando `form()` é omitido
- ❌ **Layout components dentro de Field** — Layout é o pai, não o filho

## Próximos passos

- [Actions](/guide/actions) — botões row/bulk/toolbar
- [Auth](/guide/auth) — Policies + field-level
- API reference: [`packages/table/SKILL.md`](https://github.com/arqel/arqel/blob/main/packages/table/SKILL.md), [`packages/form/SKILL.md`](https://github.com/arqel/arqel/blob/main/packages/form/SKILL.md)

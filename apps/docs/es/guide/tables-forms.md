# Tables & Forms

`Arqel\Table\Table` y `Arqel\Form\Form` son builders fluidos que producen schemas serializados al payload de Inertia. React los renderiza vía `<DataTable>` y `<FormRenderer>` de `@arqel-dev/ui`.

## Tables

### Lo mínimo

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

### Tipos de columna

| Clase | Uso |
|---|---|
| `TextColumn` | String/texto por defecto |
| `BadgeColumn` | Status con colores |
| `BooleanColumn` | Checkmark |
| `DateColumn` | `displayFormat('d/m/Y')` |
| `NumberColumn` | Numérico alineado a la derecha |
| `IconColumn` | Icono único |
| `ImageColumn` | Thumbnail |
| `RelationshipColumn` | Relación eager-loaded |
| `ComputedColumn` | Closure derivado |

### Filtros

```php
use Arqel\Table\Filters\{SelectFilter, DateRangeFilter, TernaryFilter};

->filters([
    SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published']),
    DateRangeFilter::make('created_at'),
    TernaryFilter::make('is_featured'),
])
```

6 tipos disponibles: `SelectFilter`, `MultiSelectFilter`, `DateRangeFilter`, `TextFilter`, `TernaryFilter`, `ScopeFilter`.

### Orden, búsqueda, paginación

```php
Table::make()
    ->defaultSort('created_at', 'desc')
    ->perPage(25)
    ->searchable()       // búsqueda global cross-column
    ->selectable()       // checkbox + bulk actions
    ->striped();
```

### Actions en la tabla

```php
->actions([Actions::edit(), Actions::delete()])
->bulkActions([Actions::deleteBulk()])
->toolbarActions([Actions::create()]);
```

Las Actions [se cubren en profundidad aquí](/es/guide/actions).

## Forms

### Lo mínimo

Cuando solo necesitas renderizar `Resource::fields()` en un form de 1 columna, **no necesitas** declarar nada — Arqel auto-deriva. Para layouts personalizados:

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
            ->aside()                       // sidebar lateral en desktop
            ->schema([
                Field::select('status')->options(['draft' => 'Draft', 'published' => 'Published']),
                Field::dateTime('published_at'),
            ]),
    ])->columns(3);
}
```

### Componentes de Layout

| Clase | Setters |
|---|---|
| `Section` | `heading`, `description`, `icon`, `collapsible`, `collapsed`, `columns`, `compact`, `aside` |
| `Fieldset` | `legend`, `columns` |
| `Grid` | `columns(int)` o `columns(['sm' => 1, 'md' => 2, 'lg' => 4])` |
| `Columns` | atajo semántico para `Grid::columns(2)` |
| `Group` | sin chrome visual; `orientation('horizontal'\|'vertical')` |
| `Tabs` | `tabs(array<Tab>)`, `defaultTab(id)`, `vertical()`/`horizontal()` |
| `Tab` | `id`, `label`, `icon`, `badge(int\|Closure)` |

### Tabs con badge

```php
Tabs::make()->tabs([
    Tab::make()->id('content')->label('Content')->schema([...]),
    Tab::make()->id('seo')->label('SEO')->schema([...]),
    Tab::make()->id('comments')->label('Comments')
        ->badge(fn ($record) => $record?->comments_count ?? 0)
        ->schema([...]),
])->defaultTab('content');
```

`badge()` acepta `int` o `Closure(?Model)`. Closures que no devuelven int se descartan silenciosamente.

### Visibilidad de Layout

`Section`/`Fieldset`/`Group`/etc. exponen `visibleIf` y `canSee` — ocultan el bloque entero:

```php
Section::make('Admin only')
    ->canSee(fn ($user) => $user?->is_admin)
    ->schema([Field::text('internal_id')]),
```

### Form requests generados

`php artisan arqel:form-request PostResource` genera `app/Http/Requests/StorePostRequest.php` y `UpdatePostRequest.php`:

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

Puedes editarlos libremente — Arqel no los regenera por defecto (`--force` para sobrescribir).

## Anti-patrones

- Lógica de query dentro de `table()` — usa `indexQuery()` en el Resource
- Declaración completa de form cuando solo necesitas 1 columna — Arqel auto-deriva cuando `form()` se omite
- Componentes Layout dentro de un Field — Layout es el padre, no el hijo

## Próximos pasos

- [Actions](/es/guide/actions) — botones row/bulk/toolbar
- [Auth](/es/guide/auth) — Policies + nivel de Field
- Referencia API: [`packages/table/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/table/SKILL.md), [`packages/form/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/form/SKILL.md)

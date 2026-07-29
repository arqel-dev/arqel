# Table

`Arqel\Table\Table` es el builder fluido detrás de la página de index de un Resource: columnas, filtros, ordenación, paginación, búsqueda y actions de fila, en lote y de toolbar. Se serializa a un esquema dentro del payload de Inertia, que el `<DataTable>` de `@arqel-dev/ui` renderiza en el cliente.

No necesitas declarar un método `table()` en absoluto — Arqel deriva una tabla razonable directamente desde `Resource::fields()`. Recurre a un `Table` personalizado cuando necesites tipos de columna, filtros o comportamientos que la autoderivación no puede expresar.

Referencia completa de métodos: [`arqel-dev/table`](/es/reference/php/table).

## Lo mínimo

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

## Tipos de columna

Las columnas siguen la convención del ADR-019: clases concretas, no un alias de factory (`TextColumn::make('name')`, no `Column::text('name')`).

| Clase | Caso de uso |
|---|---|
| `TextColumn` | Cadena/texto por defecto |
| `BadgeColumn` | Estado con colores |
| `BooleanColumn` | Marca de verificación |
| `DateColumn` | Fechas formateadas (`date`, `dateTime`, `since`) |
| `NumberColumn` | Numérico alineado a la derecha |
| `IconColumn` | Un solo icono |
| `ImageColumn` | Miniatura |
| `RelationshipColumn` | Relación con eager loading |
| `ComputedColumn` | Valor derivado de una closure |
| `SelectColumn` | Celda de select editable (edición en línea) |
| `TextInputColumn` | Celda editable de input de texto en línea |
| `ToggleColumn` | Celda de toggle editable |

Todas las columnas comparten una superficie común de setters: `label`, `sortable(bool)`, `searchable(bool)`, `hidden(bool)`, `hiddenOnMobile(bool)`, `align(string)`, `width(string)`, `tooltip(string|Closure)`.

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

## Filtros

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

Hay ocho tipos de filtro disponibles: `SelectFilter`, `MultiSelectFilter`, `DateRangeFilter`, `TextFilter`, `TernaryFilter`, `ScopeFilter` (envuelve un scope de Eloquent), `QueryBuilderFilter` (un árbol visual de condiciones AND/OR) y `TrashedFilter` (tres estados de soft delete: sin/con/solo eliminados).

## Ordenación, búsqueda y paginación

```php
Table::make()
    ->defaultSort('created_at', 'desc')
    ->perPage(25)
    ->perPageOptions([10, 25, 50, 100])
    ->searchable()   // búsqueda global en todas las columnas
    ->selectable()   // añade checkboxes + habilita las bulk actions
    ->striped()
    ->compact();
```

La ordenación se valida en el servidor contra una lista blanca de columnas marcadas como `sortable()` — los nombres de columna arbitrarios en la petición se ignoran. `per_page` se valida contra `perPageOptions`, y el eager loading se infiere automáticamente de cualquier `RelationshipColumn` que declares, mediante `TableQueryBuilder`.

## Actions

Las actions de fila, en lote y de toolbar se adjuntan directamente a la tabla:

```php
use Arqel\Actions\Actions;

Table::make()
    ->columns([...])
    ->actions([Actions::edit(), Actions::delete()])
    ->bulkActions([Actions::deleteBulk()])
    ->toolbarActions([Actions::create()]);
```

`bulkActions()` solo surte efecto cuando `selectable()` también está activo. Consulta [Actions](/es/resources/actions) para conocer el conjunto completo de variantes y cómo escribir las tuyas.

## Estado vacío

```php
Table::make()->emptyState([
    'icon' => 'inbox',
    'title' => 'No posts yet',
    'description' => 'Create your first post to get started.',
]);
```

## Antipatrones

- Poner lógica de acotación de la consulta dentro de `table()` — usa `Resource::indexQuery()` en su lugar; `Table` solo describe la presentación.
- Recurrir a `Column::make()` por costumbre — usa siempre la clase concreta (`TextColumn::make`, `BadgeColumn::make`, ...); la convención de alias de factory es algo exclusivo de `Field` (ADR-019).
- Añadir `bulkActions()` sin `selectable()` — los checkboxes no se renderizarán y las actions quedarán inalcanzables.

## Próximos pasos

- [Resource](/es/resources/resource) — dónde se declara `table()`
- [Actions](/es/resources/actions) — botones de fila, en lote y de toolbar en profundidad
- API completa: [referencia de `arqel-dev/table`](/es/reference/php/table)

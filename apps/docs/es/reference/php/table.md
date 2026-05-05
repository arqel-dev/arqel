# `arqel-dev/table` — Referencia de API

Namespace `Arqel\Table\`. Builder fluido, 9 tipos de columna, 6 tipos de filtro, query builder.

## `Arqel\Table\Table` (final)

Builder principal.

| Método | Tipo | Descripción |
|---|---|---|
| `Table::make()` | `self` | Factory |
| `columns(array<Column>)` | `self` | Lista de columnas |
| `filters(array<Filter>)` | `self` | Lista de filtros |
| `actions(array<Action>)` | `self` | RowActions |
| `bulkActions(array<Action>)` | `self` | BulkActions (requieren `selectable`) |
| `toolbarActions(array<Action>)` | `self` | ToolbarActions |
| `defaultSort(string $column, string $direction='asc')` | `self` | |
| `perPage(int)` / `perPageOptions(array<int>)` | `self` | Paginación |
| `searchable(bool=true)` / `selectable(bool=true)` | `self` | |
| `striped(bool=true)` / `compact(bool=true)` | `self` | |
| `emptyState(array)` | `self` | `['icon', 'title', 'description']` |
| `toArray()` | `array` | Schema serializado para Inertia |

## Columns

`Arqel\Table\Column` (abstract). Factory: `Column::make($name)` retorna `TextColumn` por defecto.

| Clase | Caso de uso | Setters extra |
|---|---|---|
| `TextColumn` | String/text por defecto | `limit(int)`, `wrap(bool)` |
| `BadgeColumn` | Estado con colores | `colors(array)`, `icon(string)` |
| `BooleanColumn` | Checkmark | `trueIcon`, `falseIcon`, `trueColor`, `falseColor` |
| `DateColumn` | Fechas formateadas | `displayFormat(string)`, `timezone(string)` |
| `NumberColumn` | Numérico alineado a la derecha | `decimals(int)`, `prefix`, `suffix` |
| `IconColumn` | Icono único | `icon(string\|Closure)`, `color(string\|Closure)` |
| `ImageColumn` | Thumbnail | `disk(string)`, `circular(bool)`, `size(int)` |
| `RelationshipColumn` | Relación con eager-load | factory `make(name, relation, attribute)` |
| `ComputedColumn` | Derivado por Closure | factory `make(name, Closure)` |

**Setters comunes** (en todas): `label`, `sortable(bool)`, `searchable(bool)`, `hidden(bool)`, `hiddenOnMobile(bool)`, `align(string)`, `width(string)`, `tooltip(string\|Closure)`.

## Filters

`Arqel\Table\Filter` (abstract). Factory: `Filter::make($name)` (pero usa las clases concretas).

| Clase | Caso de uso | Setters extra |
|---|---|---|
| `SelectFilter` | Picker de un valor | `options(array\|Closure)` |
| `MultiSelectFilter` | Picker multi-valor | `options(array\|Closure)` |
| `DateRangeFilter` | rango start/end | (sin setters extra) |
| `TextFilter` | Búsqueda Like | `column(string)` |
| `TernaryFilter` | true/false/all | `trueLabel`, `falseLabel`, `allLabel` |
| `ScopeFilter` | scope Eloquent | factory `make($name, $scopeName)` |

**Setters comunes**: `label`, `apply(Closure)` (override de query), `default(mixed)`, `placeholder(string)`.

## `Arqel\Table\TableQueryBuilder` (final)

Orquesta request → query Eloquent.

| Método | Descripción |
|---|---|
| `for(Table, Builder, Request)` | Factory |
| `paginate(): LengthAwarePaginator` | Aplica search/filter/sort/eager-load + paginate |

Sort whitelisted contra columnas sortable. `per_page` validado contra `perPageOptions`. Eager loading inferido desde `RelationshipColumn`.

## Relacionado

- SKILL: [`packages/table/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/table/SKILL.md)
- Conceptos: [`/es/guide/tables-forms`](/es/guide/tables-forms)
- Siguiente: [`arqel-dev/form`](/es/reference/php/form)

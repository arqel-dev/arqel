# `arqel/table` — API Reference

Namespace `Arqel\Table\`. Builder fluente, 9 column types, 6 filter types, query builder.

## `Arqel\Table\Table` (final)

Builder principal.

| Método | Tipo | Descrição |
|---|---|---|
| `Table::make()` | `self` | Factory |
| `columns(array<Column>)` | `self` | Lista de columns |
| `filters(array<Filter>)` | `self` | Lista de filters |
| `actions(array<Action>)` | `self` | RowActions |
| `bulkActions(array<Action>)` | `self` | BulkActions (require `selectable`) |
| `toolbarActions(array<Action>)` | `self` | ToolbarActions |
| `defaultSort(string $column, string $direction='asc')` | `self` | |
| `perPage(int)` / `perPageOptions(array<int>)` | `self` | Paginação |
| `searchable(bool=true)` / `selectable(bool=true)` | `self` | |
| `striped(bool=true)` / `compact(bool=true)` | `self` | |
| `emptyState(array)` | `self` | `['icon', 'title', 'description']` |
| `toArray()` | `array` | Schema serializado para Inertia |

## Columns

`Arqel\Table\Column` (abstract). Factory: `Column::make($name)` retorna `TextColumn` por defeito.

| Class | Use case | Setters extra |
|---|---|---|
| `TextColumn` | String/text default | `limit(int)`, `wrap(bool)` |
| `BadgeColumn` | Status com cores | `colors(array)`, `icon(string)` |
| `BooleanColumn` | Checkmark | `trueIcon`, `falseIcon`, `trueColor`, `falseColor` |
| `DateColumn` | Datas formatadas | `displayFormat(string)`, `timezone(string)` |
| `NumberColumn` | Numérico right-align | `decimals(int)`, `prefix`, `suffix` |
| `IconColumn` | Single ícone | `icon(string\|Closure)`, `color(string\|Closure)` |
| `ImageColumn` | Thumbnail | `disk(string)`, `circular(bool)`, `size(int)` |
| `RelationshipColumn` | Eager-loaded relation | factory `make(name, relation, attribute)` |
| `ComputedColumn` | Closure derivada | factory `make(name, Closure)` |

**Setters comuns** (em todos): `label`, `sortable(bool)`, `searchable(bool)`, `hidden(bool)`, `hiddenOnMobile(bool)`, `align(string)`, `width(string)`, `tooltip(string\|Closure)`.

## Filters

`Arqel\Table\Filter` (abstract). Factory: `Filter::make($name)` (mas use as classes concretas).

| Class | Use case | Setters extra |
|---|---|---|
| `SelectFilter` | Single-value picker | `options(array\|Closure)` |
| `MultiSelectFilter` | Multi-value picker | `options(array\|Closure)` |
| `DateRangeFilter` | Range start/end | (sem setters extra) |
| `TextFilter` | Like search | `column(string)` |
| `TernaryFilter` | true/false/all | `trueLabel`, `falseLabel`, `allLabel` |
| `ScopeFilter` | Eloquent scope | factory `make($name, $scopeName)` |

**Setters comuns**: `label`, `apply(Closure)` (override de query), `default(mixed)`, `placeholder(string)`.

## `Arqel\Table\TableQueryBuilder` (final)

Orquestra request → Eloquent query.

| Método | Descrição |
|---|---|
| `for(Table, Builder, Request)` | Factory |
| `paginate(): LengthAwarePaginator` | Aplica search/filter/sort/eager-load + paginate |

Sort whitelisted contra columns sortable. `per_page` validado contra `perPageOptions`. Eager loading inferido de `RelationshipColumn`.

## Related

- SKILL: [`packages/table/SKILL.md`](https://github.com/arqel/arqel/blob/main/packages/table/SKILL.md)
- Conceitos: [`/guide/tables-forms`](/guide/tables-forms)
- Próximo: [`arqel/form`](/reference/php/form)

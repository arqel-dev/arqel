# `arqel-dev/table` — API Reference

Namespace `Arqel\Table\`. Fluent builder, 9 column types, 6 filter types, query builder.

## `Arqel\Table\Table` (final)

Main builder.

| Method | Type | Description |
|---|---|---|
| `Table::make()` | `self` | Factory |
| `columns(array<Column>)` | `self` | List of columns |
| `filters(array<Filter>)` | `self` | List of filters |
| `actions(array<Action>)` | `self` | RowActions |
| `bulkActions(array<Action>)` | `self` | BulkActions (require `selectable`) |
| `toolbarActions(array<Action>)` | `self` | ToolbarActions |
| `defaultSort(string $column, string $direction='asc')` | `self` | |
| `perPage(int)` / `perPageOptions(array<int>)` | `self` | Pagination |
| `searchable(bool=true)` / `selectable(bool=true)` | `self` | |
| `striped(bool=true)` / `compact(bool=true)` | `self` | |
| `emptyState(array)` | `self` | `['icon', 'title', 'description']` |
| `toArray()` | `array` | Schema serialized for Inertia |

## Columns

`Arqel\Table\Column` (abstract). Factory: `Column::make($name)` returns `TextColumn` by default.

| Class | Use case | Extra setters |
|---|---|---|
| `TextColumn` | Default String/text | `limit(int)`, `wrap(bool)` |
| `BadgeColumn` | Status with colors | `colors(array)`, `icon(string)` |
| `BooleanColumn` | Checkmark | `trueIcon`, `falseIcon`, `trueColor`, `falseColor` |
| `DateColumn` | Formatted dates | `displayFormat(string)`, `timezone(string)` |
| `NumberColumn` | Right-aligned numeric | `decimals(int)`, `prefix`, `suffix` |
| `IconColumn` | Single icon | `icon(string\|Closure)`, `color(string\|Closure)` |
| `ImageColumn` | Thumbnail | `disk(string)`, `circular(bool)`, `size(int)` |
| `RelationshipColumn` | Eager-loaded relation | factory `make(name, relation, attribute)` |
| `ComputedColumn` | Closure-derived | factory `make(name, Closure)` |

**Common setters** (on all): `label`, `sortable(bool)`, `searchable(bool)`, `hidden(bool)`, `hiddenOnMobile(bool)`, `align(string)`, `width(string)`, `tooltip(string\|Closure)`.

## Filters

`Arqel\Table\Filter` (abstract). Factory: `Filter::make($name)` (but use the concrete classes).

| Class | Use case | Extra setters |
|---|---|---|
| `SelectFilter` | Single-value picker | `options(array\|Closure)` |
| `MultiSelectFilter` | Multi-value picker | `options(array\|Closure)` |
| `DateRangeFilter` | start/end range | (no extra setters) |
| `TextFilter` | Like search | `column(string)` |
| `TernaryFilter` | true/false/all | `trueLabel`, `falseLabel`, `allLabel` |
| `ScopeFilter` | Eloquent scope | factory `make($name, $scopeName)` |

**Common setters**: `label`, `apply(Closure)` (query override), `default(mixed)`, `placeholder(string)`.

## `Arqel\Table\TableQueryBuilder` (final)

Orchestrates request → Eloquent query.

| Method | Description |
|---|---|
| `for(Table, Builder, Request)` | Factory |
| `paginate(): LengthAwarePaginator` | Applies search/filter/sort/eager-load + paginate |

Sort whitelisted against sortable columns. `per_page` validated against `perPageOptions`. Eager loading inferred from `RelationshipColumn`.

## Related

- SKILL: [`packages/table/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/table/SKILL.md)
- Concepts: [`/guide/tables-forms`](/guide/tables-forms)
- Next: [`arqel-dev/form`](/reference/php/form)

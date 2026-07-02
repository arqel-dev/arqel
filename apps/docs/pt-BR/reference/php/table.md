# `arqel-dev/table` — API Reference

Namespace `Arqel\Table\`. Builder fluente, 12 column types, 8 filter types, query builder.

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
| `BadgeColumn` | Status com cores | `colors(array)`, `icons(array)` |
| `BooleanColumn` | Checkmark | `trueIcon`, `falseIcon`, `trueColor`, `falseColor` |
| `DateColumn` | Datas formatadas | `date(string)`, `dateTime(string)`, `since()`, `timezone(string)` |
| `NumberColumn` | Numérico right-align | `decimals(int)`, `prefix`, `suffix` |
| `IconColumn` | Single ícone | `options(array)`, `size(string)` |
| `ImageColumn` | Thumbnail | `disk(string)`, `circular()`, `square()`, `size(int)` |
| `RelationshipColumn` | Eager-loaded relation | `make(name)` + `->display(attribute)` |
| `ComputedColumn` | Closure derivada | factory `make(name, Closure)` |
| `SelectColumn` | Célula select editável | `options(array\|Closure)`, `rules(array)`, `debounce(int)` |
| `TextInputColumn` | Célula text-input inline editável | `rules(array)`, `debounce(int)`, `readonly(bool)` |
| `ToggleColumn` | Célula toggle editável | `onValue(mixed)`, `offValue(mixed)`, `debounce(int)` |

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
| `QueryBuilderFilter` | Árvore visual de condições (grupos AND/OR) | `constraints(array<Constraint>)` |
| `TrashedFilter` | Soft-delete três estados (`without`/`with`/`only`) | (sem setters extra) |

**Setters comuns**: `label`, `apply(Closure)` (override de query), `default(mixed)`, `placeholder(string)`.

## `Arqel\Table\TableQueryBuilder` (final)

Orquestra request → Eloquent query.

| Método | Descrição |
|---|---|
| `for(Table, Builder, Request)` | Factory |
| `paginate(): LengthAwarePaginator` | Aplica search/filter/sort/eager-load + paginate |

Sort whitelisted contra columns sortable. `per_page` validado contra `perPageOptions`. Eager loading inferido de `RelationshipColumn`.

## Related

- SKILL: [`packages/table/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/table/SKILL.md)
- Conceitos: [`/pt-BR/guide/tables-forms`](/pt-BR/guide/tables-forms)
- Próximo: [`arqel-dev/form`](/pt-BR/reference/php/form)

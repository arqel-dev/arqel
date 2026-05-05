# Tables V2

> Package: [`arqel-dev/table`](../../packages/table/) · Tickets: TABLE-V2-002..010

## Purpose

Phase 2 extends Phase 1's `arqel-dev/table` with advanced capabilities: inline editing, visual query builder, granular column visibility control, grouping with summaries, drag-drop reordering, mobile mode, and multiple pagination types.

The whole API stays declarative — `Resource::table()` still returns an `Arqel\Table\Table` builder, and `arqel-dev/core` detects it via duck-typing in `InertiaDataBuilder::isTableObject`.

## Inline editing

3 editable column types:

- `TextInputColumn` — `type='textInput'`.
- `SelectColumn` — `type='select'`, `options(array|Closure)` lazy in `toArray()` (non-array Closure degrades to `[]`).
- `ToggleColumn` — `type='toggle'`, `onValue/offValue` to map boolean → arbitrary persisted value.

**Common contract**: `editable=true` by default (opt-out via `readonly()`); `debounce=500ms` default, `debounce(int)` clamps to `≥0`; `rules(array)` for server-side validation; `readonly(bool|Closure=true)` — bool flips `editable`, Closure resolved per-record server-side.

```php
use Arqel\Table\Columns\{SelectColumn, ToggleColumn};

SelectColumn::make('status')
    ->options(['draft' => 'Draft', 'published' => 'Published'])
    ->rules(['required', 'in:draft,published'])
    ->debounce(800);

ToggleColumn::make('is_active')
    ->onValue('active')
    ->offValue('inactive')
    ->readonly(fn ($record) => $record->locked_at !== null);
```

The `POST {panel}/{resource}/{id}/inline-update` endpoint is **deferred** — it depends on `arqel-dev/core` `ResourceRegistry::findBySlug` + Policy authorization.

## Visual Query Builder

`QueryBuilderFilter` + `Filters\Constraints\Constraint` enable nested filters with AND/OR and typed operators.

5 concrete constraints:

| Constraint | Operators |
|---|---|
| `TextConstraint` | equals, not_equals, contains, starts_with, ends_with |
| `NumberConstraint` | =, !=, >, <, >=, <=, between (cast int/float; non-numeric → `InvalidArgumentException`) |
| `DateConstraint` | =, before, after, between (`Carbon::parse`) |
| `BooleanConstraint` | is_true, is_false |
| `SelectConstraint` | equals, not_equals, in, not_in (`whereIn`/`whereNotIn` silently ignores non-arrays) |

```php
use Arqel\Table\Filters\QueryBuilderFilter;
use Arqel\Table\Filters\Constraints\{TextConstraint, NumberConstraint, DateConstraint};

QueryBuilderFilter::make('advanced')->constraints([
    new TextConstraint('title'),
    new NumberConstraint('price'),
    new DateConstraint('published_at'),
]);
```

**Security guarantee**: every lookup goes through `findConstraint($field)` against a declared whitelist. Unknown field or operator outside the list are silently dropped — there is no path from arbitrary user input to a SQL column name.

## Column visibility

3 fluent flags on the `Column` base:

- `togglable(bool=true)` — adds to the visibility dropdown.
- `hiddenByDefault(bool=true)` — column hidden on first render (auto-enables `togglable`; later `togglable(false)` wins).
- `hiddenOnMobile(bool=true)` — hidden on mobile viewport.

Getters `isTogglable/isHiddenByDefault/isHiddenOnMobile` exposed in the Inertia payload. Per-user cross-package persistence (`POST /admin/user-settings/tables/{resource}`) ships on the React side.

## Grouping with summaries

```php
use Arqel\Table\Summaries\Summary;

Table::make()
    ->groupBy('category', fn ($record) => $record->category->name)
    ->groupSummaries([
        Summary::sum('price'),
        Summary::count(),
    ]);
```

5 final summary types (`SumSummary`, `AvgSummary` (skips nulls), `CountSummary` (optional field), `MinSummary`, `MaxSummary`). Static facade `Summary::sum/avg/count/min/max($field)`.

`buildGroups(Collection)` returns `array<{label, key, records, summaries}>` — without `groupBy` it returns a single group `'All'`.

## Reorderable

```php
Table::make()->reorderable('position');  // null disables
```

Getters `getReorderColumn(): ?string`, `isReorderable(): bool`. UI DnD-kit + auto-scroll + rollback ship on the React side. Rule: **block reorder when sort != reorder column**.

## Mobile mode

```php
Table::make()->mobileMode(Table::MOBILE_MODE_STACKED);  // or MOBILE_MODE_SCROLL
```

Default `'stacked'`. Unknown value silently falls back to the default (a typo must not crash the Inertia render).

## Pagination types

4 constants:

| Constant | String | Semantics |
|---|---|---|
| `PAGINATION_LENGTH_AWARE` | `'lengthAware'` (default) | classic paginator with page numbers + total |
| `PAGINATION_SIMPLE` | `'simple'` | only prev/next |
| `PAGINATION_CURSOR` | `'cursor'` | cursor-based (recommended on large datasets or unstable ordering) |
| `PAGINATION_INFINITE` | `'infinite'` | flag for React to use Inertia 3 `merge` on scroll |

```php
Table::make()->paginationType(Table::PAGINATION_CURSOR);
```

The Inertia 3 merge React side is **deferred** to `TABLE-JS-XXX`: `IntersectionObserver` on the last row + `router.reload({ only: ['records'], merge: ['records.data'], data: { page: currentPage + 1 } })`.

## FAQ

**How do I authorize inline edit per record?**
`->readonly(fn ($record) => !auth()->user()->can('update', $record))`. The server is the source of truth.

**Can I nest Query Builder groups?**
Yes — the payload accepts `operator: 'AND'|'OR'` and nested groups. `applyConditions` is recursive.

**Does reorder respect the current filters?**
The reorder operation runs against the visible subset, but the rule **block reorder when sort != reorder column** lives on the React side. Cross-package coordination.

## Anti-patterns

- ❌ Query logic in Column — eager loading via `RelationshipColumn`/`indexQuery`, never in `formatState`.
- ❌ Per-row action authorization on the client — the source of truth is the server (`canBeExecutedBy`).
- ❌ Bulk action without `chunkSize` when the operation is heavy (default 100).
- ❌ Custom constraint that accepts arbitrary `field` from the payload — always validate against a whitelist.

## Related

- [`packages/table/SKILL.md`](../../packages/table/SKILL.md)
- [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §TABLE-V2-002..010

# SKILL.md — arqel-dev/table

> Contexto canónico para AI agents a trabalhar no pacote `arqel-dev/table`.

## Purpose

`arqel-dev/table` constrói tabelas declarativas para Resources Arqel — sorting, filtering, search, pagination, ações row/bulk/toolbar, edição inline, agrupamento, visual query builder, reorder e mobile mode. Recebe Columns + Filters declarados em PHP, aplica-os a uma query Eloquent via `TableQueryBuilder`, e expõe o schema serializado para o `<DataTable>` em `@arqel-dev/ui`.

`Resource::table()` é detectado por duck-typing em `Arqel\Core\Support\InertiaDataBuilder::isTableObject` (presença de `getColumns/getFilters/getActions/getBulkActions/getToolbarActions`). Quando presente, `buildTableIndexData` carrega via Reflection o `TableQueryBuilder` para paginar.

## Status

**Base (TABLE-001..008):**

- `Arqel\Table\Table` (final) builder: `make/columns/filters/actions/bulkActions/toolbarActions/defaultSort/perPage/perPageOptions/searchable/selectable/striped/compact/emptyState/toArray`. Action arrays são `array<int, mixed>` (duck-typed contra `arqel-dev/actions` para evitar circular dep — ambos dependem só de `arqel-dev/core`).
- **9 Column types** em `src/Columns/`: `TextColumn` (limit/wrap), `BadgeColumn` (`colors()` value→token + `icons()` value→lucide name; o `BadgeCell` em `@arqel-dev/ui` mapeia o token de cor para classes Tailwind e renderiza o ícone — valor sem match cai no muted), `BooleanColumn`, `DateColumn` (displayFormat/timezone), `NumberColumn` (decimals/prefix/suffix), `IconColumn`, `ImageColumn` (disk/circular/size), `RelationshipColumn` (`make(name, relation, attribute)`), `ComputedColumn` (`make(name, Closure)`). Setters comuns: `label/sortable/searchable/hidden/hiddenOnMobile/align/width/tooltip`.
- **6 Filter types** em `src/Filters/`: `SelectFilter`, `MultiSelectFilter`, `DateRangeFilter`, `TextFilter`, `TernaryFilter`, `ScopeFilter`. Setters comuns: `label/apply(Closure)/default/placeholder`.
- `TableQueryBuilder` (final): search global cross-column, filter application, sort whitelisted, eager-load inferido de `RelationshipColumn`, paginate sanitizado contra `perPageOptions`. Factory `for(Table, Builder, Request)::paginate()`. **Nota:** `RelationshipColumn` é excluída das whitelists de sort/search — ordenar/buscar por relação exige um JOIN ainda não implementado (deferido para TABLE-005), então `->sortable()`/`->searchable()` numa relação degrada para no-op em vez de emitir SQL inválido (#141).
- **Per-row authz** (TABLE-007): `arqel-dev/core` `InertiaDataBuilder::resolveVisibleActionNames` injeta `record.arqel.actions: ['view', 'edit']`; React filtra a lista global pelo nome. Avalia `Action::isVisibleFor` + `Action::canBeExecutedBy` duck-typed.
- **Bulk actions endpoint** (TABLE-008): `POST {panel}/{resource}/bulk-actions/{action}` em `arqel-dev/actions`, fetcha via `whereIn(getKeyName, ids)`, delega para `BulkAction::execute(Collection)` que chunka via `chunkSize(int)` (default 100, clamp ≥ 1). `deselectRecordsAfterCompletion(bool)` controla UX pós-execução.

**Inline editing (TABLE-V2-002):**

- 3 editable column types: `TextInputColumn` (`type='textInput'`), `SelectColumn` (`type='select'`, `options(array|Closure)` lazy em `toArray()` — Closure não-array degrada para `[]`), `ToggleColumn` (`type='toggle'`, `onValue/offValue` para mapear boolean → valor persistido arbitrário).
- **Contrato comum**: `editable=true` por default (opt-out via `readonly()`); `debounce=500ms` default, `debounce(int)` clampa em `≥0`; `rules(array)` para validation server-side; `readonly(bool|Closure=true)` — bool flipa `editable`, Closure resolvida per-record server-side. `toArray()` mescla `{editable, debounce, rules}` (+ extras por tipo).

**Visual Query Builder (TABLE-V2-003):**

- `Filters\Constraints\Constraint` (abstract): construtor `(string $field)`, `label/operators` setters, getters `getField/getLabel/getType/getOperators` (label fallback via `Str::headline`). Subclasses declaram `protected string $type` + `getDefaultOperators()` + `apply(Builder, string $operator, mixed $value, string $method='where')`.
- 5 concrete (final): `TextConstraint` (equals/not_equals/contains/starts_with/ends_with), `NumberConstraint` (=,!=,>,<,>=,<=,between — cast int/float defensivo, valor não-numérico → `InvalidArgumentException`), `DateConstraint` (=/before/after/between via `Carbon::parse`), `BooleanConstraint` (is_true/is_false), `SelectConstraint` (equals/not_equals/in/not_in — `whereIn`/`whereNotIn` ignora silenciosamente não-arrays).
- `Filters\QueryBuilderFilter` (final): `type='queryBuilder'`, `constraints(array)` filtra não-`Constraint`, `applyToQuery` envolve em `where(Closure)` e delega recursivo a `applyConditions`. Suporta `operator: 'AND'|'OR'` no payload e em groups aninhados.
- **Security guarantee**: cada lookup vai por `findConstraint($field)` contra whitelist declarado. Field desconhecido ou operator fora da lista são silenciosamente descartados — não há caminho de input arbitrário do usuário para nome de coluna SQL.

**Column visibility (TABLE-V2-004):**

- 3 flags fluentes na base `Column`: `togglable(bool=true)`, `hiddenByDefault(bool=true)` (auto-enables `togglable` quando `true`; `togglable(false)` posterior wins), `hiddenOnMobile(bool=true)`.
- Getters `isTogglable/isHiddenByDefault/isHiddenOnMobile`. `toArray()` expõe as 3 chaves no payload Inertia.

**Grouping (TABLE-V2-005):**

- `Summaries\Summary` (abstract): construtor `(?string $field, ?string $label)`, setters `field/label`, subclasses declaram `protected string $type` + `compute(Collection): mixed`. `toArray()` emite `{type, field, label}`. Static facade `Summary::sum/avg/count/min/max($field)`.
- 5 concretes finais em `src/Summaries/`: `SumSummary` ("Total"), `AvgSummary` ("Average", skipa nulls), `CountSummary` ("Count", field opcional), `MinSummary`, `MaxSummary`.
- `Table::groupBy(string $field, ?Closure $labelResolver=null)` + `Table::groupSummaries(array)` (filtra não-`Summary`). `buildGroups(Collection)` devolve `array<{label, key, records, summaries}>` — sem `groupBy` retorna grupo único `'All'`. `toArray()` mescla `{groupBy, summaries}` (groups computados em render time).

**Reorderable (TABLE-V2-006):**

- `Table::reorderable(?string $columnName='position')` — `null` desabilita. Getters `getReorderColumn(): ?string`, `isReorderable(): bool`. `toArray()` mescla chave `reorderable`.

**Mobile mode (TABLE-V2-007):**

- `Table::mobileMode(string)` + 2 constantes: `MOBILE_MODE_STACKED='stacked'` (default) e `MOBILE_MODE_SCROLL='scroll'`. Valor desconhecido cai silenciosamente para `'stacked'` (typo não deve crashar Inertia render). `toArray()` mescla `config.mobileMode`.

**Entregue (TABLE-V2-008 — PHP slice):**

- `Table::paginationType(string)` + 4 constantes: `PAGINATION_LENGTH_AWARE` (`'lengthAware'`, default), `PAGINATION_SIMPLE` (`'simple'`), `PAGINATION_CURSOR` (`'cursor'`), `PAGINATION_INFINITE` (`'infinite'`). Valor desconhecido cai silenciosamente para o default (typo não deve crashar Inertia render). Getter `getPaginationType(): string`. `toArray()` mescla `config.paginationType`.
- **Semântica**: `lengthAware` ativa o paginator clássico com page numbers + total; `simple` expõe apenas prev/next; `cursor` troca para cursor-based navigation (recomendado em datasets grandes ou ordering instável); `infinite` flagga o React layer para usar Inertia 3 `merge` em scroll.
- Inertia 3 merge React side **deferido** para `TABLE-JS-XXX`: `IntersectionObserver` no último row + `router.reload({ only: ['records'], merge: ['records.data'], data: { page: currentPage + 1 } })`, loading indicator durante fetch, "No more results" no fim, dedupe contra duplicate rows, integração com filters aplicados.

**Coverage:** ~150 testes Pest passando (Base 117 + V2-002..008 ≈33: 9 visibility + 16 grouping + 6 reorderable + 7 mobile + 9 pagination type + outros).

**Por chegar (cross-package + JS):**

- `POST {panel}/{resource}/{id}/inline-update` controller (TABLE-V2-002 — depende de `arqel-dev/core` `ResourceRegistry::findBySlug` + Policy authorization).
- React inline-cell components, query-builder tree UI (drag-drop groups + value pickers polimórficos), column-visibility dropdown + persistência cross-package (`POST /admin/user-settings/tables/{resource}`), grouping sticky headers + summary rows render, reorder DnD-kit + auto-scroll + rollback (regra: bloquear reorder quando sort != reorder column), mobile stacked-cards render via `useBreakpoint`.
- Concurrency optimistic via version column (Phase 3).
- Adiados Phase 2: TABLE-009..013 (advanced filters: relationship-based, range numeric, computed) e persistência per-user de preferências (column visibility + sort default).

## Conventions

- `declare(strict_types=1)` obrigatório; classes `final` (Columns, Filters, Constraints, Summaries, Table, TableQueryBuilder).
- Action arrays são `array<int, mixed>` — `arqel-dev/table` não declara dep em `arqel-dev/actions` (circular path-repo).
- Eager loading inferido de `RelationshipColumn`, não de `BelongsToField` (essa coordenação fica em `EagerLoadingResolver` de `arqel-dev/fields` no contexto de form).
- Sort whitelisted: `?sort=anything` só funciona se a column declarou `->sortable()`.
- Visual Query Builder: field/operator whitelist é fonte da verdade — input desconhecido é descartado, não rejeitado com erro.
- Mobile mode / pagination type: valores inválidos degradam para default, nunca lançam.

## Anti-patterns

- Lógica de query no Column — eager loading via `RelationshipColumn`/`indexQuery`, nunca em `formatState`.
- Side-effects em columns (logging, eventos) — Columns são definição declarativa.
- `Column::make('x')->sortable(false)` — basta omitir `sortable()` (default false).
- Per-row action authorization no client — fonte da verdade é o servidor (`canBeExecutedBy`); React só filtra pelo `record.arqel.actions`.
- Bulk action sem `chunkSize` quando a operação é pesada — default 100 cobre maioria; ajuste explícito quando o callback faz I/O por record.
- Constraint custom que aceita `field` arbitrário do payload — sempre validar via `findConstraint($field)` contra o whitelist declarado.

## Examples

Tabela base com columns, filters e actions:

```php
use Arqel\Table\Table;
use Arqel\Table\Columns\{TextColumn, BadgeColumn, DateColumn, RelationshipColumn};
use Arqel\Table\Filters\{SelectFilter, DateRangeFilter, TernaryFilter};
use Arqel\Actions\Actions;

return Table::make()
    ->columns([
        TextColumn::make('title')->sortable()->searchable()->limit(60),
        BadgeColumn::make('status')->colors(['draft' => 'gray', 'published' => 'green']),
        DateColumn::make('published_at')->displayFormat('d/m/Y H:i')->sortable(),
        RelationshipColumn::make('author', 'user', 'name'),
    ])
    ->filters([
        SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published']),
        DateRangeFilter::make('created_at'),
        TernaryFilter::make('is_featured'),
    ])
    ->defaultSort('created_at', 'desc')
    ->perPage(25)
    ->searchable()
    ->selectable()
    ->actions([Actions::edit(), Actions::delete()])
    ->bulkActions([Actions::deleteBulk()]);
```

Inline editing com SelectColumn e ToggleColumn:

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

Visual Query Builder + grouping com summaries:

```php
use Arqel\Table\Filters\QueryBuilderFilter;
use Arqel\Table\Filters\Constraints\{TextConstraint, NumberConstraint, DateConstraint};
use Arqel\Table\Summaries\Summary;

Table::make()
    ->filters([
        QueryBuilderFilter::make('advanced')->constraints([
            new TextConstraint('title'),
            new NumberConstraint('price'),
            new DateConstraint('published_at'),
        ]),
    ])
    ->groupBy('category', fn ($record) => $record->category->name)
    ->groupSummaries([
        Summary::sum('price'),
        Summary::count(),
    ]);
```

## Related

- Source: [`packages/table/src/`](./src/)
- Testes: [`packages/table/tests/`](./tests/)
- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §TABLE-001..008 + [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §TABLE-V2-002..010
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §Table
- Per-row impl: [`packages/core/src/Support/InertiaDataBuilder.php`](../../packages/core/src/Support/InertiaDataBuilder.php) (`resolveVisibleActionNames`)
- Bulk impl: [`packages/actions/src/Http/Controllers/ActionController.php`](../../packages/actions/src/Http/Controllers/ActionController.php) (`invokeBulk`)
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3
  - [ADR-017](../../PLANNING/03-adrs.md) — Authorization UX-only no client

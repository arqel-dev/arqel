# SKILL.md — arqel/table

> Contexto canónico para AI agents a trabalhar no pacote `arqel/table`.

## Purpose

`arqel/table` constrói tabelas declarativas para Resources Arqel — sorting, filtering, search, pagination, ações row/bulk/toolbar. Recebe Columns + Filters declarados em PHP, aplica-os a uma query Eloquent via `TableQueryBuilder`, e expõe o schema serializado para o `<DataTable>` em `@arqel/ui`.

## Status

**Entregue (TABLE-001..008):**

- **`Arqel\Table\Table`** (final) builder com `make()`, `columns(array)`, `filters(array)`, `actions(array)`, `bulkActions(array)`, `toolbarActions(array)`, `defaultSort(col, dir)`, `perPage(int)`, `perPageOptions(array<int>)`, `searchable(bool)`, `selectable(bool)`, `striped(bool)`, `compact(bool)`, `emptyState(array)`, `toArray()`. Action arrays são `array<int, mixed>` (duck-typed contra `arqel/actions` para não criar circular dep)
- **9 Column types** em `src/Columns/` extendendo `Arqel\Table\Column`: `TextColumn` (limit/wrap), `BadgeColumn` (colors map), `BooleanColumn` (true/false icon+color), `DateColumn` (displayFormat/timezone), `NumberColumn` (decimals/prefix/suffix), `IconColumn` (icon|Closure + color|Closure), `ImageColumn` (disk/circular/size), `RelationshipColumn` (factory `make(name, relation, attribute)`), `ComputedColumn` (factory `make(name, Closure)`). Setters comuns: `label`, `sortable`, `searchable`, `hidden`, `hiddenOnMobile`, `align`, `width`, `tooltip`
- **6 Filter types** em `src/Filters/` extendendo `Arqel\Table\Filter`: `SelectFilter` (options array|Closure), `MultiSelectFilter`, `DateRangeFilter`, `TextFilter` (column override), `TernaryFilter` (true/false/all labels), `ScopeFilter` (factory `make($name, $scopeName)`). Setters comuns: `label`, `apply(Closure)`, `default`, `placeholder`
- **`Arqel\Table\TableQueryBuilder`** (final) orquestra request → Eloquent query: search global cross-column, filter application, sort whitelisted contra columns sortable, eager-load inferido de `RelationshipColumn`, paginate sanitizado contra `perPageOptions`. Factory `for(Table, Builder, Request)` + `paginate(): LengthAwarePaginator`
- **Per-row authorization de actions** (TABLE-007) — implementado em `arqel/core` (`InertiaDataBuilder::resolveVisibleActionNames`): cada record carrega `arqel.actions: ['view', 'edit']` (lista de **nomes** das row actions visíveis para `(user, record)`); o React filtra a lista global pelo nome. Avalia `Action::isVisibleFor($record)` + `Action::canBeExecutedBy($user, $record)` duck-typed
- **Bulk actions endpoint** (TABLE-008) — `POST {panel}/{resource}/bulk-actions/{action}` em `arqel/actions`, recebe `ids[]`, fetcha records via `whereIn(getKeyName, ids)`, delega para `BulkAction::execute(Collection)` que **chunka automaticamente** via `chunkSize(int)` (default 100, clamp ≥ 1) — chama callback uma vez por chunk. `deselectRecordsAfterCompletion(bool)` controla UX pós-execução

**Entregue (TABLE-V2-002 — PHP slice):**

- **3 novos editable column types** em `src/Columns/` extendendo `Arqel\Table\Column`:
  - `TextInputColumn` (final) — `type='textInput'`, edição inline via input de texto
  - `SelectColumn` (final) — `type='select'`, com `options(array|Closure)` resolvido **lazy** em `toArray()` (Closure que retorna não-array degrada para `[]`)
  - `ToggleColumn` (final) — `type='toggle'`, com `onValue(mixed)` / `offValue(mixed)` para mapear o boolean da UI a valores persistidos arbitrários (e.g. `'active'` / `'inactive'`)
- **Contrato comum de editable** (em todas as 3):
  - `editable = true` por **default** — opt-out via `readonly()`
  - `debounce = 500ms` por default; `debounce(int)` clampa em `≥ 0` (ms negativos viram 0)
  - `rules(array)` — validation rules estilo Laravel persistidas para o controller server-side resolver
  - `readonly(bool|Closure = true)` — bool flipa `editable` imediatamente; Closure é armazenada para resolução per-record server-side (não flipa `editable` eagerly)
  - `toArray()` mescla `{editable, debounce, rules}` (e options/onValue/offValue conforme o tipo)
- Getters: `isEditable()`, `getDebounce()`, `getRules()`, `getReadonly()` (+ `resolveOptions()` no `SelectColumn` e `getOnValue/getOffValue` no `ToggleColumn`)

**Entregue (TABLE-V2-003 — PHP slice):**

- **`Arqel\Table\Filters\Constraints\Constraint`** (abstract) — base de constraint do Visual Query Builder. Construtor `(string $field)` (final), setter fluente `label(string)` + `operators(array)`, getters `getField/getLabel/getType/getOperators` (label fallback via `Str::headline`). Subclasses declaram `protected string $type` + implementam `getDefaultOperators(): array<int, string>` e `apply(Builder $query, string $operator, mixed $value, string $method = 'where'): void`. `toArray()` serializa `{field, label, type, operators}` para o React.
- **5 concrete constraints** em `src/Filters/Constraints/` (todas `final`):
  - `TextConstraint` — `type='text'`, operators `equals/not_equals/contains/starts_with/ends_with` traduzidos para `=`, `!=`, `LIKE %v%`, `LIKE v%`, `LIKE %v`
  - `NumberConstraint` — `type='number'`, operators `=,!=,>,<,>=,<=,between`. Cast numérico defensivo (int quando possível, senão float); valor não-numérico → `InvalidArgumentException`. `between` espera `[min, max]` (usa `whereBetween`/`orWhereBetween`)
  - `DateConstraint` — `type='date'`, operators `=,before,after,between`. Parse via `Carbon::parse` (data inválida → `InvalidArgumentException`). `between` usa `whereBetween` com `[from, to]`
  - `BooleanConstraint` — `type='boolean'`, operators `is_true,is_false` → `where(field, true|false)`
  - `SelectConstraint` — `type='select'`, operators `equals,not_equals,in,not_in`. `options(array|Closure)` resolvido lazy em `toArray()` (Closure não-array degrada para `[]`). `in`/`not_in` usam `whereIn`/`whereNotIn` e ignoram silenciosamente valores não-array
- **`Arqel\Table\Filters\QueryBuilderFilter`** (`final extends Filter`) — `type='queryBuilder'`. `constraints(array)` filtra silenciosamente entradas não-`Constraint`. `applyToQuery()` valida que o valor é um array com `conditions` não-vazio, envolve tudo em `$query->where(Closure)`, e delega para `applyConditions()` recursivo. Suporta `operator: 'AND'|'OR'` no nível do payload e em cada `group` aninhado.
- **Security guarantee crítica**: cada `condition` lookup vai por `findConstraint($field)` contra o whitelist declarado. **Field desconhecido é silenciosamente descartado** — não há caminho de input arbitrário do usuário para nome de coluna SQL. Operators fora da lista declarada na constraint também são descartados.
- React tree UI (drag-drop de groups, field/operator/value pickers polimórficos, save/load saved queries) **diferida para TABLE-JS-XXX** — é UI-only sobre o payload acima.

**Diferido para tickets follow-up cross-package:**

- **`POST {panel}/{resource}/{id}/inline-update` controller** — depende de `arqel/core` para policy authorization (`Gate::authorize('update', $record)`) + `ResourceRegistry::findBySlug()`; rota e validation pipeline ficam em `arqel/core` ou `arqel/actions`, não no `arqel/table` para evitar dep circular
- **React inline-cell components** (`@arqel/ui` + `@arqel/react`) — double-click para editar, debounce save, `useOptimistic` (React 19.2) com rollback, Escape cancela, Tab avança, validation errors inline
- Concurrency optimistic via version column (Phase 3)

**Adiados:**

- TABLE-009..013 (advanced filters: relationship-based, range numeric, computed) — Phase 2
- Persistência de preferências de tabela per-user (column visibility, sort default) — Phase 2

## Key Contracts

```php
use Arqel\Table\Table;
use Arqel\Table\Columns\{TextColumn, BadgeColumn, DateColumn, RelationshipColumn};
use Arqel\Table\Filters\{SelectFilter, DateRangeFilter, TernaryFilter};
use Arqel\Actions\Actions;

public function table(): Table
{
    return Table::make()
        ->columns([
            TextColumn::make('title')->sortable()->searchable()->limit(60),
            BadgeColumn::make('status')->colors([
                'draft' => 'gray',
                'published' => 'green',
            ]),
            DateColumn::make('published_at')->displayFormat('d/m/Y H:i')->sortable(),
            RelationshipColumn::make('author', 'user', 'name')->label('Author'),
        ])
        ->filters([
            SelectFilter::make('status')->options([
                'draft' => 'Draft',
                'published' => 'Published',
            ]),
            DateRangeFilter::make('created_at'),
            TernaryFilter::make('is_featured'),
        ])
        ->defaultSort('created_at', 'desc')
        ->perPage(25)
        ->searchable()
        ->selectable()
        ->actions([Actions::edit(), Actions::delete()])
        ->bulkActions([Actions::deleteBulk()])
        ->toolbarActions([Actions::create()]);
}
```

`Resource::table()` é detectado por duck-typing em `Arqel\Core\Support\InertiaDataBuilder::isTableObject` (presença de `getColumns/getFilters/getActions/getBulkActions/getToolbarActions`). Quando presente, o `buildTableIndexData` carrega via Reflection o `TableQueryBuilder` para paginar.

## Per-row authorization

Cada record do payload index carrega:

```jsonc
{
  "id": 42,
  "title": "Hello",
  "arqel": {
    "title": "Hello",
    "subtitle": "Daisy",
    "actions": ["view", "edit"]  // nomes visíveis para este record
  }
}
```

`<DataTable>` filtra a lista global de `actions.row` pelo nome contra `record.arqel.actions`. Authorization que falha → action não renderiza para aquele row.

## Bulk pipeline

```
POST /admin/posts/bulk-actions/publish_all
body: { ids: [1, 2, 3, ..., 250] }

ActionController::invokeBulk
  -> resolve Resource by slug
  -> resolve action by name in bulkActions()
  -> Model::whereIn(key, ids).get()  // fetch all once
  -> Action::canBeExecutedBy($user, $records)
  -> BulkAction::execute(Collection)
       -> Collection::chunk(chunkSize)  // default 100
       -> for each chunk: parent::execute($chunk, $data)
  -> redirect back with success/error flash
```

## Conventions

- `declare(strict_types=1)` obrigatório
- Classes `final` (Columns, Filters, Table, TableQueryBuilder)
- **Action arrays são `array<int, mixed>`** — `arqel/table` não declara dep em `arqel/actions` para evitar circular path-repo (ambos dependem de `arqel/core`)
- Eager loading **inferido de `RelationshipColumn`**, não de `BelongsToField` (essa coordenação fica no `EagerLoadingResolver` de `arqel/fields` no contexto de form)
- Sort whitelisted — passar `?sort=anything` na query string só funciona se a column declarou `->sortable()`

## Anti-patterns

- ❌ **Lógica de query no Column** — eager loading via `RelationshipColumn`/`indexQuery`, nunca no `formatState`
- ❌ **Side-effects em columns** (logging, eventos) — Columns são definição declarativa
- ❌ **`Column::make('x')->sortable(false)`** — basta omitir `sortable()` (default false)
- ❌ **Per-row action authorization no client** — fonte da verdade é o servidor (`canBeExecutedBy` na Action). React só filtra pelo `record.arqel.actions`
- ❌ **Bulk action sem `chunkSize`** quando a operação é pesada — default 100 cobre maioria; ajuste explícito quando o callback faz I/O por record

## Related

- Source: [`packages/table/src/`](./src/)
- Testes: [`packages/table/tests/`](./tests/) (117 testes Pest passando)
- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §TABLE-001..008
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §Table
- Per-row impl: [`packages/core/src/Support/InertiaDataBuilder.php`](../../packages/core/src/Support/InertiaDataBuilder.php) (`resolveVisibleActionNames`)
- Bulk impl: [`packages/actions/src/Http/Controllers/ActionController.php`](../../packages/actions/src/Http/Controllers/ActionController.php) (`invokeBulk`)
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3
  - [ADR-017](../../PLANNING/03-adrs.md) — Authorization UX-only no client

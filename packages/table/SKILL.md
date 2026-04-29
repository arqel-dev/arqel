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
- Testes: [`packages/table/tests/`](./tests/) (56 testes Pest passando)
- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §TABLE-001..008
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §Table
- Per-row impl: [`packages/core/src/Support/InertiaDataBuilder.php`](../../packages/core/src/Support/InertiaDataBuilder.php) (`resolveVisibleActionNames`)
- Bulk impl: [`packages/actions/src/Http/Controllers/ActionController.php`](../../packages/actions/src/Http/Controllers/ActionController.php) (`invokeBulk`)
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3
  - [ADR-017](../../PLANNING/03-adrs.md) — Authorization UX-only no client

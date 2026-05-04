# Tables V2

> Pacote: [`arqel-dev/table`](../../packages/table/) · Tickets: TABLE-V2-002..010

## Purpose

Phase 2 estende o `arqel-dev/table` da Phase 1 com capacidades avançadas: edição inline, query builder visual, controle granular de visibilidade de colunas, agrupamento com summaries, reordenação drag-drop, mobile mode e múltiplos tipos de paginação.

Toda a API mantém-se declarativa — `Resource::table()` continua devolvendo um `Arqel\Table\Table` builder, e `arqel-dev/core` detecta-o por duck-typing em `InertiaDataBuilder::isTableObject`.

## Inline editing

3 column types editáveis:

- `TextInputColumn` — `type='textInput'`.
- `SelectColumn` — `type='select'`, `options(array|Closure)` lazy em `toArray()` (Closure não-array degrada para `[]`).
- `ToggleColumn` — `type='toggle'`, `onValue/offValue` para mapear boolean → valor persistido arbitrário.

**Contrato comum**: `editable=true` por default (opt-out via `readonly()`); `debounce=500ms` default, `debounce(int)` clampa em `≥0`; `rules(array)` para validation server-side; `readonly(bool|Closure=true)` — bool flipa `editable`, Closure resolvida per-record server-side.

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

Endpoint `POST {panel}/{resource}/{id}/inline-update` está **deferred** — depende de `arqel-dev/core` `ResourceRegistry::findBySlug` + Policy authorization.

## Visual Query Builder

`QueryBuilderFilter` + `Filters\Constraints\Constraint` permitem filtros aninhados com AND/OR e operators tipados.

5 constraints concretos:

| Constraint | Operators |
|---|---|
| `TextConstraint` | equals, not_equals, contains, starts_with, ends_with |
| `NumberConstraint` | =, !=, >, <, >=, <=, between (cast int/float; não-numérico → `InvalidArgumentException`) |
| `DateConstraint` | =, before, after, between (`Carbon::parse`) |
| `BooleanConstraint` | is_true, is_false |
| `SelectConstraint` | equals, not_equals, in, not_in (`whereIn`/`whereNotIn` ignora silenciosamente não-arrays) |

```php
use Arqel\Table\Filters\QueryBuilderFilter;
use Arqel\Table\Filters\Constraints\{TextConstraint, NumberConstraint, DateConstraint};

QueryBuilderFilter::make('advanced')->constraints([
    new TextConstraint('title'),
    new NumberConstraint('price'),
    new DateConstraint('published_at'),
]);
```

**Security guarantee**: cada lookup vai por `findConstraint($field)` contra whitelist declarado. Field desconhecido ou operator fora da lista são silenciosamente descartados — não há caminho de input arbitrário do usuário para nome de coluna SQL.

## Column visibility

3 flags fluentes na base `Column`:

- `togglable(bool=true)` — adiciona ao dropdown de visibilidade.
- `hiddenByDefault(bool=true)` — coluna oculta no primeiro render (auto-enables `togglable`; `togglable(false)` posterior wins).
- `hiddenOnMobile(bool=true)` — escondida em viewport mobile.

Getters `isTogglable/isHiddenByDefault/isHiddenOnMobile` expostos no payload Inertia. Persistência cross-package per-user (`POST /admin/user-settings/tables/{resource}`) entrega no React side.

## Grouping com summaries

```php
use Arqel\Table\Summaries\Summary;

Table::make()
    ->groupBy('category', fn ($record) => $record->category->name)
    ->groupSummaries([
        Summary::sum('price'),
        Summary::count(),
    ]);
```

5 summary types finais (`SumSummary`, `AvgSummary` (skipa nulls), `CountSummary` (field opcional), `MinSummary`, `MaxSummary`). Static facade `Summary::sum/avg/count/min/max($field)`.

`buildGroups(Collection)` devolve `array<{label, key, records, summaries}>` — sem `groupBy` retorna grupo único `'All'`.

## Reorderable

```php
Table::make()->reorderable('position');  // null desabilita
```

Getters `getReorderColumn(): ?string`, `isReorderable(): bool`. UI DnD-kit + auto-scroll + rollback entrega no React side. Regra: **bloquear reorder quando sort != reorder column**.

## Mobile mode

```php
Table::make()->mobileMode(Table::MOBILE_MODE_STACKED);  // ou MOBILE_MODE_SCROLL
```

Default `'stacked'`. Valor desconhecido cai silenciosamente para o default (typo não deve crashar Inertia render).

## Pagination types

4 constantes:

| Constante | String | Semântica |
|---|---|---|
| `PAGINATION_LENGTH_AWARE` | `'lengthAware'` (default) | paginator clássico com page numbers + total |
| `PAGINATION_SIMPLE` | `'simple'` | apenas prev/next |
| `PAGINATION_CURSOR` | `'cursor'` | cursor-based (recomendado em datasets grandes ou ordering instável) |
| `PAGINATION_INFINITE` | `'infinite'` | flag para React usar Inertia 3 `merge` em scroll |

```php
Table::make()->paginationType(Table::PAGINATION_CURSOR);
```

Inertia 3 merge React side está **deferido** para `TABLE-JS-XXX`: `IntersectionObserver` no último row + `router.reload({ only: ['records'], merge: ['records.data'], data: { page: currentPage + 1 } })`.

## FAQ

**Como autorizo edição inline por record?**
`->readonly(fn ($record) => !auth()->user()->can('update', $record))`. Server é fonte da verdade.

**Posso aninhar Query Builder groups?**
Sim — payload aceita `operator: 'AND'|'OR'` e groups aninhados. `applyConditions` recursivo.

**Reorder respeita filters atuais?**
A operação de reorder roda contra o subset visível, mas a regra **bloquear reorder quando sort != reorder column** vive no React. Coordenação cross-package.

## Anti-patterns

- ❌ Lógica de query no Column — eager loading via `RelationshipColumn`/`indexQuery`, nunca em `formatState`.
- ❌ Per-row action authorization no client — fonte da verdade é o servidor (`canBeExecutedBy`).
- ❌ Bulk action sem `chunkSize` quando a operação é pesada (default 100).
- ❌ Constraint custom que aceita `field` arbitrário do payload — sempre validar contra whitelist.

## Related

- [`packages/table/SKILL.md`](../../packages/table/SKILL.md)
- [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §TABLE-V2-002..010

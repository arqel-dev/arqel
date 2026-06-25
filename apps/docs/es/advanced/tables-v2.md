# Tables V2

> Paquete: [`arqel-dev/table`](../../../packages/table/) · Tickets: TABLE-V2-002..010

## Propósito

La Fase 2 extiende el `arqel-dev/table` de la Fase 1 con capacidades avanzadas: edición inline, query builder visual, control granular de visibilidad de columnas, agrupación con summaries, drag-drop reordering, modo móvil y múltiples tipos de paginación.

Toda la API se mantiene declarativa — `Resource::table()` sigue devolviendo un builder `Arqel\Table\Table`, y `arqel-dev/core` lo detecta vía duck-typing en `InertiaDataBuilder::isTableObject`.

## Edición inline

3 tipos de columna editables:

- `TextInputColumn` — `type='textInput'`.
- `SelectColumn` — `type='select'`, `options(array|Closure)` lazy en `toArray()` (Closure no-array degrada a `[]`).
- `ToggleColumn` — `type='toggle'`, `onValue/offValue` para mapear boolean → valor persistido arbitrario.

**Contrato común**: `editable=true` por defecto (opt-out vía `readonly()`); `debounce=500ms` por defecto, `debounce(int)` clamp a `≥0`; `rules(array)` para validación server-side; `readonly(bool|Closure=true)` — bool invierte `editable`, Closure resuelta por record en server-side.

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

El endpoint `POST {panel}/{resource}/{id}/inline-update` está **diferido** — depende de `arqel-dev/core` `ResourceRegistry::findBySlug` + autorización por Policy.

## Query Builder visual

`QueryBuilderFilter` + `Filters\Constraints\Constraint` permiten filtros anidados con AND/OR y operadores tipados.

5 constraints concretos:

| Constraint | Operadores |
|---|---|
| `TextConstraint` | equals, not_equals, contains, starts_with, ends_with |
| `NumberConstraint` | =, !=, >, <, >=, <=, between (cast int/float; no-numérico → `InvalidArgumentException`) |
| `DateConstraint` | =, before, after, between (`Carbon::parse`) |
| `BooleanConstraint` | is_true, is_false |
| `SelectConstraint` | equals, not_equals, in, not_in (`whereIn`/`whereNotIn` ignora silenciosamente no-arrays) |

```php
use Arqel\Table\Filters\QueryBuilderFilter;
use Arqel\Table\Filters\Constraints\{TextConstraint, NumberConstraint, DateConstraint};

QueryBuilderFilter::make('advanced')->constraints([
    new TextConstraint('title'),
    new NumberConstraint('price'),
    new DateConstraint('published_at'),
]);
```

**Garantía de seguridad**: cada lookup pasa por `findConstraint($field)` contra una whitelist declarada. Field desconocido u operador fuera de la lista se descartan silenciosamente — no hay camino desde input arbitrario del usuario hasta un nombre de columna SQL.

## Visibilidad de columnas

3 flags fluent en la base `Column`:

- `togglable(bool=true)` — añade al dropdown de visibilidad.
- `hiddenByDefault(bool=true)` — columna oculta en el primer render (auto-habilita `togglable`; un `togglable(false)` posterior gana).
- `hiddenOnMobile(bool=true)` — oculta en viewport móvil.

Getters `isTogglable/isHiddenByDefault/isHiddenOnMobile` expuestos en el payload Inertia. La persistencia cross-package por usuario (`POST /admin/user-settings/tables/{resource}`) llega en el lado React.

## Agrupación con summaries

```php
use Arqel\Table\Summaries\Summary;

Table::make()
    ->groupBy('category', fn ($record) => $record->category->name)
    ->groupSummaries([
        Summary::sum('price'),
        Summary::count(),
    ]);
```

5 tipos finales de summary (`SumSummary`, `AvgSummary` (salta nulls), `CountSummary` (field opcional), `MinSummary`, `MaxSummary`). Facade estática `Summary::sum/avg/count/min/max($field)`.

`buildGroups(Collection)` devuelve `array<{label, key, records, summaries}>` — sin `groupBy` devuelve un único grupo `'All'`.

## Reorderable

```php
Table::make()->reorderable('position');  // null disables
```

Getters `getReorderColumn(): ?string`, `isReorderable(): bool`. El DnD-kit + auto-scroll + rollback de UI llegan en el lado React. Regla: **bloquear reorder cuando sort != reorder column**.

## Modo móvil

```php
Table::make()->mobileMode(Table::MOBILE_MODE_STACKED);  // o MOBILE_MODE_SCROLL
```

Default `'stacked'`. Un valor desconocido cae silenciosamente al default (un typo no debe romper el render Inertia).

## Tipos de paginación

4 constantes:

| Constante | String | Semántica |
|---|---|---|
| `PAGINATION_LENGTH_AWARE` | `'lengthAware'` (default) | paginador clásico con números de página + total |
| `PAGINATION_SIMPLE` | `'simple'` | solo prev/next |
| `PAGINATION_CURSOR` | `'cursor'` | basado en cursor (recomendado en datasets grandes u ordenamiento inestable) |
| `PAGINATION_INFINITE` | `'infinite'` | flag para que React use `merge` de Inertia 3 al hacer scroll |

```php
Table::make()->paginationType(Table::PAGINATION_CURSOR);
```

El lado React de Inertia 3 merge está **diferido** a `TABLE-JS-XXX`: `IntersectionObserver` en la última fila + `router.reload({ only: ['records'], merge: ['records.data'], data: { page: currentPage + 1 } })`.

## FAQ

**¿Cómo autorizo edición inline por record?**
`->readonly(fn ($record) => !auth()->user()->can('update', $record))`. El servidor es el source of truth.

**¿Puedo anidar grupos del Query Builder?**
Sí — el payload acepta `operator: 'AND'|'OR'` y grupos anidados. `applyConditions` es recursivo.

**¿Reorder respeta los filtros actuales?**
La operación de reorder corre contra el subset visible, pero la regla **bloquear reorder cuando sort != reorder column** vive en el lado React. Coordinación cross-package.

## Anti-patrones

- ❌ Lógica de query en Column — eager loading vía `RelationshipColumn`/`indexQuery`, nunca en `formatState`.
- ❌ Autorización de Action por fila en el cliente — el source of truth es el servidor (`canBeExecutedBy`).
- ❌ Bulk action sin `chunkSize` cuando la operación es pesada (default 100).
- ❌ Constraint custom que acepta `field` arbitrario del payload — siempre validar contra whitelist.

## Relacionado

- [`packages/table/SKILL.md`](../../../packages/table/SKILL.md)
- [`PLANNING/09-fase-2-essenciais.md`](../../../PLANNING/09-fase-2-essenciais.md) §TABLE-V2-002..010

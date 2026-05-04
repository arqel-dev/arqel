# SKILL.md — arqel-dev/widgets

> Contexto canónico para AI agents a trabalhar no pacote `arqel-dev/widgets`.

## Purpose

`arqel-dev/widgets` entrega o sistema de **widgets de dashboard** para Arqel: cards de KPI (Stat), charts (Chart), mini-tabelas (Table) e widgets custom (escape-hatch). Cada widget é uma classe PHP declarativa que expõe um React component name + payload `data` per-render.

Suporta polling (refresh automático), deferred loading (lazy fetch para widgets pesados), visibility per-user (`canSee`) e filtros declarativos partilhados entre widgets do mesmo Dashboard. Dashboards são compostos por uma lista de widgets + grid columns responsivo + filtros.

## Status

**Base (WIDGETS-001..006):**

- Esqueleto `arqel-dev/widgets` com PSR-4 `Arqel\Widgets\` → `src/`, dep em `arqel-dev/core` via path repo.
- `Arqel\Widgets\Widget` (abstract) — fluent API: `heading`, `description`, `sort`, `columnSpan(int|string)`, `poll(int)`, `deferred(bool)`, `canSee(Closure)`, `filters(array)`. Construtor `(string $name)`. Subclasses declaram `protected string $type` (snake_case) + `protected string $component` (PascalCase) e implementam `data(): array`. `toArray(?Authenticatable)` emite payload Inertia (`data: null` quando deferred). `id()` default = `<type>:<name>`. `canBeSeenBy(?Authenticatable)`, `filterValue(string, mixed=null)`, `getFilters(): array`.
- `Arqel\Widgets\Dashboard` — `(string $id, string $label, ?string $path = null)` props readonly; factory `Dashboard::make(...)`. `widgets(array)` aceita Widget instances **e** `class-string<Widget>` (resolvidos via container em `resolve()`); silently filtra entradas inválidas. `addWidget()`, `columns(int|array)` (clamp 1..12 ou mapa responsivo), `heading`, `description`, `canSee`. `findWidget(string $widgetId): ?Widget` (sem auth — caller distingue 404 vs 403).
- `Arqel\Widgets\DashboardRegistry` (final, singleton) — `register(Dashboard)` keyed por id (duplicata lança `InvalidArgumentException`); `has`/`get`/`all`/`clear`.
- `Arqel\Widgets\WidgetRegistry` (final, singleton) — `register(type, class-string<Widget>)` valida `is_subclass_of(Widget)`; `has`/`get`/`all`/`clear`.
- `Arqel\Widgets\WidgetsServiceProvider` auto-discovered; binds singletons em `packageRegistered`; `configurePackage()` chama `->hasRoute('admin')`.

**Concrete widget types (WIDGETS-002..005):**

- `StatWidget` (final) — KPI card; setters value/description/icon/color/trend.
- `ChartWidget` (final) — config Recharts serializada (sem hard dep em libs JS).
- `TableWidget` (final) — mini-tabela; sem hard dep em `arqel-dev/table` (duck-typing).
- `CustomWidget` (final) — escape-hatch para componentes React arbitrários.

**HTTP layer (WIDGETS-007..008):**

- `Http\Controllers\DashboardController` (final) — `show(Request, DashboardRegistry, ?string $dashboardId='main'): Inertia\Response`. 404 quando ausente; render `'arqel::dashboard'` com `dashboard` + `filterValues`.
- `Http\Controllers\WidgetDataController` (final) — `show(Request, DashboardRegistry, string $dashboardId, string $widgetId): JsonResponse`. 404 dashboard/widget desconhecido, 403 `canBeSeenBy` reject. Aplica `$widget->filters($request->input('filters'))` quando array; retorna `{data: $widget->data()}`. Cobre RF-W-04 (Polling) + RF-W-05 (Deferred).
- Rotas em `routes/admin.php` (middleware `web` + `auth`): `GET /admin`, `GET /admin/dashboards/{dashboardId}`, `GET /admin/dashboards/{dashboardId}/widgets/{widgetId}/data` (`arqel.dashboard.widget-data`).

**Filters (WIDGETS-009):**

- `Filters\Filter` (abstract) — `(string $name)`; factory `Filter::make($name)`. Setters `label(string)`, `default(mixed)`. Subclasses declaram `$type` + `$component` e implementam `getTypeSpecificProps(): array`. `toArray()` emite `{name, type, component, label, default, ...}`. Label fallback via `Str::of($name)->snake()->replace('_',' ')->title()`.
- `Filters\DateRangeFilter` (final) — `type='date_range'`, `component='DateRangeFilter'`. `defaultRange(?DateTimeInterface, ?DateTimeInterface)`.
- `Filters\SelectFilter` (final) — `type='select'`, `component='SelectFilter'`. `options(array|Closure)` (Closure lazy), `multiple(bool=true)`.
- **`Dashboard::filters()` dual-mode** — aceita `array<string, mixed>` legado (passthrough, BC) **OU** `list<Filter>` (declarativo). Detecção por presença de qualquer `Filter`. Em modo declarativo armazena `$declaredFilters` + `$filterDefaults`; getters `getDeclaredFilters()`/`getFilterDefaults()`.
- **Propagação:** `Dashboard::resolve(?Authenticatable)` instancia class-strings, filtra por `canBeSeenBy`, ordena por `getSort()`, e para cada widget aplica `array_merge($dashboardFilterDefaults, $widget->getFilters())` antes de serializar — request-time values vencem dashboard defaults.

**Scaffolders (WIDGETS-013):**

- `Commands\MakeWidgetCommand` (final) — `arqel:widget <Name> --type=stat|chart|table|custom --force`. Gera `app/Widgets/<Name>.php`; snake_case do class name vira o `name` arg do construtor (e.g. `TotalUsers` → `'total_users'`). Idempotente (skip sem `--force`). Stubs em `stubs/widgets/{stat,chart,table,custom}.stub`.
- `Commands\MakeDashboardCommand` (final) — `arqel:dashboard <Name> --id=<custom> --force`. Gera `app/Dashboards/<Name>.php` com factory static `make(): Dashboard`. `--id` default = snake_case; `label` humanised. Stub em `stubs/dashboards/dashboard.stub`.

**Coverage:** ~139 testes Pest passando. Fixture `EchoFiltersWidget` usada em WIDGETS-008/009.

**Por chegar (WIDGETS-010..012, 014..015):**

- React side em `@arqel-dev/ui/widgets` — components Stat/Chart/Table/Custom, `DateRangeFilter`/`SelectFilter` JS, polling/deferred client (WIDGETS-010..012).
- Filters URL sync + refetch on filter change (WIDGETS-011..012).
- E2E + dashboard demo + 90% coverage target (WIDGETS-014..015).

## Conventions

- `declare(strict_types=1)` obrigatório; bases `abstract`, subclasses (`Widget`, `Filter`) `final`.
- **Sem hard dep** em libs de chart (Recharts é JS-side; `ChartWidget` só serializa config).
- `columnSpan(int)` 1..12; `columnSpan(string)` aceita atalhos (`'full'`, `'1/2'`).
- `deferred` widgets devem ter um `WidgetDataController` endpoint para fetch (entregue em WIDGETS-008).
- Filters propagam via merge: `dashboard defaults` ← `widget filters(request values)` (request wins).

## Anti-patterns

- Lógica pesada em `data()` (SQL N+1, chamadas externas síncronas) — usa `deferred(true)` + queue jobs.
- `columnSpan(13+)` — grid base é 12.
- Polling agressivo (`poll(1)`) — mínimo prático 30s; realtime via Reverb é Phase 4.
- Widget sem `canSee` em payload sensível — server é fonte da verdade; UI-only filtering é UX (ADR-017).
- `CustomWidget` como `<iframe>` — quebra single-page navigation.

## Examples

`StatWidget` declarativo:

```php
use Arqel\Widgets\StatWidget;

final class TotalUsersWidget extends StatWidget
{
    public function __construct()
    {
        parent::__construct('total_users');
        $this->heading('Total users')->columnSpan(3)->poll(60);
    }

    public function data(): array
    {
        return ['value' => User::count()];
    }
}
```

Composição de Dashboard com filtros declarativos:

```php
use Arqel\Widgets\Dashboard;
use Arqel\Widgets\Filters\{DateRangeFilter, SelectFilter};

return Dashboard::make('main', 'Overview')
    ->columns(['default' => 1, 'md' => 2, 'lg' => 4])
    ->filters([
        DateRangeFilter::make('period'),
        SelectFilter::make('status')->options(['active' => 'Active', 'archived' => 'Archived']),
    ])
    ->widgets([
        TotalUsersWidget::class,
        RevenueChartWidget::class,
    ]);
```

Widget deferred com leitura de filtro:

```php
final class RevenueChartWidget extends ChartWidget
{
    public function __construct()
    {
        parent::__construct('revenue');
        $this->heading('Revenue')->deferred(true)->columnSpan('full');
    }

    public function data(): array
    {
        $period = $this->filterValue('period', ['from' => null, 'to' => null]);
        return Order::query()
            ->whereBetween('created_at', [$period['from'], $period['to']])
            ->sum('total');
    }
}
```

## Related

- Source: [`packages/widgets/src/`](./src/)
- Testes: [`packages/widgets/tests/`](./tests/)
- Tickets: [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §WIDGETS-001..015
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §Widgets
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3
  - [ADR-017](../../PLANNING/03-adrs.md) — Authorization UX-only no client

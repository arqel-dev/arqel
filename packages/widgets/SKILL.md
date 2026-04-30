# SKILL.md — arqel/widgets

> Contexto canónico para AI agents.

## Purpose

`arqel/widgets` entrega o sistema de **widgets de dashboard** para Arqel: cards de KPI (Stat), charts (Chart), tabelas (Table) e widgets custom (Custom). Cada widget é uma classe PHP declarativa que expõe um React component name + payload `data` per-render. Suporta polling (refresh automático), deferred loading (lazy fetch para widgets pesados) e visibility per-user.

## Status

**Entregue (WIDGETS-001..009):**

- Esqueleto do pacote `arqel/widgets` com PSR-4 `Arqel\Widgets\` → `src/`, dep em `arqel/core` via path repo
- **`Arqel\Widgets\Widget`** abstract base com fluent API: `heading`, `description`, `sort`, `columnSpan(int|string)`, `poll(int)`, `deferred(bool)`, `canSee(Closure)`, `filters(array)`. Construtor `(string $name)`. Subclasses declaram `protected string $type` (snake_case identifier) + `protected string $component` (PascalCase React component name) e implementam `data(): array`. `toArray(?Authenticatable)` emite payload canônico para Inertia; `data: null` quando deferred. `id()` default = `<type>:<name>`. `canBeSeenBy(?Authenticatable)` oracle (default true). **`filterValue(string $name, mixed $default = null)`** (WIDGETS-009) lê do filter map merged. **`getFilters(): array`** (WIDGETS-009) exposto para inspeção.
- **`Arqel\Widgets\Dashboard`** (refatorado em WIDGETS-006): construtor `(string $id, string $label, ?string $path = null)` com props readonly; factory `Dashboard::make($id, $label, $path = null)`. `widgets(array)` aceita Widget instances **e** `class-string<Widget>` (resolução via container deferida para `resolve()`); filtra non-Widget/non-class-string silently. `addWidget(Widget|class-string<Widget>)`. `columns(int|array)` aceita int (clamp 1..12) ou mapa responsivo. Setters `heading`, `description`, `canSee(Closure)`. **`filters(array)` dual-mode** (WIDGETS-009): aceita `array<string, mixed>` legado (passthrough — BC mantida) **OU** `list<Filter>` (novo). Detecção por presença de qualquer instância `Filter`. Em modo declarativo armazena `$declaredFilters` + `$filterDefaults`. Getters `getDeclaredFilters()` + `getFilterDefaults()`. **`resolve(?Authenticatable)`** é o serializador canônico: instancia class-strings, filtra por `canBeSeenBy`, sort por `getSort()`, e para cada widget aplica `array_merge($dashboardFilterDefaults, $widget->getFilters())` antes de serializar (request-time values vencem dashboard defaults).
- **`Arqel\Widgets\DashboardRegistry`** (final, singleton, WIDGETS-006) `register(Dashboard)` keyed por `$dashboard->id` (lança `InvalidArgumentException` em duplicata); `has`/`get`/`all`/`clear`. Singleton bound em `WidgetsServiceProvider::packageRegistered`.
- **`Arqel\Widgets\WidgetRegistry`** (final, singleton) `register(type, class-string<Widget>)` valida `is_subclass_of(Widget)` (lança `InvalidArgumentException`); `has`/`get`/`all`/`clear`.
- **`Arqel\Widgets\WidgetsServiceProvider`** auto-discovered; `packageRegistered()` faz singletons; `configurePackage()` chama `->hasRoute('admin')`.
- **`Arqel\Widgets\StatWidget`**, **`ChartWidget`**, **`TableWidget`**, **`CustomWidget`** (WIDGETS-002..005) — KPI/Recharts/mini-tabela/escape-hatch. Cada um final com fluent API documentada nos tests (16 + 14 + 10 + 10 testes). `TableWidget` sem hard dep em `arqel/table` (duck-typing).
- **`Arqel\Widgets\Http\Controllers\DashboardController`** (final, WIDGETS-007) — `show(Request, DashboardRegistry, ?string $dashboardId = null): Inertia\Response`. Default `'main'`. 404 quando ausente, render `'arqel::dashboard'` com `dashboard` + `filterValues`. Rotas `GET /admin` + `GET /admin/dashboards/{dashboardId}` em `routes/admin.php`, middleware `web` + `auth`.
- **`Dashboard::findWidget(string $widgetId): ?Widget`** (WIDGETS-008) — lookup por id com class-string resolution; refator extracted private `resolveEntry()` helper partilhado com `resolve()`. **Auth não enforced** — caller distingue 404 vs 403.
- **`Arqel\Widgets\Http\Controllers\WidgetDataController`** (final, WIDGETS-008) — `show(Request, DashboardRegistry, string $dashboardId, string $widgetId): JsonResponse`. 404 dashboard/widget desconhecido, 403 `canBeSeenBy` reject. Aplica `$widget->filters($request->input('filters'))` quando array, retorna `{data: $widget->data()}`. Rota `GET /admin/dashboards/{dashboardId}/widgets/{widgetId}/data` (`arqel.dashboard.widget-data`). Cobre RF-W-04 (Polling) + RF-W-05 (Deferred).
- **`Arqel\Widgets\Filters\Filter`** (abstract, WIDGETS-009) — base declarativa. Construtor `(string $name)`; factory `Filter::make($name)`. Setters fluentes `label(string)`, `default(mixed)`. Subclasses declaram `$type` + `$component` e implementam `getTypeSpecificProps(): array`. `toArray()` emite `{name, type, component, label, default, ...typeSpecificProps}`. Label fallback: `Str::of($name)->snake()->replace('_',' ')->title()`.
- **`Arqel\Widgets\Filters\DateRangeFilter`** (final, WIDGETS-009) — `type='date_range'`, `component='DateRangeFilter'`. `defaultRange(?DateTimeInterface, ?DateTimeInterface)` armazena `['from' => ?DateTime, 'to' => ?DateTime]`.
- **`Arqel\Widgets\Filters\SelectFilter`** (final, WIDGETS-009) — `type='select'`, `component='SelectFilter'`. `options(array|Closure)` (Closure lazy), `multiple(bool=true)`. `getTypeSpecificProps` retorna `{options, multiple}`.
- **Testes Pest:** ~117 testes (adicionados aos 116 prévios: 8 `FilterTest` + 7 `DashboardFilterPropagationTest`). Inclui fixture `EchoFiltersWidget` que ecoa o filter map via `data()` — usado por WIDGETS-008 (passthrough request-time) e WIDGETS-009 (propagação dashboard defaults).

**Por chegar (WIDGETS-010..015):**

- React components em `@arqel/ui/widgets` (incluindo `DateRangeFilter` / `SelectFilter` JS-side, `WidgetDataController` polling/deferred client) — WIDGETS-010..012
- Filters URL sync + refetch on change — WIDGETS-011..012
- Suite full de testes + SKILL.md final + dashboard demo — WIDGETS-013..015

## Conventions

- `declare(strict_types=1)` obrigatório
- Subclasses `Widget`/`Filter` são `final` por convenção; bases são `abstract`
- **Sem hard dep** em libs de chart (Recharts é JS-side; ChartWidget só serializa config)
- `columnSpan(int)` 1..12 default; `columnSpan(string)` atalhos (`'full'`, `'1/2'`)
- `deferred` widgets devem ter um `WidgetDataController` endpoint para fetch — entregue em WIDGETS-008

## Anti-patterns

- ❌ **Lógica pesada em `data()`** — SQL N+1, chamadas externas síncronas. Use `deferred(true)` + queue jobs
- ❌ **`columnSpan(13+)`** — grid base é 12
- ❌ **Polling agressivo** (`poll(1)`) — mínimo 30s; realtime via Reverb (Phase 4)
- ❌ **Widget sem `canSee` em payload sensível** — abilities explicit no servidor; UI-only filtering é UX (ADR-017)
- ❌ **Custom Widget como `<iframe>`** — quebra single-page navigation

## Related

- Tickets: [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §WIDGETS-001..015
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §Widgets
- Source: [`packages/widgets/src/`](./src/)
- Tests: [`packages/widgets/tests/`](./tests/)
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3
  - [ADR-017](../../PLANNING/03-adrs.md) — Authorization UX-only no client

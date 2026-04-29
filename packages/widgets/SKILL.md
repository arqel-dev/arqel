# SKILL.md — arqel/widgets

> Contexto canónico para AI agents.

## Purpose

`arqel/widgets` entrega o sistema de **widgets de dashboard** para Arqel: cards de KPI (Stat), charts (Chart), tabelas (Table) e widgets custom (Custom). Cada widget é uma classe PHP declarativa que expõe um React component name + payload `data` per-render. Suporta polling (refresh automático), deferred loading (lazy fetch para widgets pesados) e visibility per-user.

## Status

**Entregue (WIDGETS-001..002):**

- Esqueleto do pacote `arqel/widgets` com PSR-4 `Arqel\Widgets\` → `src/`, dep em `arqel/core` via path repo
- **`Arqel\Widgets\Widget`** abstract base com fluent API: `heading`, `description`, `sort`, `columnSpan(int|string)`, `poll(int)`, `deferred(bool)`, `canSee(Closure)`, `filters(array)`. Construtor `(string $name)`. Subclasses declaram `protected string $type` (snake_case identifier) + `protected string $component` (PascalCase React component name) e implementam `data(): array`. `toArray(?Authenticatable)` emite payload canônico para Inertia; `data: null` quando deferred. `id()` default = `<type>:<name>`. `canBeSeenBy(?Authenticatable)` oracle (default true)
- **`Arqel\Widgets\Dashboard`** (final) builder de schema dashboard: `widgets(array)` (filtra non-Widget silently), `addWidget(Widget)`, `columns(int)` (clamp 1..12), `heading`, `description`, `canSee(Closure)`. `toArray(?Authenticatable)` filtra widgets por `canBeSeenBy` + sort por `getSort()` (null sorts last via PHP_INT_MAX) + serializa cada widget
- **`Arqel\Widgets\WidgetRegistry`** (final, singleton) `register(type, class-string<Widget>)` valida `is_subclass_of(Widget)` (lança `InvalidArgumentException`); `has`/`get`/`all`/`clear`. Singleton bound em `WidgetsServiceProvider::packageRegistered`
- **`Arqel\Widgets\WidgetsServiceProvider`** auto-discovered via `extra.laravel.providers`; `packageRegistered()` faz `singleton(WidgetRegistry::class)`
- **`Arqel\Widgets\StatWidget`** (final) — KPI/big-number widget. Fluent API: `make(string $name)` (factory), `value(scalar|Closure)`, `statDescription(string|Closure)`, `descriptionIcon(string)`, `color(string)` (palette: primary/secondary/success/warning/danger/info, fallback primary em valores desconhecidos via constantes `COLOR_*`), `icon(string)`, `chart(array|Closure)` (sparkline points), `url(string)`. `data()` resolve Closures lazy (não invoca quando `deferred()` é true). `chart()` filtra silently non-numeric points; non-array Closure return → null. Construtor herdado de `Widget` aceita `name`
- 45 testes Pest passando: 13 Widget, 7 Dashboard, 6 WidgetRegistry, 3 ServiceProvider smoke + **16 StatWidget** (type/component, value scalar/Closure/non-scalar coerce, statDescription string/Closure/non-string→null, color canonical/unknown→primary/all 6 constants, chart filter non-numeric/Closure/null/non-array, url+icon+descriptionIcon passthrough, toArray envelope shape, deferred → data null + Closures não invocados)

**Por chegar (WIDGETS-003..015):**

- `ChartWidget` (Recharts integration: line/bar/pie/area) — WIDGETS-003
- `TableWidget` (paginated table embedded em dashboard) — WIDGETS-004
- `CustomWidget` (escape hatch para Inertia component arbitrário) — WIDGETS-005
- `Http\Controllers\DashboardController` + `WidgetDataController` (deferred fetch endpoint) — WIDGETS-006
- React components em `@arqel/ui/widgets` — WIDGETS-007..010
- Filters compartilhados (date range global, dropdown filter por dashboard) — WIDGETS-011..012
- Suite full de testes + SKILL.md final + dashboard demo — WIDGETS-013..015

## Conventions

- `declare(strict_types=1)` obrigatório
- Subclasses `Widget` são `final` por convenção; `Widget` base é abstract
- **Sem hard dep** em libs de chart (Recharts é JS-side, ChartWidget só serializa config — render é client-only)
- `columnSpan(int)` semântica é "spans X colunas do grid raiz" (1..12 default); `columnSpan(string)` aceita atalhos (`'full'`, `'1/2'`) que o `<DashboardRenderer>` React decodifica
- `deferred` widgets devem ter um `WidgetDataController` endpoint para fetch — esse vem em WIDGETS-006

## Anti-patterns

- ❌ **Lógica pesada em `data()`** — SQL N+1, chamadas externas síncronas. Use `deferred(true)` + queue jobs para resultados pre-calculados (cache redis)
- ❌ **`columnSpan(13+)`** — grid base é 12; valores maiores não fazem sentido (clampar reverte para o mínimo se passar 0/negativo)
- ❌ **Polling agressivo** (`poll(1)`) — server load explode. Mínimo recomendado: 30s. Para realtime use Reverb (Phase 4)
- ❌ **Widget sem `canSee` em payload sensível** — abilities fica explicit no servidor; UI-only filtering é UX (ADR-017), policies reforçam
- ❌ **Custom Widget como `<iframe>`** — quebra o single-page navigation. Use `CustomWidget` com Inertia partial reload

## Related

- Tickets: [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §WIDGETS-001..015
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §Widgets
- Source: [`packages/widgets/src/`](./src/)
- Tests: [`packages/widgets/tests/`](./tests/)
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3
  - [ADR-017](../../PLANNING/03-adrs.md) — Authorization UX-only no client

# Dashboards and widgets

> Package: [`arqel-dev/widgets`](../../packages/widgets/) · Tickets: WIDGETS-001..015

## Purpose

`arqel-dev/widgets` ships the **dashboard widgets** system for Arqel: KPI cards (Stat), charts (Chart), mini-tables (Table), and custom widgets (escape-hatch). Each widget is a declarative PHP class that exposes a React component name + `data` payload per render.

It supports:

- **Polling** — automatic refresh at a configurable interval.
- **Deferred loading** — lazy fetch for heavy widgets.
- **Per-user visibility** — `canSee(Closure)`.
- **Declarative filters** — shared across widgets in the same Dashboard.

Dashboards compose a list of widgets + responsive grid + filters, and `DashboardController` renders everything via Inertia.

## Quick start

`StatWidget`:

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

Dashboard composition:

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

Routes auto-registered by `WidgetsServiceProvider`:

- `GET /admin` — dashboard `main` (default).
- `GET /admin/dashboards/{dashboardId}`.
- `GET /admin/dashboards/{dashboardId}/widgets/{widgetId}/data` (`arqel.dashboard.widget-data`) — endpoint for polling/deferred.

## Widget types

### `StatWidget` (KPI card)

Setters: `value/description/icon/color/trend`. Good for single metrics — total users, MRR, conversions.

### `ChartWidget`

Serializes Recharts config. **No hard dep** on JS libs — PHP only emits the config; the React side resolves it.

### `TableWidget`

Mini-table. No hard dep on `arqel-dev/table` (duck-typing) — good for "last 5 orders" or "top customers".

### `CustomWidget`

Escape-hatch for arbitrary React components. Use when the previous 3 don't cover the need (maps, gauges, schedulers).

## Filters

`Filters\Filter` is the base. 2 concretes shipped:

- `DateRangeFilter` (`type='date_range'`) — `defaultRange(?DateTimeInterface, ?DateTimeInterface)`.
- `SelectFilter` (`type='select'`) — `options(array|Closure)` lazy, `multiple(bool=true)`.

`Dashboard::filters()` accepts both modes:

- **Legacy** — `array<string, mixed>` (passthrough, BC).
- **Declarative** — `list<Filter>`. Auto-detected by the presence of any `Filter` instance.

**Propagation**: `Dashboard::resolve(?Authenticatable)` applies `array_merge($dashboardFilterDefaults, $widget->getFilters())` before serializing — request-time values override dashboard defaults.

## Polling + deferred

```php
final class RevenueChartWidget extends ChartWidget
{
    public function __construct()
    {
        parent::__construct('revenue');
        $this->heading('Revenue')
             ->deferred(true)
             ->poll(120)
             ->columnSpan('full');
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

- `deferred(true)` — `data: null` on the initial Inertia payload; React fetches the endpoint after mount.
- `poll(120)` — React refetches every 120s. **Practical minimum: 30s**; real-time via Reverb is reserved for Phase 4.

## Scaffolders

```bash
php artisan arqel:widget TotalUsers --type=stat
php artisan arqel:dashboard Overview --id=main
```

Idempotent (skip without `--force`). Stubs at `stubs/widgets/{stat,chart,table,custom}.stub`.

## FAQ

**Can I use widgets outside `/admin`?**
Yes — instantiate `Dashboard` in your own controller and pass it to the Inertia render. `DashboardController` is just a convenient shortcut.

**How do I authorize a widget?**
`->canSee(fn ($user) => $user->can('view-revenue'))`. The server is the source of truth — UI-only filtering is UX (ADR-017).

**Does `columnSpan(13)` break?**
No — clamped to 1..12. Strings accept `'full'`, `'1/2'`, etc.

**Where do React components live?**
`@arqel-dev/ui/widgets` — delivered in WIDGETS-010..012 (currently in development; the PHP side and Inertia payload are already stable).

## Anti-patterns

- ❌ Heavy logic in `data()` (N+1 SQL, synchronous external calls) — use `deferred(true)` + queue jobs.
- ❌ Aggressive polling (`poll(1)`) — minimum 30s.
- ❌ A widget without `canSee` on a sensitive payload — server-side authz, always.
- ❌ `CustomWidget` as `<iframe>` — breaks single-page navigation.

## Related

- [`packages/widgets/SKILL.md`](../../packages/widgets/SKILL.md)
- [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §WIDGETS-001..015

# `arqel-dev/widgets` — API Reference

Namespace `Arqel\Widgets\`. Dashboard widget system: KPI cards (Stat), charts (Chart), mini-tables (Table), and an escape-hatch for arbitrary React components (Custom). Supports polling, deferred loading, per-user visibility, and declarative filters shared across a dashboard's widgets.

## `Arqel\Widgets\Widget` (abstract)

Base class for all widget types. Constructor: `(string $name)`.

| Method | Type | Description |
|---|---|---|
| `heading(string)` / `description(string)` | `static` | Visual chrome |
| `sort(int)` | `static` | Ordering within the dashboard grid |
| `columnSpan(int\|string)` | `static` | `1..12`, or a shorthand string (`'full'`, `'1/2'`) |
| `poll(int $seconds)` | `static` | Client refetch interval; `0` or negative disables polling |
| `deferred(bool = true)` | `static` | When true, `toArray()` emits `data: null` and the client fetches lazily via `WidgetDataController` |
| `canSee(Closure)` | `static` | Receives `?Authenticatable`, returns bool |
| `filters(array<string, mixed>)` | `static` | Seeds/overrides the widget's filter map |
| `data()` | `array` (abstract) | Per-render payload — subclasses implement |
| `getName()` / `getType()` / `getComponent()` / `getHeading()` / `getDescription()` / `getSort()` / `getColumnSpan()` / `getPollingInterval()` / `isDeferred()` | getters | |
| `filterValue(string $name, mixed $default = null)` | `mixed` | Canonical filter reader inside `data()` |
| `getFilters()` | `array<string, mixed>` | |
| `id()` | `string` | Default `<type>:<name>` |
| `canBeSeenBy(?Authenticatable $user)` | `bool` | `true` when no `canSee` set |
| `toArray(?Authenticatable $user = null)` | `array` | Inertia payload: `{ id, name, type, component, heading, description, sort, columnSpan, poll, deferred, filters, data }` |

Subclasses declare `protected string $type` (snake_case) and `protected string $component` (PascalCase React component name).

### `StatWidget` (not final — subclassed by `arqel:widget --type=stat`)

KPI card. Factory `StatWidget::make($name)`.

| Method | Type | Description |
|---|---|---|
| `value(mixed)` | `self` | Scalar or `Closure(): scalar`, resolved at `data()` time |
| `statDescription(mixed)` | `self` | Secondary line (e.g. `'+12% vs last week'`); string or Closure |
| `descriptionIcon(string)` / `icon(string)` | `self` | |
| `color(string)` | `self` | One of `primary\|secondary\|success\|warning\|danger\|info` (`COLOR_*` constants); unknown falls back to `primary` |
| `chart(mixed)` | `self` | `array<int\|float>` or Closure — sparkline points |
| `url(string)` | `self` | Renders the card as a link when set |

### `ChartWidget` (not final — subclassed by `arqel:widget --type=chart`)

Serializes Recharts config; rendering happens client-side. Factory `ChartWidget::make($name)`.

| Method | Type | Description |
|---|---|---|
| `chartType(string)` | `self` | One of `line\|bar\|area\|pie\|donut\|radar` (`CHART_*` constants) |
| `height(int)` | `self` | Pixels, min 50 |
| `showLegend(bool = true)` / `showGrid(bool = true)` | `self` | |
| `chartData(array\|Closure)` | `self` | `{ labels, datasets: [{label, data, color}, ...] }` |
| `chartOptions(array\|Closure)` | `self` | |
| `getChartType()` / `getHeight()` / `isLegendVisible()` / `isGridVisible()` | getters | |

### `TableWidget` (not final — subclassed by `arqel:widget --type=table`)

Mini-table; intentionally has no hard dependency on `arqel-dev/table` (columns are duck-typed via `toArray()`). Factory `TableWidget::make($name)`.

| Method | Type | Description |
|---|---|---|
| `query(Closure(): Builder)` | `self` | Must return an Eloquent `Builder` (or builder-shaped object) |
| `limit(int)` | `self` | Min 1, default 10 |
| `columns(array)` | `self` | Objects exposing `toArray()`; others dropped silently |
| `seeAllUrl(string\|Closure\|null)` | `self` | |

Errors thrown inside the `query` Closure are caught and surfaced as `loadError` on the payload rather than crashing the dashboard.

### `CustomWidget` (final)

Escape-hatch for arbitrary React components — composed via `make()`, never subclassed.

| Method | Type | Description |
|---|---|---|
| `CustomWidget::make(string $name, string $component)` | `self` | Factory |
| `component(string)` | `self` | Throws `InvalidArgumentException` on empty string |
| `withData(array\|Closure)` | `self` | Payload emitted to the React component; note the setter is `withData()`, not `data()` (PHP's LSP rules prevent narrowing `data(): array`'s return type) |

## `Arqel\Widgets\Dashboard` (final)

Declarative dashboard schema: a list of widgets + shared layout. Factory `Dashboard::make(string $id, string $label, ?string $path = null)`.

| Method | Type | Description |
|---|---|---|
| `widgets(array<Widget\|class-string<Widget>>)` | `self` | Class-strings resolved through the container at `resolve()` time; invalid entries dropped silently |
| `addWidget(Widget\|class-string<Widget>)` | `self` | |
| `columns(int\|array<string, int>)` | `self` | Flat `1..12`, or a responsive map keyed by `sm\|md\|lg\|xl\|2xl`, each clamped `1..12` |
| `heading(string)` / `description(string)` | `self` | |
| `filters(array)` | `self` | Dual-mode: legacy `array<string, mixed>` passthrough, or `list<Filter>` declarative (detected by presence of any `Filter` instance) |
| `canSee(Closure)` | `self` | Receives `?Authenticatable` |
| `canBeSeenBy(?Authenticatable $user)` | `bool` | |
| `getWidgets()` / `getColumns()` / `getHeading()` / `getDescription()` / `getFilters()` / `getDeclaredFilters()` / `getFilterDefaults()` | getters | |
| `resolve(?Authenticatable $user = null)` | `array` | Resolves class-string widgets, filters by `canBeSeenBy`, sorts by `getSort()`, merges dashboard filter defaults into each widget, returns `{ id, label, path, widgets, filters, columns, heading, description }` |
| `toArray(?Authenticatable $user = null)` | `array` | Alias for `resolve()` |
| `findWidget(string $widgetId)` | `?Widget` | Lookup by `id()`; authorization intentionally not enforced here (callers distinguish 404 vs 403) |

## `Arqel\Widgets\DashboardRegistry` (final, singleton)

`register(Dashboard)` (throws `InvalidArgumentException` on duplicate id — no silent overwrite), `has(id)`, `get(id): ?Dashboard`, `all(): array<string, Dashboard>`, `clear()`.

## `Arqel\Widgets\WidgetRegistry` (final, singleton)

`register(string $type, class-string<Widget> $widgetClass)` (validates `is_subclass_of(Widget::class)`), `has(type)`, `get(type): ?class-string<Widget>`, `all()`, `clear()`.

## Panel → Dashboard bridge

Widgets declared on a Panel reach the rendered dashboard. `WidgetsServiceProvider` defers a sync to `app->booted()` that collects `Panel::getWidgets()` from **every** registered panel and folds it into the dashboard with id `main` — the id `DashboardController::show()` falls back to for the `/admin` route.

```php
// A ServiceProvider, or a Plugin's register()/boot()
Panel::make('admin')->widgets([
    TotalUsersWidget::class,
    RevenueChartWidget::class,
]);
```

The sync is **additive**, never destructive:

| Situation | Behavior |
|---|---|
| No panel declares widgets | No-op — no phantom dashboard is registered |
| Widgets declared, no `main` dashboard registered | Creates `Dashboard::make('main', 'Dashboard')` holding those widgets |
| Widgets declared, `main` already registered by the app | **Appends** them via `addWidget()`, preserving both the widgets and the label the app chose |

Notes:

- The sync runs after core's `bootPanelPlugins()`, so widgets a Plugin injects in its `boot()` still reach the dashboard. That boot ordering is what makes the bridge useful to the Plugin API: a plugin adds widgets to the app's dashboard without knowing about it or replacing it.
- Panel widgets are appended **after** the ones the app registered. Final render order does not depend on that: `Dashboard::resolve()` sorts by `Widget::getSort()`; insertion order is only the tie-breaker among equal `sort` values.
- Entries that are not `Widget` subclasses are dropped silently — validation lives in `Dashboard::addWidget()`, and a misconfigured class-string must not break panel boot.
- In a multi-panel app, widgets from **all** panels converge into the same `main` dashboard: there is no panel↔dashboard binding today.
- Dashboards with their own id are still registered directly on the `DashboardRegistry`; the bridge only ever touches `main`.

## Filters

### `Arqel\Widgets\Filters\Filter` (abstract)

Declarative dashboard-level filter, propagated into each widget's filter map at `resolve()` time. Constructor `(string $name)` is `final`; factory `Filter::make($name)`.

| Method | Type | Description |
|---|---|---|
| `label(string)` | `static` | Default = `Str::of($name)->snake()->replace('_',' ')->title()` |
| `default(mixed)` | `static` | |
| `getName()` / `getLabel()` / `getDefault()` / `getType()` / `getComponent()` | getters | |
| `getResolvedDefault()` | `mixed` | The serializable default (same value `toArray()['default']` emits) — use this, not `getDefault()`, when seeding a filter map |
| `toArray()` | `array` | `{ name, type, component, label, default, ...typeSpecificProps }` |

### `Filters\DateRangeFilter` (final)

`type='date_range'`, `component='DateRangeFilter'`. `defaultRange(?DateTimeInterface $from, ?DateTimeInterface $to): static` stores the range; `resolveDefault()` serializes both endpoints as `Y-m-d` strings (`{from, to}`) so a raw `DateTimeInterface` never leaks its cast shape to the client.

### `Filters\SelectFilter` (final)

`type='select'`, `component='SelectFilter'`. `options(array|Closure)` (Closure resolved lazily at `toArray()` time), `multiple(bool = true)`.

## HTTP

Registered in `routes/admin.php` under `web` + `auth` + `HandleArqelInertiaRequests` (needed for the sidebar/`panel.navigation` shared prop):

| Verb | Route | Name | Controller |
|---|---|---|---|
| GET | `/admin` | `arqel.dashboard.main` | `Http\Controllers\DashboardController::show` |
| GET | `/admin/dashboards/{dashboardId}` | `arqel.dashboard.show` | `Http\Controllers\DashboardController::show` |
| GET | `/admin/dashboards/{dashboardId}/widgets/{widgetId}/data` | `arqel.dashboard.widget-data` | `Http\Controllers\WidgetDataController::show` |

Both controllers `abort_unless(Dashboard::canBeSeenBy($user), 403)` before resolving further; `WidgetDataController` additionally checks `Widget::canBeSeenBy()` and seeds dashboard filter defaults under request-supplied filter values before calling `data()`.

## Artisan commands

| Command | Function |
|---|---|
| `arqel:widget <Name> --type=stat\|chart\|table\|custom --force` | Scaffolds `app/Widgets/<Name>.php`. `stat`/`chart`/`table` generate a `final` subclass; `custom` generates a `final` factory composing `CustomWidget::make()` |
| `arqel:dashboard <Name> --id=<custom> --force` | Scaffolds `app/Dashboards/<Name>.php` with a static `make(): Dashboard` factory |

## Example

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

## Related

- SKILL: [`packages/widgets/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/widgets/SKILL.md)
- Source: [`packages/widgets/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/widgets/src/)

# Dashboards y widgets

> Paquete: [`arqel-dev/widgets`](../../packages/widgets/) · Tickets: WIDGETS-001..015

## Propósito

`arqel-dev/widgets` provee el sistema de **widgets de dashboard** para Arqel: tarjetas KPI (Stat), charts (Chart), mini-tablas (Table) y widgets custom (escape-hatch). Cada widget es una clase PHP declarativa que expone un nombre de componente React + payload `data` por render.

Soporta:

- **Polling** — refresh automático en intervalo configurable.
- **Carga diferida** — fetch lazy para widgets pesados.
- **Visibilidad por usuario** — `canSee(Closure)`.
- **Filtros declarativos** — compartidos entre widgets del mismo Dashboard.

Los Dashboards componen una lista de widgets + grid responsivo + filtros, y `DashboardController` renderiza todo vía Inertia.

## Inicio rápido

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

Composición de Dashboard:

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

Rutas auto-registradas por `WidgetsServiceProvider`:

- `GET /admin` — dashboard `main` (default).
- `GET /admin/dashboards/{dashboardId}`.
- `GET /admin/dashboards/{dashboardId}/widgets/{widgetId}/data` (`arqel.dashboard.widget-data`) — endpoint para polling/diferido.

## Tipos de widget

### `StatWidget` (tarjeta KPI)

Setters: `value/description/icon/color/trend`. Bueno para métricas únicas — total de usuarios, MRR, conversiones.

### `ChartWidget`

Serializa la config de Recharts. **Sin hard dep** en libs JS — PHP solo emite la config; el lado React la resuelve.

### `TableWidget`

Mini-tabla. Sin hard dep en `arqel-dev/table` (duck-typing) — bueno para "últimas 5 órdenes" o "top customers".

### `CustomWidget`

Escape-hatch para componentes React arbitrarios. Úsalo cuando los 3 anteriores no cubren la necesidad (mapas, gauges, schedulers).

## Filtros

`Filters\Filter` es la base. 2 concretos incluidos:

- `DateRangeFilter` (`type='date_range'`) — `defaultRange(?DateTimeInterface, ?DateTimeInterface)`.
- `SelectFilter` (`type='select'`) — `options(array|Closure)` lazy, `multiple(bool=true)`.

`Dashboard::filters()` acepta ambos modos:

- **Legacy** — `array<string, mixed>` (passthrough, BC).
- **Declarativo** — `list<Filter>`. Auto-detectado por la presencia de cualquier instancia de `Filter`.

**Propagación**: `Dashboard::resolve(?Authenticatable)` aplica `array_merge($dashboardFilterDefaults, $widget->getFilters())` antes de serializar — los valores en request-time sobrescriben los defaults del dashboard.

## Polling + diferido

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

- `deferred(true)` — `data: null` en el payload Inertia inicial; React hace fetch del endpoint después del mount.
- `poll(120)` — React refetchea cada 120s. **Mínimo práctico: 30s**; tiempo real vía Reverb está reservado para Fase 4.

## Scaffolders

```bash
php artisan arqel:widget TotalUsers --type=stat
php artisan arqel:dashboard Overview --id=main
```

Idempotente (skip sin `--force`). Stubs en `stubs/widgets/{stat,chart,table,custom}.stub`.

## FAQ

**¿Puedo usar widgets fuera de `/admin`?**
Sí — instancia `Dashboard` en tu propio controlador y pásalo al render Inertia. `DashboardController` es solo un atajo conveniente.

**¿Cómo autorizo un widget?**
`->canSee(fn ($user) => $user->can('view-revenue'))`. El servidor es el source of truth — el filtrado solo en UI es UX (ADR-017).

**¿`columnSpan(13)` rompe?**
No — clamp a 1..12. Los strings aceptan `'full'`, `'1/2'`, etc.

**¿Dónde viven los componentes React?**
`@arqel-dev/ui/widgets` — entregado en WIDGETS-010..012 (actualmente en desarrollo; el lado PHP y el payload Inertia ya son estables).

## Anti-patrones

- ❌ Lógica pesada en `data()` (SQL N+1, llamadas externas síncronas) — usa `deferred(true)` + queue jobs.
- ❌ Polling agresivo (`poll(1)`) — mínimo 30s.
- ❌ Un widget sin `canSee` en un payload sensible — authz server-side, siempre.
- ❌ `CustomWidget` como `<iframe>` — rompe la navegación single-page.

## Relacionado

- [`packages/widgets/SKILL.md`](../../packages/widgets/SKILL.md)
- [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §WIDGETS-001..015

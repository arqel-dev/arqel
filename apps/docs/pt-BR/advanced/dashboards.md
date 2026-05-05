# Dashboards e widgets

> Pacote: [`arqel-dev/widgets`](../../packages/widgets/) · Tickets: WIDGETS-001..015

## Purpose

`arqel-dev/widgets` entrega o sistema de **widgets de dashboard** para Arqel: cards de KPI (Stat), charts (Chart), mini-tabelas (Table) e widgets custom (escape-hatch). Cada widget é uma classe PHP declarativa que expõe um React component name + payload `data` per-render.

Suporta:

- **Polling** — refresh automático em intervalo configurável.
- **Deferred loading** — lazy fetch para widgets pesados.
- **Visibility per-user** — `canSee(Closure)`.
- **Filtros declarativos** — partilhados entre widgets do mesmo Dashboard.

Dashboards compõem uma lista de widgets + grid responsivo + filtros, e o `DashboardController` renderiza tudo via Inertia.

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

Composição de Dashboard:

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

Rotas registadas auto via `WidgetsServiceProvider`:

- `GET /admin` — dashboard `main` (default).
- `GET /admin/dashboards/{dashboardId}`.
- `GET /admin/dashboards/{dashboardId}/widgets/{widgetId}/data` (`arqel.dashboard.widget-data`) — endpoint para polling/deferred.

## Widget types

### `StatWidget` (KPI card)

Setters: `value/description/icon/color/trend`. Bom para métricas únicas — total users, MRR, conversões.

### `ChartWidget`

Serializa config Recharts. **Sem hard dep** em libs JS — o PHP só emite a config; o React side resolve.

### `TableWidget`

Mini-tabela. Sem hard dep em `arqel-dev/table` (duck-typing) — bom para "últimos 5 pedidos" ou "top customers".

### `CustomWidget`

Escape-hatch para componentes React arbitrários. Use quando os 3 anteriores não cobrem (mapas, gauges, schedulers).

## Filtros

`Filters\Filter` é a base. 2 concretes prontos:

- `DateRangeFilter` (`type='date_range'`) — `defaultRange(?DateTimeInterface, ?DateTimeInterface)`.
- `SelectFilter` (`type='select'`) — `options(array|Closure)` lazy, `multiple(bool=true)`.

`Dashboard::filters()` aceita os dois modos:

- **Legado** — `array<string, mixed>` (passthrough, BC).
- **Declarativo** — `list<Filter>`. Detecção automática pela presença de qualquer instância `Filter`.

**Propagação**: `Dashboard::resolve(?Authenticatable)` aplica `array_merge($dashboardFilterDefaults, $widget->getFilters())` antes de serializar — request-time values vencem dashboard defaults.

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

- `deferred(true)` — `data: null` no payload Inertia inicial; React faz fetch ao endpoint após mount.
- `poll(120)` — React refetcha a cada 120s. **Mínimo prático: 30s**; realtime via Reverb fica para Phase 4.

## Scaffolders

```bash
php artisan arqel:widget TotalUsers --type=stat
php artisan arqel:dashboard Overview --id=main
```

Idempotentes (skip sem `--force`). Stubs em `stubs/widgets/{stat,chart,table,custom}.stub`.

## FAQ

**Posso usar widgets fora do `/admin`?**
Sim — instancie `Dashboard` no seu próprio controller e passe ao Inertia render. O `DashboardController` é só um atalho conveniente.

**Como autorizo um widget?**
`->canSee(fn ($user) => $user->can('view-revenue'))`. Server é fonte da verdade — UI-only filtering é UX (ADR-017).

**`columnSpan(13)` quebra?**
Não — clamp em 1..12. Strings aceitam `'full'`, `'1/2'`, etc.

**Onde vivem os components React?**
`@arqel-dev/ui/widgets` — entrega WIDGETS-010..012 (atualmente em desenvolvimento; PHP side e Inertia payload já estáveis).

## Anti-patterns

- ❌ Lógica pesada em `data()` (SQL N+1, chamadas externas síncronas) — use `deferred(true)` + queue jobs.
- ❌ Polling agressivo (`poll(1)`) — mínimo 30s.
- ❌ Widget sem `canSee` em payload sensível — server-side authz, sempre.
- ❌ `CustomWidget` como `<iframe>` — quebra single-page navigation.

## Related

- [`packages/widgets/SKILL.md`](../../packages/widgets/SKILL.md)
- [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §WIDGETS-001..015

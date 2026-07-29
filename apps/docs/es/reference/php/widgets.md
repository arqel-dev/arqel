# `arqel-dev/widgets` — Referencia de API

Namespace `Arqel\Widgets\`. Sistema de widgets para dashboards: tarjetas de KPI (Stat), gráficos (Chart), mini-tablas (Table) y una vía de escape para componentes React arbitrarios (Custom). Admite polling, carga diferida, visibilidad por usuario y filtros declarativos compartidos entre los widgets de un dashboard.

## `Arqel\Widgets\Widget` (abstract)

Clase base de todos los tipos de widget. Constructor: `(string $name)`.

| Método | Tipo | Descripción |
|---|---|---|
| `heading(string)` / `description(string)` | `static` | Adornos visuales |
| `sort(int)` | `static` | Orden dentro de la grilla del dashboard |
| `columnSpan(int\|string)` | `static` | `1..12`, o una cadena abreviada (`'full'`, `'1/2'`) |
| `poll(int $seconds)` | `static` | Intervalo de refetch en el cliente; `0` o un negativo desactiva el polling |
| `deferred(bool = true)` | `static` | Cuando es true, `toArray()` emite `data: null` y el cliente lo obtiene de forma perezosa vía `WidgetDataController` |
| `canSee(Closure)` | `static` | Recibe `?Authenticatable`, devuelve bool |
| `filters(array<string, mixed>)` | `static` | Inicializa o sobrescribe el mapa de filtros del widget |
| `data()` | `array` (abstract) | Payload por renderizado — lo implementan las subclases |
| `getName()` / `getType()` / `getComponent()` / `getHeading()` / `getDescription()` / `getSort()` / `getColumnSpan()` / `getPollingInterval()` / `isDeferred()` | getters | |
| `filterValue(string $name, mixed $default = null)` | `mixed` | Lector canónico de filtros dentro de `data()` |
| `getFilters()` | `array<string, mixed>` | |
| `id()` | `string` | Por defecto `<type>:<name>` |
| `canBeSeenBy(?Authenticatable $user)` | `bool` | `true` cuando no hay `canSee` definido |
| `toArray(?Authenticatable $user = null)` | `array` | Payload de Inertia: `{ id, name, type, component, heading, description, sort, columnSpan, poll, deferred, filters, data }` |

Las subclases declaran `protected string $type` (snake_case) y `protected string $component` (nombre del componente React en PascalCase).

### `StatWidget` (no final — se subclasifica mediante `arqel:widget --type=stat`)

Tarjeta de KPI. Factory `StatWidget::make($name)`.

| Método | Tipo | Descripción |
|---|---|---|
| `value(mixed)` | `self` | Escalar o `Closure(): scalar`, resuelto en el momento de `data()` |
| `statDescription(mixed)` | `self` | Línea secundaria (p. ej. `'+12% vs last week'`); string o Closure |
| `descriptionIcon(string)` / `icon(string)` | `self` | |
| `color(string)` | `self` | Uno de `primary\|secondary\|success\|warning\|danger\|info` (constantes `COLOR_*`); un valor desconocido recae en `primary` |
| `chart(mixed)` | `self` | `array<int\|float>` o Closure — puntos del sparkline |
| `url(string)` | `self` | Renderiza la tarjeta como un enlace cuando se define |

### `ChartWidget` (no final — se subclasifica mediante `arqel:widget --type=chart`)

Serializa la configuración de Recharts; el renderizado ocurre en el cliente. Factory `ChartWidget::make($name)`.

| Método | Tipo | Descripción |
|---|---|---|
| `chartType(string)` | `self` | Uno de `line\|bar\|area\|pie\|donut\|radar` (constantes `CHART_*`) |
| `height(int)` | `self` | Píxeles, mínimo 50 |
| `showLegend(bool = true)` / `showGrid(bool = true)` | `self` | |
| `chartData(array\|Closure)` | `self` | `{ labels, datasets: [{label, data, color}, ...] }` |
| `chartOptions(array\|Closure)` | `self` | |
| `getChartType()` / `getHeight()` / `isLegendVisible()` / `isGridVisible()` | getters | |

### `TableWidget` (no final — se subclasifica mediante `arqel:widget --type=table`)

Mini-tabla; intencionalmente no tiene una dependencia dura de `arqel-dev/table` (las columnas se tipan por duck typing vía `toArray()`). Factory `TableWidget::make($name)`.

| Método | Tipo | Descripción |
|---|---|---|
| `query(Closure(): Builder)` | `self` | Debe devolver un `Builder` de Eloquent (u objeto con forma de builder) |
| `limit(int)` | `self` | Mínimo 1, por defecto 10 |
| `columns(array)` | `self` | Objetos que expongan `toArray()`; los demás se descartan silenciosamente |
| `seeAllUrl(string\|Closure\|null)` | `self` | |

Los errores lanzados dentro de la Closure de `query` se capturan y se exponen como `loadError` en el payload, en lugar de hacer caer el dashboard.

### `CustomWidget` (final)

Vía de escape para componentes React arbitrarios — se compone mediante `make()`, nunca se subclasifica.

| Método | Tipo | Descripción |
|---|---|---|
| `CustomWidget::make(string $name, string $component)` | `self` | Factory |
| `component(string)` | `self` | Lanza `InvalidArgumentException` ante una cadena vacía |
| `withData(array\|Closure)` | `self` | Payload emitido al componente React; ten en cuenta que el setter es `withData()`, no `data()` (las reglas de LSP de PHP impiden estrechar el tipo de retorno de `data(): array`) |

## `Arqel\Widgets\Dashboard` (final)

Esquema declarativo de dashboard: una lista de widgets + layout compartido. Factory `Dashboard::make(string $id, string $label, ?string $path = null)`.

| Método | Tipo | Descripción |
|---|---|---|
| `widgets(array<Widget\|class-string<Widget>>)` | `self` | Los class-strings se resuelven a través del contenedor en el momento de `resolve()`; las entradas inválidas se descartan silenciosamente |
| `addWidget(Widget\|class-string<Widget>)` | `self` | |
| `columns(int\|array<string, int>)` | `self` | Plano `1..12`, o un mapa responsivo indexado por `sm\|md\|lg\|xl\|2xl`, cada uno acotado a `1..12` |
| `heading(string)` / `description(string)` | `self` | |
| `filters(array)` | `self` | Modo dual: `array<string, mixed>` heredado como passthrough, o `list<Filter>` declarativo (detectado por la presencia de cualquier instancia de `Filter`) |
| `canSee(Closure)` | `self` | Recibe `?Authenticatable` |
| `canBeSeenBy(?Authenticatable $user)` | `bool` | |
| `getWidgets()` / `getColumns()` / `getHeading()` / `getDescription()` / `getFilters()` / `getDeclaredFilters()` / `getFilterDefaults()` | getters | |
| `resolve(?Authenticatable $user = null)` | `array` | Resuelve los widgets declarados como class-string, los filtra por `canBeSeenBy`, los ordena por `getSort()`, fusiona los valores por defecto de los filtros del dashboard en cada widget y devuelve `{ id, label, path, widgets, filters, columns, heading, description }` |
| `toArray(?Authenticatable $user = null)` | `array` | Alias de `resolve()` |
| `findWidget(string $widgetId)` | `?Widget` | Búsqueda por `id()`; la autorización intencionalmente no se aplica aquí (quienes llaman distinguen entre 404 y 403) |

## `Arqel\Widgets\DashboardRegistry` (final, singleton)

`register(Dashboard)` (lanza `InvalidArgumentException` ante un id duplicado — sin sobrescritura silenciosa), `has(id)`, `get(id): ?Dashboard`, `all(): array<string, Dashboard>`, `clear()`.

## `Arqel\Widgets\WidgetRegistry` (final, singleton)

`register(string $type, class-string<Widget> $widgetClass)` (valida `is_subclass_of(Widget::class)`), `has(type)`, `get(type): ?class-string<Widget>`, `all()`, `clear()`.

## Puente Panel → Dashboard

Los widgets declarados en un Panel llegan al dashboard renderizado. `WidgetsServiceProvider` difiere una sincronización a `app->booted()` que recopila `Panel::getWidgets()` de **todos** los panels registrados y la fusiona en el dashboard con id `main` — el id al que `DashboardController::show()` recurre por defecto para la ruta `/admin`.

```php
// Un ServiceProvider, o el register()/boot() de un Plugin
Panel::make('admin')->widgets([
    TotalUsersWidget::class,
    RevenueChartWidget::class,
]);
```

La sincronización es **aditiva**, nunca destructiva:

| Situación | Comportamiento |
|---|---|
| Ningún panel declara widgets | No-op — no se registra ningún dashboard fantasma |
| Widgets declarados, sin dashboard `main` registrado | Crea `Dashboard::make('main', 'Dashboard')` con esos widgets |
| Widgets declarados, `main` ya registrado por la aplicación | **Añade** mediante `addWidget()`, preservando tanto los widgets como el label que eligió la aplicación |

Notas:

- La sincronización se ejecuta después de `bootPanelPlugins()` del core, así que los widgets que un Plugin inyecta en su `boot()` también llegan al dashboard. Ese orden de arranque es lo que hace útil el puente para la Plugin API: un plugin añade widgets al dashboard de la aplicación sin conocerlo ni reemplazarlo.
- Los widgets del Panel se añaden **después** de los que registró la aplicación. El orden final de renderizado no depende de eso: `Dashboard::resolve()` ordena por `Widget::getSort()`; el orden de inserción solo desempata entre widgets con el mismo `sort`.
- Las entradas que no son subclases de `Widget` se descartan en silencio — la validación vive en `Dashboard::addWidget()`, y una class-string mal configurada no debe tumbar el arranque del panel.
- En una aplicación multi-panel, los widgets de **todos** los panels convergen en el mismo dashboard `main`: hoy no existe un vínculo panel↔dashboard.
- Los dashboards con id propio se siguen registrando directamente en el `DashboardRegistry`; el puente solo toca `main`.

## Filtros

### `Arqel\Widgets\Filters\Filter` (abstract)

Filtro declarativo a nivel de dashboard, propagado al mapa de filtros de cada widget en el momento de `resolve()`. El constructor `(string $name)` es `final`; factory `Filter::make($name)`.

| Método | Tipo | Descripción |
|---|---|---|
| `label(string)` | `static` | Por defecto = `Str::of($name)->snake()->replace('_',' ')->title()` |
| `default(mixed)` | `static` | |
| `getName()` / `getLabel()` / `getDefault()` / `getType()` / `getComponent()` | getters | |
| `getResolvedDefault()` | `mixed` | El valor por defecto serializable (el mismo valor que emite `toArray()['default']`) — usa este, y no `getDefault()`, al inicializar un mapa de filtros |
| `toArray()` | `array` | `{ name, type, component, label, default, ...typeSpecificProps }` |

### `Filters\DateRangeFilter` (final)

`type='date_range'`, `component='DateRangeFilter'`. `defaultRange(?DateTimeInterface $from, ?DateTimeInterface $to): static` almacena el rango; `resolveDefault()` serializa ambos extremos como cadenas `Y-m-d` (`{from, to}`), de modo que un `DateTimeInterface` en bruto nunca filtre su forma de cast al cliente.

### `Filters\SelectFilter` (final)

`type='select'`, `component='SelectFilter'`. `options(array|Closure)` (la Closure se resuelve de forma perezosa en el momento de `toArray()`), `multiple(bool = true)`.

## HTTP

Registrado en `routes/admin.php` bajo `web` + `auth` + `HandleArqelInertiaRequests` (necesario para la prop compartida de la barra lateral/`panel.navigation`):

| Verbo | Ruta | Nombre | Controlador |
|---|---|---|---|
| GET | `/admin` | `arqel.dashboard.main` | `Http\Controllers\DashboardController::show` |
| GET | `/admin/dashboards/{dashboardId}` | `arqel.dashboard.show` | `Http\Controllers\DashboardController::show` |
| GET | `/admin/dashboards/{dashboardId}/widgets/{widgetId}/data` | `arqel.dashboard.widget-data` | `Http\Controllers\WidgetDataController::show` |

Ambos controladores ejecutan `abort_unless(Dashboard::canBeSeenBy($user), 403)` antes de seguir resolviendo; `WidgetDataController` verifica además `Widget::canBeSeenBy()` e inicializa los valores por defecto de los filtros del dashboard por debajo de los valores de filtro suministrados en la petición, antes de llamar a `data()`.

## Comandos de Artisan

| Comando | Función |
|---|---|
| `arqel:widget <Name> --type=stat\|chart\|table\|custom --force` | Genera `app/Widgets/<Name>.php`. `stat`/`chart`/`table` generan una subclase `final`; `custom` genera una factory `final` que compone `CustomWidget::make()` |
| `arqel:dashboard <Name> --id=<custom> --force` | Genera `app/Dashboards/<Name>.php` con una factory estática `make(): Dashboard` |

## Ejemplo

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

## Relacionado

- SKILL: [`packages/widgets/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/widgets/SKILL.md)
- Código fuente: [`packages/widgets/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/widgets/src/)

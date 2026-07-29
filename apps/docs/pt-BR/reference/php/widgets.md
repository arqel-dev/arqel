# `arqel-dev/widgets` — Referência de API

Namespace `Arqel\Widgets\`. Sistema de widgets de dashboard: cards de KPI (Stat), gráficos (Chart), mini-tabelas (Table) e uma escape hatch para componentes React arbitrários (Custom). Suporta polling, carregamento adiado, visibilidade por usuário e filtros declarativos compartilhados entre os widgets de um dashboard.

## `Arqel\Widgets\Widget` (abstract)

Class base para todos os tipos de widget. Construtor: `(string $name)`.

| Método | Tipo | Descrição |
|---|---|---|
| `heading(string)` / `description(string)` | `static` | Elementos visuais |
| `sort(int)` | `static` | Ordenação dentro do grid do dashboard |
| `columnSpan(int\|string)` | `static` | `1..12`, ou uma string abreviada (`'full'`, `'1/2'`) |
| `poll(int $seconds)` | `static` | Intervalo de refetch no cliente; `0` ou negativo desabilita o polling |
| `deferred(bool = true)` | `static` | Quando true, `toArray()` emite `data: null` e o cliente busca os dados de forma lazy via `WidgetDataController` |
| `canSee(Closure)` | `static` | Recebe `?Authenticatable`, retorna bool |
| `filters(array<string, mixed>)` | `static` | Inicializa/sobrescreve o mapa de filtros do widget |
| `data()` | `array` (abstract) | Payload por renderização — implementado pelas subclasses |
| `getName()` / `getType()` / `getComponent()` / `getHeading()` / `getDescription()` / `getSort()` / `getColumnSpan()` / `getPollingInterval()` / `isDeferred()` | getters | |
| `filterValue(string $name, mixed $default = null)` | `mixed` | Leitor canônico de filtro dentro de `data()` |
| `getFilters()` | `array<string, mixed>` | |
| `id()` | `string` | Default `<type>:<name>` |
| `canBeSeenBy(?Authenticatable $user)` | `bool` | `true` quando nenhum `canSee` foi definido |
| `toArray(?Authenticatable $user = null)` | `array` | Payload do Inertia: `{ id, name, type, component, heading, description, sort, columnSpan, poll, deferred, filters, data }` |

As subclasses declaram `protected string $type` (snake_case) e `protected string $component` (nome do componente React em PascalCase).

### `StatWidget` (não é final — subclasse gerada por `arqel:widget --type=stat`)

Card de KPI. Factory `StatWidget::make($name)`.

| Método | Tipo | Descrição |
|---|---|---|
| `value(mixed)` | `self` | Escalar ou `Closure(): scalar`, resolvido no momento do `data()` |
| `statDescription(mixed)` | `self` | Linha secundária (por exemplo, `'+12% vs last week'`); string ou Closure |
| `descriptionIcon(string)` / `icon(string)` | `self` | |
| `color(string)` | `self` | Um entre `primary\|secondary\|success\|warning\|danger\|info` (constantes `COLOR_*`); valores desconhecidos caem em `primary` |
| `chart(mixed)` | `self` | `array<int\|float>` ou Closure — pontos do sparkline |
| `url(string)` | `self` | Renderiza o card como um link quando definido |

### `ChartWidget` (não é final — subclasse gerada por `arqel:widget --type=chart`)

Serializa a configuração do Recharts; a renderização acontece no cliente. Factory `ChartWidget::make($name)`.

| Método | Tipo | Descrição |
|---|---|---|
| `chartType(string)` | `self` | Um entre `line\|bar\|area\|pie\|donut\|radar` (constantes `CHART_*`) |
| `height(int)` | `self` | Pixels, mínimo 50 |
| `showLegend(bool = true)` / `showGrid(bool = true)` | `self` | |
| `chartData(array\|Closure)` | `self` | `{ labels, datasets: [{label, data, color}, ...] }` |
| `chartOptions(array\|Closure)` | `self` | |
| `getChartType()` / `getHeight()` / `isLegendVisible()` / `isGridVisible()` | getters | |

### `TableWidget` (não é final — subclasse gerada por `arqel:widget --type=table`)

Mini-tabela; intencionalmente não tem dependência rígida de `arqel-dev/table` (as columns são duck-typed via `toArray()`). Factory `TableWidget::make($name)`.

| Método | Tipo | Descrição |
|---|---|---|
| `query(Closure(): Builder)` | `self` | Precisa retornar um `Builder` do Eloquent (ou um objeto com o mesmo formato) |
| `limit(int)` | `self` | Mínimo 1, default 10 |
| `columns(array)` | `self` | Objetos que expõem `toArray()`; os demais são descartados silenciosamente |
| `seeAllUrl(string\|Closure\|null)` | `self` | |

Erros lançados dentro da Closure de `query` são capturados e expostos como `loadError` no payload, em vez de derrubar o dashboard.

### `CustomWidget` (final)

Escape hatch para componentes React arbitrários — composto via `make()`, nunca por subclasse.

| Método | Tipo | Descrição |
|---|---|---|
| `CustomWidget::make(string $name, string $component)` | `self` | Factory |
| `component(string)` | `self` | Lança `InvalidArgumentException` com string vazia |
| `withData(array\|Closure)` | `self` | Payload emitido para o componente React; note que o setter é `withData()`, e não `data()` (as regras de LSP do PHP impedem estreitar o tipo de retorno de `data(): array`) |

## `Arqel\Widgets\Dashboard` (final)

Schema declarativo de dashboard: uma lista de widgets + layout compartilhado. Factory `Dashboard::make(string $id, string $label, ?string $path = null)`.

| Método | Tipo | Descrição |
|---|---|---|
| `widgets(array<Widget\|class-string<Widget>>)` | `self` | Class-strings resolvidas pelo container no momento do `resolve()`; entradas inválidas são descartadas silenciosamente |
| `addWidget(Widget\|class-string<Widget>)` | `self` | |
| `columns(int\|array<string, int>)` | `self` | Valor plano `1..12`, ou um mapa responsivo indexado por `sm\|md\|lg\|xl\|2xl`, cada um limitado a `1..12` |
| `heading(string)` / `description(string)` | `self` | |
| `filters(array)` | `self` | Modo duplo: passagem direta do formato legado `array<string, mixed>`, ou `list<Filter>` declarativo (detectado pela presença de qualquer instância de `Filter`) |
| `canSee(Closure)` | `self` | Recebe `?Authenticatable` |
| `canBeSeenBy(?Authenticatable $user)` | `bool` | |
| `getWidgets()` / `getColumns()` / `getHeading()` / `getDescription()` / `getFilters()` / `getDeclaredFilters()` / `getFilterDefaults()` | getters | |
| `resolve(?Authenticatable $user = null)` | `array` | Resolve os widgets declarados como class-string, filtra por `canBeSeenBy`, ordena por `getSort()`, mescla os defaults de filtro do dashboard em cada widget e retorna `{ id, label, path, widgets, filters, columns, heading, description }` |
| `toArray(?Authenticatable $user = null)` | `array` | Alias de `resolve()` |
| `findWidget(string $widgetId)` | `?Widget` | Busca por `id()`; a autorização intencionalmente não é aplicada aqui (quem chama distingue 404 de 403) |

## `Arqel\Widgets\DashboardRegistry` (final, singleton)

`register(Dashboard)` (lança `InvalidArgumentException` em id duplicado — sem sobrescrita silenciosa), `has(id)`, `get(id): ?Dashboard`, `all(): array<string, Dashboard>`, `clear()`.

## `Arqel\Widgets\WidgetRegistry` (final, singleton)

`register(string $type, class-string<Widget> $widgetClass)` (valida `is_subclass_of(Widget::class)`), `has(type)`, `get(type): ?class-string<Widget>`, `all()`, `clear()`.

## Bridge Panel → Dashboard

Widgets declarados num Panel chegam ao dashboard renderizado. O `WidgetsServiceProvider` defere um sync para o `app->booted()` que coleta o `Panel::getWidgets()` de **todos** os panels registrados e o funde no dashboard de id `main` — o id que o `DashboardController::show()` usa como fallback para a rota `/admin`.

```php
// Um ServiceProvider, ou o register()/boot() de um Plugin
Panel::make('admin')->widgets([
    TotalUsersWidget::class,
    RevenueChartWidget::class,
]);
```

O sync é **aditivo**, nunca destrutivo:

| Situação | Comportamento |
|---|---|
| Nenhum panel declara widgets | No-op — nenhum dashboard fantasma é registrado |
| Widgets declarados, sem dashboard `main` registrado | Cria `Dashboard::make('main', 'Dashboard')` contendo esses widgets |
| Widgets declarados, `main` já registrado pela aplicação | **Acrescenta** via `addWidget()`, preservando tanto os widgets quanto o label que a aplicação escolheu |

Observações:

- O sync roda depois do `bootPanelPlugins()` do core, então widgets que um Plugin injeta no seu `boot()` ainda chegam ao dashboard. É essa ordem de boot que torna o bridge útil para o Plugin API: um plugin acrescenta widgets ao dashboard da aplicação sem precisar conhecê-lo nem substituí-lo.
- Os widgets do Panel são acrescentados **depois** dos que a aplicação registrou. A ordem final de renderização não depende disso: o `Dashboard::resolve()` ordena por `Widget::getSort()`; a ordem de inserção é apenas o desempate entre widgets de mesmo `sort`.
- Entradas que não são subclasses de `Widget` são descartadas em silêncio — a validação vive no `Dashboard::addWidget()`, e uma class-string mal configurada não pode derrubar o boot do painel.
- Numa aplicação multi-panel, os widgets de **todos** os panels convergem para o mesmo dashboard `main`: hoje não existe vínculo panel↔dashboard.
- Dashboards com id próprio continuam sendo registrados direto no `DashboardRegistry`; o bridge só toca no `main`.

## Filtros

### `Arqel\Widgets\Filters\Filter` (abstract)

Filtro declarativo no nível do dashboard, propagado para o mapa de filtros de cada widget no momento do `resolve()`. O construtor `(string $name)` é `final`; factory `Filter::make($name)`.

| Método | Tipo | Descrição |
|---|---|---|
| `label(string)` | `static` | Default = `Str::of($name)->snake()->replace('_',' ')->title()` |
| `default(mixed)` | `static` | |
| `getName()` / `getLabel()` / `getDefault()` / `getType()` / `getComponent()` | getters | |
| `getResolvedDefault()` | `mixed` | O default serializável (o mesmo valor que `toArray()['default']` emite) — use este, e não `getDefault()`, ao inicializar um mapa de filtros |
| `toArray()` | `array` | `{ name, type, component, label, default, ...typeSpecificProps }` |

### `Filters\DateRangeFilter` (final)

`type='date_range'`, `component='DateRangeFilter'`. `defaultRange(?DateTimeInterface $from, ?DateTimeInterface $to): static` armazena o intervalo; `resolveDefault()` serializa ambas as extremidades como strings `Y-m-d` (`{from, to}`), de modo que um `DateTimeInterface` bruto nunca vaze seu formato de cast para o cliente.

### `Filters\SelectFilter` (final)

`type='select'`, `component='SelectFilter'`. `options(array|Closure)` (a Closure é resolvida de forma lazy no momento do `toArray()`), `multiple(bool = true)`.

## HTTP

Registrado em `routes/admin.php` sob `web` + `auth` + `HandleArqelInertiaRequests` (necessário para a shared prop da sidebar/`panel.navigation`):

| Verbo | Route | Nome | Controller |
|---|---|---|---|
| GET | `/admin` | `arqel.dashboard.main` | `Http\Controllers\DashboardController::show` |
| GET | `/admin/dashboards/{dashboardId}` | `arqel.dashboard.show` | `Http\Controllers\DashboardController::show` |
| GET | `/admin/dashboards/{dashboardId}/widgets/{widgetId}/data` | `arqel.dashboard.widget-data` | `Http\Controllers\WidgetDataController::show` |

Ambos os controllers fazem `abort_unless(Dashboard::canBeSeenBy($user), 403)` antes de resolver qualquer outra coisa; o `WidgetDataController` verifica adicionalmente `Widget::canBeSeenBy()` e injeta os defaults de filtro do dashboard sob os valores de filtro vindos da requisição, antes de chamar `data()`.

## Comandos Artisan

| Comando | Função |
|---|---|
| `arqel:widget <Name> --type=stat\|chart\|table\|custom --force` | Gera `app/Widgets/<Name>.php`. `stat`/`chart`/`table` geram uma subclasse `final`; `custom` gera uma factory `final` compondo `CustomWidget::make()` |
| `arqel:dashboard <Name> --id=<custom> --force` | Gera `app/Dashboards/<Name>.php` com uma factory estática `make(): Dashboard` |

## Exemplo

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

## Relacionados

- SKILL: [`packages/widgets/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/widgets/SKILL.md)
- Código-fonte: [`packages/widgets/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/widgets/src/)

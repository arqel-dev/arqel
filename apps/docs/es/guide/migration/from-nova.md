# Migrando de Laravel Nova a Arqel

> Guía de migración con 12 mappings side-by-side. Para el índice general
> y el árbol de decisión, ver [`README.md`](./README.md).

## Por qué migrar

Laravel Nova está pulido, es oficial y tiene licencia comercial — tres
razones fuertes para seguir usándolo si el equipo está contento. Arqel
empieza a tener sentido cuando el equipo quiere **(a)** control MIT + open-source,
**(b)** React/TypeScript en lugar del stack Vue+Inertia históricamente
sabor Nova, y **(c)** libertad para personalizar la UI sin pelearse con
Cards/Resource Tools propietarios. La tabla de abajo resume.

| Criterio                          | Laravel Nova           | Arqel                       |
|-----------------------------------|------------------------|-----------------------------|
| Licencia                          | Comercial (de pago)    | MIT                         |
| Capa de render                    | Inertia + Vue          | Inertia + React             |
| Personalización UI                | Componentes Vue + Tools | React + shadcn CLI v4 (new-york) sobre Radix UI |
| Tipado del cliente                | JS (TS opcional)       | TypeScript strict requerido |
| Lenses                            | Built-in               | Mapean a vistas con scope   |
| Cards                             | Built-in (Vue)         | Widgets (`arqel-dev/widgets`) |
| Métricas (Value/Trend/Partition)  | Built-in               | StatWidget + ChartWidget    |
| Multi-tenancy                     | DIY                    | `arqel-dev/tenant`          |
| Tipos de Field built-in           | ~30                    | ~25 (core + advanced)       |
| Plugins de la comunidad           | Maduro                 | En construcción (v0.8)      |
| Migración a Laravel Cloud         | Ambos soportados       | Idéntica                    |

**Cuándo NO migrar:** si el equipo depende de Resource Tools/Cards
propietarios sin equivalentes open-source, o si Vue es el stack
canónico de la empresa.

## Matriz de decisión

| Feature                | Nova                                     | Arqel                                     | Notas                       |
|------------------------|------------------------------------------|-------------------------------------------|-----------------------------|
| Resource               | `Laravel\Nova\Resource`                  | `Arqel\Core\Resources\Resource`           | API similar                 |
| Fields                 | `Text::make`/`Select::make`/etc.         | `FieldFactory::text`/`select`/etc.        | Pequeños renombres          |
| Filters                | `Lenses\Filters\Filter`                  | `Arqel\Table\Filters\*`                   | 6 tipos listos              |
| Lenses                 | clase `Lens`                             | Vistas con scope (Resource + ruta custom) | Manual                      |
| Actions                | `Actions\Action`                         | `RowAction`/`HeaderAction`/`BulkAction`   | 3 tipos explícitos          |
| Cards                  | `Cards\Card`                             | `Arqel\Widgets\Widget` + subtipos         | Stat/Chart/Table/Custom     |
| Métrica: Value         | `Metrics\Value`                          | `StatWidget`                              | Directo                     |
| Métrica: Trend         | `Metrics\Trend`                          | `ChartWidget` (line/area)                 | Directo                     |
| Métrica: Partition     | `Metrics\Partition`                      | `ChartWidget` (pie/donut)                 | Directo                     |
| Resource policies      | Laravel Gates                            | Laravel Gates                             | Idénticas                   |
| Tools                  | `Nova\Tool` (Vue)                        | Página Inertia personalizada (React)      | Reescritura                 |
| Field personalizado    | Vue SFC                                  | Componente React + clase `Field` PHP      | Reescritura                 |
| File uploads           | Field `File`/`Image`                     | `FileField`/`ImageField`                  | Directo                     |
| Multi-tenancy          | DIY (con `Stancl` o similar)             | Adapters `arqel-dev/tenant`               | Paquete nativo              |
| Action queueing        | `ShouldQueue` (Laravel)                  | `ShouldQueue` (Laravel)                   | Idéntico                    |
| Search                 | Scout o `searchable`                     | Columna `searchable()` + Scout opcional   |                             |
| Inline create          | Modal built-in                           | Pendiente Fase 3                          | Usa la ruta create por ahora |
| Repeatable fields      | Plugins de la comunidad                  | `RepeaterField`/`BuilderField`            | `arqel-dev/fields-advanced` |

## Side-by-side: 12 patrones

### 1. Declaración de Resource

**Nova**:

```php
namespace App\Nova;

use Laravel\Nova\Resource;

class Post extends Resource
{
    public static $model = \App\Models\Post::class;
    public static $title = 'title';
    public static $search = ['id', 'title'];
}
```

**Arqel**:

```php
namespace App\Arqel\Resources;

use Arqel\Core\Resources\Resource;

final class PostResource extends Resource
{
    public static string $model = \App\Models\Post::class;
    public static ?string $recordTitleAttribute = 'title';
    // search se declara vía column->searchable() en table()
}
```

> Diferencias: el nombre de la clase incluye el sufijo `Resource` (convención Arqel).
> `$title` se vuelve `$recordTitleAttribute`. La búsqueda migra a nivel de columna.

### 2. Fields del Resource

**Nova**:

```php
public function fields(NovaRequest $request): array
{
    return [
        ID::make()->sortable(),
        Text::make('Title')->rules('required', 'max:255'),
        Select::make('Status')->options(['draft' => 'Draft', 'published' => 'Published']),
        BelongsTo::make('Author', 'user', User::class),
        Date::make('Published At'),
    ];
}
```

**Arqel**:

```php
use Arqel\Fields\FieldFactory as F;

public function fields(): array
{
    return [
        F::text('title')->required()->maxLength(255),
        F::select('status')->options(['draft' => 'Draft', 'published' => 'Published']),
        F::belongsTo('user_id', 'user', 'name'),
        F::date('published_at'),
    ];
}
```

> Diferencias: keys snake_case (atributos directos de Eloquent).
> `BelongsTo` en Arqel toma (foreign_key, relation, attribute_label).
> El ID se renderiza automáticamente — no lo declares.

### 3. Columnas de tabla (Nova: config de field index)

**Nova** (el mismo `fields()` controla index/detail/forms vía visibilidad):

```php
Text::make('Title')->onlyOnIndex()->sortable(),
Text::make('Body')->onlyOnDetail()->asHtml(),
```

**Arqel** (separación clara: `fields()` para form, `table()` para index):

```php
use Arqel\Table\Table;
use Arqel\Table\Columns\{TextColumn, BadgeColumn, DateColumn};

public function table(): Table
{
    return Table::make()->columns([
        TextColumn::make('title')->sortable()->searchable()->limit(60),
        BadgeColumn::make('status')->colors(['draft' => 'gray', 'published' => 'green']),
        DateColumn::make('published_at')->displayFormat('d/m/Y'),
    ]);
}
```

> Nova condensa todo en `fields()` con flags de visibilidad. Arqel
> divide en `fields()` (forms) + `table()` (index) — más explícito, menos magia.

### 4. Filtros

**Nova**:

```php
class Status extends Filter
{
    public function apply(NovaRequest $request, $query, $value)
    { return $query->where('status', $value); }

    public function options(NovaRequest $request)
    { return ['Draft' => 'draft', 'Published' => 'published']; }
}
```

**Arqel** (declarativo, sin clase personalizada):

```php
use Arqel\Table\Filters\SelectFilter;

SelectFilter::make('status')
    ->options(['draft' => 'Draft', 'published' => 'Published'])
    ->apply(fn ($query, $value) => $query->where('status', $value));
```

> Para filtros complejos con múltiples constraints, usa `QueryBuilderFilter`
> con `TextConstraint`/`NumberConstraint`/`DateConstraint` (TABLE-V2-003).

### 5. Lenses → Vistas con scope

Las Lenses de Nova son "vistas alternativas del recurso" con query
personalizado + columnas personalizadas. Arqel no tiene un concepto 1:1 — porta cada Lens como **un
Resource adicional o una ruta personalizada**.

**Nova Lens**:

```php
class MostValuableCustomers extends Lens
{
    public static function query(LensRequest $request, $query) { /* ... */ }
    public function fields(Request $request) { /* ... */ }
}
```

**Arqel** (opción A — Resource con scope):

```php
final class MostValuableCustomersResource extends Resource
{
    public static string $model = Customer::class;

    public function indexQuery(): Builder
    { return Customer::query()->where('lifetime_value', '>', 10000); }

    public function table(): Table { /* columnas dedicadas */ }
}
```

**Arqel** (opción B — ruta personalizada + página Inertia) para casos con
lógica compleja fuera del CRUD.

### 6. Actions

**Nova**:

```php
class PublishPost extends Action
{
    public function handle(ActionFields $fields, Collection $models)
    { $models->each->update(['status' => 'published']); }
}
```

**Arqel**:

```php
use Arqel\Actions\Types\{RowAction, BulkAction};

RowAction::make('publish')
    ->label('Publish')
    ->action(fn ($record) => $record->update(['status' => 'published']));

BulkAction::make('publish_bulk')
    ->label('Publish selected')
    ->execute(fn (Collection $records) => $records->each->update(['status' => 'published']))
    ->chunkSize(100);
```

> Diferencia: Nova unifica row/bulk. Arqel divide explícitamente —
> bulk recibe un `Collection` chunked, row recibe un `Model`.

### 7. Cards → Widgets (overview)

**Nova**:

```php
public function cards(NovaRequest $request): array
{
    return [new TotalCustomers, new RevenueChart];
}

class TotalCustomers extends Card { protected $width = '1/3'; /* componente Vue */ }
```

**Arqel**:

```php
use Arqel\Widgets\Dashboard;

Dashboard::make('main', 'Overview')
    ->widgets([TotalCustomers::class, RevenueChart::class])
    ->columns(3);
```

> Diferencia: Nova vincula cards al Resource. Arqel los divide en un
> `Dashboard` dedicado, registrado en el Panel.

### 8. Métricas → Stat widgets

**Nova Value metric**:

```php
class TotalRevenue extends Value
{
    public function calculate(NovaRequest $request)
    { return $this->result(Order::sum('total')); }
}
```

**Arqel `StatWidget`**:

```php
use Arqel\Widgets\StatWidget;

final class TotalRevenue extends StatWidget
{
    protected string $type = 'total_revenue';
    protected string $component = 'StatWidget';

    public function data(): array
    {
        return ['label' => 'Total Revenue', 'value' => Order::sum('total')];
    }
}
```

**Trend metric Nova → `ChartWidget` (line)**:

```php
final class RevenueTrend extends ChartWidget
{
    protected string $type = 'revenue_trend';
    protected string $component = 'ChartWidget';

    public function data(): array
    {
        return [
            'kind'   => 'line',
            'labels' => $this->last30Days(),
            'series' => [['name' => 'Revenue', 'data' => $this->revenueByDay()]],
        ];
    }
}
```

### 9. Policies / Autorización

Idéntico en ambos — Laravel Gates. Migración = cero código.

```php
public function update(User $user, Post $post): bool
{ return $user->id === $post->user_id; }
```

> Arqel filtra acciones per-row automáticamente vía `Action::canBeExecutedBy`
> (TABLE-007); Nova lo hace vía `authorizedToRun`/`authorizedToView`. Con
> una Policy limpia, ambos funcionan sin wiring extra.

### 10. Multi-tenancy

Nova no tiene multi-tenancy nativo — integras `stancl/tenancy` o
similar manualmente. Arqel incluye `arqel-dev/tenant` con adapters listos:

```php
// config/arqel.php
'tenancy' => [
    'resolver'    => Arqel\Tenant\Resolvers\AuthUserResolver::class, // estilo currentTeam
    'model'       => App\Models\Team::class,
    'foreign_key' => 'team_id',
],

// Modelos tenanted:
class Post extends Model { use \Arqel\Tenant\Concerns\BelongsToTenant; }

// Rutas:
Route::middleware(['web', 'auth', 'arqel.tenant'])->group(function () { /* ... */ });
```

> Validación tenant-aware: cambia `Rule::unique` por `new ScopedUnique('posts', 'slug')`.

### 11. File uploads

**Nova**:

```php
File::make('Attachment')->disk('s3')->path('attachments');
Image::make('Cover')->disk('s3')->path('covers');
```

**Arqel**:

```php
use Arqel\Fields\FieldFactory as F;

F::file('attachment')->disk('s3')->directory('attachments');
F::image('cover')->disk('s3')->directory('covers');
```

> Diferencia mínima: `path()` se vuelve `directory()`.

### 12. Tools / Páginas personalizadas

Las Tools de Nova son componentes Vue con integración server-side de menú. Arqel
no tiene un concepto 1:1 — implémentalas como **controller + página Inertia +
componente React**, luego añade al panel vía Panel `widgets()` o una
ruta personalizada.

**Nova Tool**:

```bash
php artisan nova:tool acme/analytics-tool
# genera SFC Vue + ToolServiceProvider
```

**Arqel** (manual):

```php
// app/Http/Controllers/AnalyticsController.php
final class AnalyticsController
{
    public function __invoke(): \Inertia\Response
    { return inertia('Analytics', ['stats' => /* ... */]); }
}

// resources/js/Pages/Analytics.tsx — componente React personalizado
// routes/admin.php
Route::get('/admin/analytics', AnalyticsController::class)->name('admin.analytics');
```

## Checklist paso a paso

1. [ ] Audita Models, Migrations, Policies — **no cambian**.
2. [ ] Instala Arqel: `composer require arqel-dev/arqel` seguido de `php artisan arqel:install` (instala core + table + actions + fields, publica configs y el tema shadcn (new-york) sobre Radix UI).
3. [ ] Configura un panel paralelo en `/admin-v2` (coexistiendo con Nova).
4. [ ] Migra primero los Resources simples; renombra clases con el sufijo `Resource`.
5. [ ] Divide `fields()` de Nova en `fields()` (forms) + `table()` (index) en Arqel.
6. [ ] Porta los Filtros: clases custom de Nova → `Arqel\Table\Filters\*` declarativos.
7. [ ] Mapea Lenses → Resources con scope o rutas personalizadas.
8. [ ] Migra Cards/Métricas → `arqel-dev/widgets` (`StatWidget`/`ChartWidget`).
9. [ ] Reescribe las Tools Vue como páginas Inertia React.
10. [ ] Configura `arqel-dev/tenant` si aplica; abandona Nova; promueve `/admin-v2` a `/admin`.

## Pitfalls comunes

1. **`fields()` no controla index/detail/forms al mismo tiempo.** En Arqel, `fields()` es solo para forms. Usa `table()` para columnas del index. Esta división suele sorprender a quienes vienen de Nova.
2. **`ID::make()` no existe.** La columna primaria se renderiza automáticamente; declárala solo si quieres ordering personalizado u ocultarla.
3. **Las Lenses no tienen concepto 1:1.** Evalúa cada Lens: si es una "vista alternativa del mismo Resource", crea otro Resource con un `indexQuery()` personalizado. Si es un dashboard analítico, se vuelve un Widget.
4. **Vue Tools → reescritura React.** No existe traductor — los SFC Vue con `<script setup>` necesitan volverse `.tsx`. Esto es típicamente el trabajo más demandante.
5. **El queueing de Action es igual pero la UI de input cambia.** Arqel todavía no tiene el "modal de confirmación con fields" built-in de Nova (planificado Fase 2); por ahora, captura inputs vía un form React personalizado y POSTea al endpoint del action.

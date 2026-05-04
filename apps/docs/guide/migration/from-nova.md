# Migrando de Laravel Nova para Arqel

> Guia de migração com 12 mapeamentos side-by-side. Para o índice geral
> e a árvore de decisão, veja [`README.md`](./README.md).

## Por que migrar

Laravel Nova é polido, oficial e tem licenciamento pago — três motivos
fortes para mantê-lo se a equipe está satisfeita. Arqel passa a fazer
sentido quando a equipe quer **(a)** controle MIT + open-source, **(b)**
React/TypeScript em vez do stack Vue+Inertia historicamente Nova-flavored,
e **(c)** liberdade para customizar a UI sem fights com Cards/Resource
Tools proprietários. A tabela a seguir resume.

| Critério                          | Laravel Nova           | Arqel                       |
|-----------------------------------|------------------------|-----------------------------|
| Licença                           | Comercial (paga)       | MIT                         |
| Render layer                      | Inertia + Vue          | Inertia + React             |
| Customização de UI                | Vue components + Tools | React + ShadCN/Base UI      |
| Tipagem do client                 | JS (TS opcional)       | TypeScript strict obrigatório |
| Lenses                            | Built-in               | Mapear para scoped views    |
| Cards                             | Built-in (Vue)         | Widgets (`arqel-dev/widgets`)   |
| Metrics (Value/Trend/Partition)   | Built-in               | StatWidget + ChartWidget    |
| Multi-tenancy                     | DIY                    | `arqel-dev/tenant`              |
| Field types builtin               | ~30                    | ~25 (core + advanced)       |
| Plugins comunitários              | Maduros                | Em construção (v0.8)        |
| Migração para Laravel Cloud       | Ambos suportam         | Idêntico                    |

**Quando NÃO migrar:** se o time depende de Resource Tools/Cards
proprietários sem equivalentes open-source, ou se Vue é a stack canônica
da empresa.

## Matriz de decisão

| Feature                | Nova                                     | Arqel                                     | Notas                       |
|------------------------|------------------------------------------|-------------------------------------------|-----------------------------|
| Resource               | `Laravel\Nova\Resource`                  | `Arqel\Core\Resources\Resource`           | API similar                 |
| Fields                 | `Text::make`/`Select::make`/etc.         | `FieldFactory::text`/`select`/etc.        | Renomes pequenos            |
| Filters                | `Lenses\Filters\Filter`                  | `Arqel\Table\Filters\*`                   | 6 tipos prontos             |
| Lenses                 | `Lens` class                             | Scoped views (Resource + custom route)    | Manual                      |
| Actions                | `Actions\Action`                         | `RowAction`/`HeaderAction`/`BulkAction`   | 3 tipos explícitos          |
| Cards                  | `Cards\Card`                             | `Arqel\Widgets\Widget` + subtypes         | Stat/Chart/Table/Custom     |
| Metrics: Value         | `Metrics\Value`                          | `StatWidget`                              | Direto                      |
| Metrics: Trend         | `Metrics\Trend`                          | `ChartWidget` (line/area)                 | Direto                      |
| Metrics: Partition     | `Metrics\Partition`                      | `ChartWidget` (pie/donut)                 | Direto                      |
| Resource policies      | Laravel Gates                            | Laravel Gates                             | Idêntico                    |
| Tools                  | `Nova\Tool` (Vue)                        | Custom Inertia page (React)               | Reescrita                   |
| Custom Field           | Vue SFC                                  | React component + `Field` PHP class       | Reescrita                   |
| File uploads           | `File`/`Image` field                     | `FileField`/`ImageField`                  | Direto                      |
| Multi-tenancy          | DIY (com `Stancl` ou similar)            | `arqel-dev/tenant` adapters                   | Pacote nativo               |
| Action queueing        | `ShouldQueue` (Laravel)                  | `ShouldQueue` (Laravel)                   | Idêntico                    |
| Search                 | Scout ou `searchable`                    | Column `searchable()` + Scout opcional    |                             |
| Inline create          | Modal embutido                           | Pendente Phase 3                          | Use route create por enquanto |
| Repeatable fields      | Plugins comunitários                     | `RepeaterField`/`BuilderField`            | `arqel-dev/fields-advanced`     |

## Side-by-side: 12 padrões

### 1. Resource declaration

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
    // search é declarado via column->searchable() em table()
}
```

> Diferenças: nome da classe inclui sufixo `Resource` (convenção Arqel).
> `$title` vira `$recordTitleAttribute`. Search migra para column-level.

### 2. Resource fields

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

> Diferenças: snake_case nas keys (Eloquent attributes diretos).
> `BelongsTo` em Arqel recebe (foreign_key, relação, attribute_label).
> ID é renderizado automaticamente — não declare.

### 3. Table columns (Nova: index field config)

**Nova** (mesma `fields()` controla index/detail/forms via visibility):

```php
Text::make('Title')->onlyOnIndex()->sortable(),
Text::make('Body')->onlyOnDetail()->asHtml(),
```

**Arqel** (separação clara: `fields()` para form, `table()` para index):

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

> Nova condensa tudo em `fields()` com flags de visibility. Arqel separa
> em `fields()` (forms) + `table()` (index) — mais explícito, menos magia.

### 4. Filters

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

**Arqel** (declarativo, sem classe custom):

```php
use Arqel\Table\Filters\SelectFilter;

SelectFilter::make('status')
    ->options(['draft' => 'Draft', 'published' => 'Published'])
    ->apply(fn ($query, $value) => $query->where('status', $value));
```

> Para filters complexos com múltiplas constraints, use `QueryBuilderFilter`
> com `TextConstraint`/`NumberConstraint`/`DateConstraint` (TABLE-V2-003).

### 5. Lenses → Scoped views

Nova Lenses são "alternative resource views" com query custom + columns
custom. Arqel não tem conceito 1:1 — porte cada Lens como **um Resource
adicional ou uma rota custom**.

**Nova Lens**:

```php
class MostValuableCustomers extends Lens
{
    public static function query(LensRequest $request, $query) { /* ... */ }
    public function fields(Request $request) { /* ... */ }
}
```

**Arqel** (opção A — Resource scoped):

```php
final class MostValuableCustomersResource extends Resource
{
    public static string $model = Customer::class;

    public function indexQuery(): Builder
    { return Customer::query()->where('lifetime_value', '>', 10000); }

    public function table(): Table { /* columns dedicadas */ }
}
```

**Arqel** (opção B — rota custom + Inertia page) para casos com lógica
complexa fora do CRUD.

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

> Diferença: Nova unifica row/bulk. Arqel separa explicitamente —
> bulk recebe `Collection` chunked, row recebe `Model`.

### 7. Cards → Widgets (overview)

**Nova**:

```php
public function cards(NovaRequest $request): array
{
    return [new TotalCustomers, new RevenueChart];
}

class TotalCustomers extends Card { protected $width = '1/3'; /* Vue component */ }
```

**Arqel**:

```php
use Arqel\Widgets\Dashboard;

Dashboard::make('main', 'Overview')
    ->widgets([TotalCustomers::class, RevenueChart::class])
    ->columns(3);
```

> Diferença: Nova bind cards no Resource. Arqel separa em `Dashboard`
> dedicado, registrado no Panel.

### 8. Metrics → Stat widgets

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

**Nova Trend metric → `ChartWidget` (line)**:

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

### 9. Policies / Authorization

Idêntico em ambos — Laravel Gates. Migração = zero código.

```php
public function update(User $user, Post $post): bool
{ return $user->id === $post->user_id; }
```

> Arqel filtra ações por linha automaticamente via `Action::canBeExecutedBy`
> (TABLE-007); Nova faz via `authorizedToRun`/`authorizedToView`. Quando
> você tem Policy clean, ambos funcionam sem extra wiring.

### 10. Multi-tenancy

Nova não tem multi-tenancy nativo — você integra `stancl/tenancy` ou
similar manualmente. Arqel inclui `arqel-dev/tenant` com adapters prontos:

```php
// config/arqel.php
'tenancy' => [
    'resolver'    => Arqel\Tenant\Resolvers\AuthUserResolver::class, // currentTeam style
    'model'       => App\Models\Team::class,
    'foreign_key' => 'team_id',
],

// Models tenanted:
class Post extends Model { use \Arqel\Tenant\Concerns\BelongsToTenant; }

// Routes:
Route::middleware(['web', 'auth', 'arqel.tenant'])->group(function () { /* ... */ });
```

> Validação tenant-aware: troque `Rule::unique` por `new ScopedUnique('posts', 'slug')`.

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

> Diferença mínima: `path()` vira `directory()`.

### 12. Custom Tools / Pages

Nova Tools são Vue components com server-side menu integration. Arqel
não tem conceito 1:1 — implemente como **controller + Inertia page +
componente React**, depois adicione ao painel via Panel `widgets()` ou
rota custom.

**Nova Tool**:

```bash
php artisan nova:tool acme/analytics-tool
# gera Vue SFC + ToolServiceProvider
```

**Arqel** (manual):

```php
// app/Http/Controllers/AnalyticsController.php
final class AnalyticsController
{
    public function __invoke(): \Inertia\Response
    { return inertia('Analytics', ['stats' => /* ... */]); }
}

// resources/js/Pages/Analytics.tsx — componente React custom
// routes/admin.php
Route::get('/admin/analytics', AnalyticsController::class)->name('admin.analytics');
```

## Checklist passo-a-passo

1. [ ] Auditar Models, Migrations, Policies — **não mudam**.
2. [ ] Instalar Arqel: `composer require arqel-dev/core arqel-dev/table arqel-dev/actions arqel-dev/fields`.
3. [ ] Configurar painel paralelo em `/admin-v2` (coexistência com Nova).
4. [ ] Migrar Resources simples primeiro; renomear classes para sufixo `Resource`.
5. [ ] Separar `fields()` Nova em `fields()` (forms) + `table()` (index) Arqel.
6. [ ] Portar Filters: classes custom Nova → declarativo `Arqel\Table\Filters\*`.
7. [ ] Mapear Lenses → Resources scoped ou rotas custom.
8. [ ] Migrar Cards/Metrics → `arqel-dev/widgets` (`StatWidget`/`ChartWidget`).
9. [ ] Reescrever Tools Vue como Inertia pages React.
10. [ ] Configurar `arqel-dev/tenant` se aplicável; descartar Nova; promover `/admin-v2` para `/admin`.

## Pitfalls comuns

1. **`fields()` não controla index/detail/forms ao mesmo tempo.** Em Arqel, `fields()` é só para forms. Use `table()` para index columns. Esse split costuma surpreender quem vem de Nova.
2. **`ID::make()` não existe.** A coluna primária é renderizada automaticamente; declare apenas se quiser ordenação custom ou hidden.
3. **Lenses não têm conceito 1:1.** Avalie cada Lens: se é "vista alternativa do mesmo Resource", crie outro Resource com `indexQuery()` custom. Se é dashboard analítico, vira Widget.
4. **Tools Vue → reescrita React.** Não há tradutor — Vue SFC com `<script setup>` precisa virar `.tsx`. Esse é tipicamente o trabalho mais demorado.
5. **Action queueing é igual mas o input UI muda.** Arqel ainda não tem o "modal de confirmação com fields" do Nova builtin (planejado Phase 2); por enquanto, capture inputs via form custom no React e POST para action endpoint.

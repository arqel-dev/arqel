# Migrating from Laravel Nova to Arqel

> Migration guide with 12 side-by-side mappings. For the general index
> and the decision tree, see [`README.md`](./README.md).

## Why migrate

Laravel Nova is polished, official, and commercially licensed — three
strong reasons to keep using it if the team is happy. Arqel starts to
make sense when the team wants **(a)** MIT + open-source control,
**(b)** React/TypeScript instead of the historically Vue+Inertia
Nova-flavored stack, and **(c)** freedom to customize the UI without
fighting proprietary Cards/Resource Tools. The table below summarizes.

| Criteria                          | Laravel Nova           | Arqel                       |
|-----------------------------------|------------------------|-----------------------------|
| License                           | Commercial (paid)      | MIT                         |
| Render layer                      | Inertia + Vue          | Inertia + React             |
| UI customization                  | Vue components + Tools | React + shadcn CLI v4 (new-york) over Radix UI |
| Client typing                     | JS (TS optional)       | Strict TypeScript required  |
| Lenses                            | Built-in               | Map to scoped views         |
| Cards                             | Built-in (Vue)         | Widgets (`arqel-dev/widgets`) |
| Metrics (Value/Trend/Partition)   | Built-in               | StatWidget + ChartWidget    |
| Multi-tenancy                     | DIY                    | `arqel-dev/tenant`          |
| Built-in field types              | ~30                    | ~25 (core + advanced)       |
| Community plugins                 | Mature                 | Building (v0.8)             |
| Migration to Laravel Cloud        | Both supported         | Identical                   |

**When NOT to migrate:** if the team depends on proprietary Resource
Tools/Cards without open-source equivalents, or if Vue is the canonical
stack of the company.

## Decision matrix

| Feature                | Nova                                     | Arqel                                     | Notes                       |
|------------------------|------------------------------------------|-------------------------------------------|-----------------------------|
| Resource               | `Laravel\Nova\Resource`                  | `Arqel\Core\Resources\Resource`           | Similar API                 |
| Fields                 | `Text::make`/`Select::make`/etc.         | `FieldFactory::text`/`select`/etc.        | Minor renames               |
| Filters                | `Lenses\Filters\Filter`                  | `Arqel\Table\Filters\*`                   | 6 ready types               |
| Lenses                 | `Lens` class                             | Scoped views (Resource + custom route)    | Manual                      |
| Actions                | `Actions\Action`                         | `RowAction`/`HeaderAction`/`BulkAction`   | 3 explicit types            |
| Cards                  | `Cards\Card`                             | `Arqel\Widgets\Widget` + subtypes         | Stat/Chart/Table/Custom     |
| Metrics: Value         | `Metrics\Value`                          | `StatWidget`                              | Direct                      |
| Metrics: Trend         | `Metrics\Trend`                          | `ChartWidget` (line/area)                 | Direct                      |
| Metrics: Partition     | `Metrics\Partition`                      | `ChartWidget` (pie/donut)                 | Direct                      |
| Resource policies      | Laravel Gates                            | Laravel Gates                             | Identical                   |
| Tools                  | `Nova\Tool` (Vue)                        | Custom Inertia page (React)               | Rewrite                     |
| Custom Field           | Vue SFC                                  | React component + `Field` PHP class       | Rewrite                     |
| File uploads           | `File`/`Image` field                     | `FileField`/`ImageField`                  | Direct                      |
| Multi-tenancy          | DIY (with `Stancl` or similar)           | `arqel-dev/tenant` adapters               | Native package              |
| Action queueing        | `ShouldQueue` (Laravel)                  | `ShouldQueue` (Laravel)                   | Identical                   |
| Search                 | Scout or `searchable`                    | Column `searchable()` + optional Scout    |                             |
| Inline create          | Built-in modal                           | Pending Phase 3                           | Use route create for now    |
| Repeatable fields      | Community plugins                        | `RepeaterField`/`BuilderField`            | `arqel-dev/fields-advanced` |

## Side-by-side: 12 patterns

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
    // search is declared via column->searchable() in table()
}
```

> Differences: class name includes the `Resource` suffix (Arqel convention).
> `$title` becomes `$recordTitleAttribute`. Search migrates to column-level.

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

> Differences: snake_case keys (direct Eloquent attributes).
> `BelongsTo` in Arqel takes (foreign_key, relation, attribute_label).
> ID is rendered automatically — don't declare it.

### 3. Table columns (Nova: index field config)

**Nova** (the same `fields()` controls index/detail/forms via visibility):

```php
Text::make('Title')->onlyOnIndex()->sortable(),
Text::make('Body')->onlyOnDetail()->asHtml(),
```

**Arqel** (clear separation: `fields()` for form, `table()` for index):

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

> Nova condenses everything into `fields()` with visibility flags. Arqel
> splits into `fields()` (forms) + `table()` (index) — more explicit, less magic.

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

**Arqel** (declarative, no custom class):

```php
use Arqel\Table\Filters\SelectFilter;

SelectFilter::make('status')
    ->options(['draft' => 'Draft', 'published' => 'Published'])
    ->apply(fn ($query, $value) => $query->where('status', $value));
```

> For complex filters with multiple constraints, use `QueryBuilderFilter`
> with `TextConstraint`/`NumberConstraint`/`DateConstraint` (TABLE-V2-003).

### 5. Lenses → Scoped views

Nova Lenses are "alternative resource views" with a custom query +
custom columns. Arqel has no 1:1 concept — port each Lens as **an
additional Resource or a custom route**.

**Nova Lens**:

```php
class MostValuableCustomers extends Lens
{
    public static function query(LensRequest $request, $query) { /* ... */ }
    public function fields(Request $request) { /* ... */ }
}
```

**Arqel** (option A — scoped Resource):

```php
final class MostValuableCustomersResource extends Resource
{
    public static string $model = Customer::class;

    public function indexQuery(): Builder
    { return Customer::query()->where('lifetime_value', '>', 10000); }

    public function table(): Table { /* dedicated columns */ }
}
```

**Arqel** (option B — custom route + Inertia page) for cases with
complex logic outside CRUD.

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

> Difference: Nova unifies row/bulk. Arqel splits explicitly —
> bulk receives a chunked `Collection`, row receives a `Model`.

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

> Difference: Nova binds cards to the Resource. Arqel splits them into a
> dedicated `Dashboard`, registered on the Panel.

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

Identical in both — Laravel Gates. Migration = zero code.

```php
public function update(User $user, Post $post): bool
{ return $user->id === $post->user_id; }
```

> Arqel filters per-row actions automatically via `Action::canBeExecutedBy`
> (TABLE-007); Nova does it via `authorizedToRun`/`authorizedToView`. With
> a clean Policy, both work without extra wiring.

### 10. Multi-tenancy

Nova has no native multi-tenancy — you integrate `stancl/tenancy` or
similar manually. Arqel includes `arqel-dev/tenant` with ready adapters:

```php
// config/arqel.php
'tenancy' => [
    'resolver'    => Arqel\Tenant\Resolvers\AuthUserResolver::class, // currentTeam style
    'model'       => App\Models\Team::class,
    'foreign_key' => 'team_id',
],

// Tenanted models:
class Post extends Model { use \Arqel\Tenant\Concerns\BelongsToTenant; }

// Routes:
Route::middleware(['web', 'auth', 'arqel.tenant'])->group(function () { /* ... */ });
```

> Tenant-aware validation: swap `Rule::unique` for `new ScopedUnique('posts', 'slug')`.

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

> Minimal difference: `path()` becomes `directory()`.

### 12. Custom Tools / Pages

Nova Tools are Vue components with server-side menu integration. Arqel
has no 1:1 concept — implement them as **controller + Inertia page +
React component**, then add to the panel via Panel `widgets()` or a
custom route.

**Nova Tool**:

```bash
php artisan nova:tool acme/analytics-tool
# generates Vue SFC + ToolServiceProvider
```

**Arqel** (manual):

```php
// app/Http/Controllers/AnalyticsController.php
final class AnalyticsController
{
    public function __invoke(): \Inertia\Response
    { return inertia('Analytics', ['stats' => /* ... */]); }
}

// resources/js/Pages/Analytics.tsx — custom React component
// routes/admin.php
Route::get('/admin/analytics', AnalyticsController::class)->name('admin.analytics');
```

## Step-by-step checklist

1. [ ] Audit Models, Migrations, Policies — **they don't change**.
2. [ ] Install Arqel: `composer require arqel-dev/arqel` followed by `php artisan arqel:install` (installs core + table + actions + fields, publishes configs and the shadcn (new-york) theme over Radix UI).
3. [ ] Configure a parallel panel at `/admin-v2` (coexisting with Nova).
4. [ ] Migrate simple Resources first; rename classes with the `Resource` suffix.
5. [ ] Split Nova `fields()` into `fields()` (forms) + `table()` (index) in Arqel.
6. [ ] Port Filters: Nova custom classes → declarative `Arqel\Table\Filters\*`.
7. [ ] Map Lenses → scoped Resources or custom routes.
8. [ ] Migrate Cards/Metrics → `arqel-dev/widgets` (`StatWidget`/`ChartWidget`).
9. [ ] Rewrite Vue Tools as React Inertia pages.
10. [ ] Configure `arqel-dev/tenant` if applicable; drop Nova; promote `/admin-v2` to `/admin`.

## Common pitfalls

1. **`fields()` does not control index/detail/forms at the same time.** In Arqel, `fields()` is only for forms. Use `table()` for index columns. This split usually surprises folks coming from Nova.
2. **`ID::make()` does not exist.** The primary column is rendered automatically; declare it only if you want custom ordering or to hide it.
3. **Lenses have no 1:1 concept.** Evaluate each Lens: if it's an "alternative view of the same Resource", create another Resource with a custom `indexQuery()`. If it's an analytical dashboard, it becomes a Widget.
4. **Vue Tools → React rewrite.** No translator exists — Vue SFCs with `<script setup>` need to become `.tsx`. This is typically the most time-consuming work.
5. **Action queueing is the same but the input UI changes.** Arqel doesn't yet have Nova's built-in "confirmation modal with fields" (planned Phase 2); for now, capture inputs via a custom React form and POST to the action endpoint.

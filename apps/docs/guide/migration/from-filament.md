# Migrating from Filament v3 to Arqel

> Migration guide with 12 side-by-side mappings. For the general index
> and the decision tree, see [`README.md`](./README.md).

## Why migrate

Filament v3 is mature and productive, especially for apps heavily
focused on forms. Arqel is a better choice when the team **already
invests in React** and wants a single Laravel + Inertia + React stack
(without the Livewire/Alpine cognitive jump). The table below
summarizes the trade-offs.

| Criteria                          | Filament v3            | Arqel                       |
|-----------------------------------|------------------------|-----------------------------|
| Render layer                      | Livewire + Alpine      | Inertia 3 + React 19.2+     |
| Client language                   | Blade + Alpine.js      | Strict TypeScript           |
| UI customization                  | Blade components       | React components (shadcn CLI v4 (new-york) over Radix UI) |
| Hot-reload in dev                 | Livewire reload        | Vite HMR                    |
| Admin bundle size                 | Medium (Alpine + Tailwind) | Larger (React + Inertia) |
| React learning curve              | Not required           | Requires TS + React         |
| Community plugins                 | Rich (4 years)         | Building (v0.8)             |
| Multi-tenancy                     | Built-in               | `arqel-dev/tenant` (5 resolvers + 2 adapters) |
| Code splitting                    | Limited                | Vite + tsup native          |
| Static analysis                   | Good                   | PHPStan max + TS strict     |

**When NOT to migrate:** if 80% of the work is simple forms + CRUD
and the team has no React fluency, Filament is still excellent. Arqel
pays dividends when the panel grows in custom UI complexity.

## Decision matrix

| Feature                 | Filament v3                  | Arqel                                | Notes                      |
|-------------------------|------------------------------|--------------------------------------|----------------------------|
| Resource CRUD           | Yes (`Filament\Resources`)   | Yes (`Arqel\Core\Resources\Resource`) | Very similar API          |
| Form schema             | Yes (Livewire-bound)         | Yes (declarative `Form` + Inertia)   | No magical two-way binding |
| Table builder           | Yes (`Filament\Tables`)      | Yes (`Arqel\Table\Table`)            | Parity > 90% Phase 2       |
| Inline editing          | Yes                          | Yes (TABLE-V2-002)                   | `TextInputColumn`/`SelectColumn`/`ToggleColumn` |
| Bulk actions            | Yes                          | Yes (TABLE-008)                      | `BulkAction::execute(Collection)` |
| Header actions          | Yes                          | Yes (`HeaderAction`)                 |                            |
| Row actions             | Yes                          | Yes (`RowAction`)                    |                            |
| Filters                 | Yes                          | Yes (`SelectFilter`/`DateRangeFilter`/etc.) | 6 types + Visual Query Builder |
| Lifecycle hooks         | Yes (`mutateFormDataBefore...`) | Yes (`beforeSave`/`afterSave`/etc.) | Different names            |
| Policies                | Yes (Laravel Gates)          | Yes (Laravel Gates)                  | Identical                  |
| Multi-tenancy           | Yes                          | Yes (`arqel-dev/tenant`)             | Stancl + Spatie adapters   |
| Widgets / Dashboards    | Yes                          | Yes (`arqel-dev/widgets`)            | Stat/Chart/Table/Custom    |
| Notifications           | Yes                          | Partial (Phase 2)                    | Use Laravel Notifications  |
| Custom pages            | Yes (Livewire components)    | Yes (Inertia + React) — **different paradigm** | Rewrite required |
| Plugins                 | Rich                         | Under development                    | Evaluate case by case      |
| File uploads            | Yes                          | Yes (`FileField` / `ImageField`)     |                            |
| Wizards                 | Yes                          | Yes (`WizardField` in fields-advanced) |                          |
| Rich text               | Yes (Trix)                   | Yes (Tiptap in `RichTextField`)      |                            |

## Side-by-side: 12 patterns

### 1. Resource declaration

**Filament**:

```php
namespace App\Filament\Resources;

use Filament\Resources\Resource;
use App\Models\Post;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;
    protected static ?string $navigationIcon = 'heroicon-o-document';
    protected static ?string $navigationGroup = 'Content';
    protected static ?int $navigationSort = 10;
}
```

**Arqel**:

```php
namespace App\Arqel\Resources;

use Arqel\Core\Resources\Resource;
use App\Models\Post;

final class PostResource extends Resource
{
    public static string $model = Post::class;
    public static ?string $navigationIcon = 'document';
    public static ?string $navigationGroup = 'Content';
    public static ?int $navigationSort = 10;
}
```

> Difference: `public static` instead of `protected`. Icons are React
> component name strings (not hardcoded Heroicons). `final` by convention.

### 2. Form schema

**Filament**:

```php
public static function form(Form $form): Form
{
    return $form->schema([
        TextInput::make('title')->required()->maxLength(255),
        Select::make('status')->options(['draft' => 'Draft', 'published' => 'Published']),
        RichEditor::make('body'),
    ]);
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
        F::richText('body'), // requires arqel-dev/fields-advanced
    ];
}
```

> Difference: instance method (`fields()`) instead of static. Layouts
> (Section/Tabs/Grid) sit in a separate `form()` when needed.

### 3. Table columns

**Filament**:

```php
public static function table(Table $table): Table
{
    return $table->columns([
        TextColumn::make('title')->sortable()->searchable()->limit(60),
        BadgeColumn::make('status')->colors(['gray' => 'draft', 'success' => 'published']),
        TextColumn::make('published_at')->date('d/m/Y'),
    ]);
}
```

**Arqel**:

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

> Difference: separate `DateColumn` (not `TextColumn::date()`). Colors in
> Arqel map `value → color` (more natural).

### 4. Filters

**Filament**:

```php
->filters([
    SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published']),
    Filter::make('is_featured')->query(fn ($query) => $query->where('is_featured', true)),
])
```

**Arqel**:

```php
use Arqel\Table\Filters\{SelectFilter, TernaryFilter};

->filters([
    SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published']),
    TernaryFilter::make('is_featured'), // Yes/No/All automatically
])
```

### 5. Actions: row, bulk, and header

**Filament**:

```php
->actions([
    Tables\Actions\EditAction::make(),
    Tables\Actions\DeleteAction::make(),
])
->bulkActions([
    Tables\Actions\BulkActionGroup::make([
        Tables\Actions\DeleteBulkAction::make(),
    ]),
])
->headerActions([
    Tables\Actions\CreateAction::make(),
])
```

**Arqel**:

```php
use Arqel\Actions\Actions;

->actions([
    Actions::edit(),
    Actions::delete(),
])
->bulkActions([
    Actions::deleteBulk(),
])
->toolbarActions([
    Actions::create(),
])
```

> Difference: `toolbarActions()` instead of `headerActions()`. No
> `BulkActionGroup` wrapper — the array is the group.

### 6. Policies / Authorization

**Filament**:

```php
// app/Policies/PostPolicy.php — Laravel standard
public function update(User $user, Post $post): bool { return $user->id === $post->user_id; }
```

**Arqel** (identical — uses native Laravel Gates):

```php
public function update(User $user, Post $post): bool { return $user->id === $post->user_id; }
```

> Arqel respects `Gate::authorize` automatically in controllers and
> filters per-row actions via `Action::canBeExecutedBy` (TABLE-007).
> Policy migration = zero code.

### 7. Lifecycle hooks

**Filament**:

```php
protected function mutateFormDataBeforeCreate(array $data): array
{
    $data['user_id'] = auth()->id();
    return $data;
}

protected function afterCreate(): void { /* ... */ }
```

**Arqel**:

```php
// inside the Resource:
protected function beforeCreate(array $data): array
{
    $data['user_id'] = auth()->id();
    return $data;
}

protected function afterCreate(Model $record): void { /* ... */ }
```

> Difference: `afterCreate` receives the `Model` (does not use `$this->record`).
> There's also `beforeSave`/`afterSave` that run on create+update.

### 8. Multi-tenancy

**Filament**:

```php
// AdminPanelProvider
$panel->tenant(Team::class)->tenantRoutePrefix('team');
```

**Arqel** (with `arqel-dev/tenant`):

```php
// config/arqel.php
'tenancy' => [
    'resolver' => Arqel\Tenant\Resolvers\AuthUserResolver::class,
    'model'    => App\Models\Team::class,
    'foreign_key' => 'team_id',
],

// Tenanted models:
class Post extends Model
{
    use \Arqel\Tenant\Concerns\BelongsToTenant;
}

// Routes:
Route::middleware(['web', 'auth', 'arqel.tenant'])->group(function () { /* ... */ });
```

> Arqel offers 5 resolvers (`Subdomain`, `Path`, `Header`, `Session`,
> `AuthUser`) + 2 adapters (`stancl/tenancy`, `spatie/laravel-multitenancy`).

### 9. Dashboards and Widgets

**Filament**:

```php
class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [Stat::make('Users', User::count())];
    }
}
```

**Arqel** (`arqel-dev/widgets`):

```php
use Arqel\Widgets\StatWidget;

final class UsersStat extends StatWidget
{
    protected string $type = 'users_count';
    protected string $component = 'StatWidget';

    public function data(): array
    {
        return ['label' => 'Users', 'value' => User::count()];
    }
}

// Dashboard:
Dashboard::make('main', 'Overview')
    ->widgets([UsersStat::class, RevenueChart::class])
    ->columns(3);
```

### 10. File uploads

**Filament**:

```php
FileUpload::make('attachment')->disk('s3')->directory('attachments')->image();
```

**Arqel**:

```php
use Arqel\Fields\FieldFactory as F;

F::file('attachment')->disk('s3')->directory('attachments');
F::image('cover')->disk('s3')->directory('covers');
```

### 11. Custom Inertia pages (the biggest difference)

**Filament** (Livewire):

```php
// app/Filament/Pages/AnalyticsPage.php
class AnalyticsPage extends Page
{
    protected static string $view = 'filament.pages.analytics';
    public int $rangeDays = 30;
    public function loadStats(): array { /* ... */ }
}
```

**Arqel** (Controller + Inertia + React — different paradigm):

```php
// app/Http/Controllers/AnalyticsController.php
final class AnalyticsController
{
    public function __invoke(Request $request): \Inertia\Response
    {
        return inertia('Analytics', [
            'rangeDays' => (int) $request->query('range', 30),
            'stats'     => app(AnalyticsService::class)->stats(),
        ]);
    }
}
```

```tsx
// resources/js/Pages/Analytics.tsx
export default function Analytics({ rangeDays, stats }: Props) {
    return <DashboardLayout>{/* JSX */}</DashboardLayout>;
}
```

> **This is the biggest rewrite.** Livewire pages with reactive state
> need to be ported to React components. There's no shortcut.

### 12. Notifications

**Filament**:

```php
Notification::make()->title('Saved').success()->send();
```

**Arqel** (Phase 2 — temporary):

```php
return back()->with('flash', ['message' => 'Saved', 'type' => 'success']);
// React side: useFlash() hook reads props.flash
```

> A unified notification system is planned for Phase 2
> (`arqel-dev/notifications`). Today, use Laravel Notifications + Inertia
> shared props.

## Step-by-step checklist

1. [ ] Audit Models, Migrations, Policies — **they don't change**.
2. [ ] Install Arqel: `composer require arqel-dev/arqel` followed by `php artisan arqel:install` (installs core + table + actions + fields, publishes configs and the shadcn (new-york) theme over Radix UI).
3. [ ] Configure a parallel panel at `/admin-v2` (coexisting with Filament).
4. [ ] Migrate the simplest Resources first (shallow CRUD, no relations).
5. [ ] Port `form()` → `fields()` + adjust hook names (`mutateFormDataBeforeCreate` → `beforeCreate`).
6. [ ] Port `table()` columns/filters — close to 1:1 parity.
7. [ ] Migrate Actions (header → toolbar, BulkActionGroup → direct array).
8. [ ] Migrate Widgets/Dashboards to `arqel-dev/widgets`.
9. [ ] Rewrite custom Livewire pages in React + Inertia (last — biggest effort).
10. [ ] Configure `arqel-dev/tenant` if there's multi-tenancy; remove the Filament panel; switch the `/admin-v2` prefix to `/admin`.

## Common pitfalls

1. **`$this->record` does not exist in Arqel hooks.** Use the `Model $record` passed as an argument. Hooks receive the record explicitly.
2. **No magical two-way binding.** Arqel uses Inertia + React state — updates require `useForm` from `@inertiajs/react`, not Livewire polling.
3. **Badge colors are inverted.** Filament: `colors(['color' => 'value'])`. Arqel: `colors(['value' => 'color'])` — more natural.
4. **`BulkActionGroup` does not exist.** In Arqel, `bulkActions()` accepts a direct array.
5. **Custom Livewire pages = rewrite.** There's no automatic translator. Components with reactive state become React components; server-side state becomes Inertia props.

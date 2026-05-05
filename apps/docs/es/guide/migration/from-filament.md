# Migrando de Filament v3 a Arqel

> Guía de migración con 12 mappings side-by-side. Para el índice general
> y el árbol de decisión, ver [`README.md`](./README.md).

## Por qué migrar

Filament v3 es maduro y productivo, especialmente para apps muy
enfocadas en forms. Arqel es una mejor elección cuando el equipo **ya
invierte en React** y quiere un único stack Laravel + Inertia + React
(sin el salto cognitivo de Livewire/Alpine). La tabla de abajo
resume los trade-offs.

| Criterio                          | Filament v3            | Arqel                       |
|-----------------------------------|------------------------|-----------------------------|
| Capa de render                    | Livewire + Alpine      | Inertia 3 + React 19.2+     |
| Lenguaje del cliente              | Blade + Alpine.js      | TypeScript strict           |
| Personalización UI                | Componentes Blade      | Componentes React (shadcn CLI v4 (new-york) sobre Radix UI) |
| Hot-reload en dev                 | Reload de Livewire     | Vite HMR                    |
| Tamaño del bundle del admin       | Medio (Alpine + Tailwind) | Mayor (React + Inertia) |
| Curva de aprendizaje React        | No requerida           | Requiere TS + React         |
| Plugins de la comunidad           | Rico (4 años)          | En construcción (v0.8)      |
| Multi-tenancy                     | Built-in               | `arqel-dev/tenant` (5 resolvers + 2 adapters) |
| Code splitting                    | Limitado               | Vite + tsup nativo          |
| Análisis estático                 | Bueno                  | PHPStan max + TS strict     |

**Cuándo NO migrar:** si el 80% del trabajo es forms simples + CRUD
y el equipo no tiene fluidez en React, Filament sigue siendo excelente.
Arqel paga dividendos cuando el panel crece en complejidad de UI personalizada.

## Matriz de decisión

| Feature                 | Filament v3                  | Arqel                                | Notas                      |
|-------------------------|------------------------------|--------------------------------------|----------------------------|
| Resource CRUD           | Sí (`Filament\Resources`)    | Sí (`Arqel\Core\Resources\Resource`) | API muy similar            |
| Form schema             | Sí (Livewire-bound)          | Sí (`Form` declarativo + Inertia)    | Sin two-way binding mágico |
| Table builder           | Sí (`Filament\Tables`)       | Sí (`Arqel\Table\Table`)             | Paridad > 90% Fase 2       |
| Inline editing          | Sí                           | Sí (TABLE-V2-002)                    | `TextInputColumn`/`SelectColumn`/`ToggleColumn` |
| Bulk actions            | Sí                           | Sí (TABLE-008)                       | `BulkAction::execute(Collection)` |
| Header actions          | Sí                           | Sí (`HeaderAction`)                  |                            |
| Row actions             | Sí                           | Sí (`RowAction`)                     |                            |
| Filters                 | Sí                           | Sí (`SelectFilter`/`DateRangeFilter`/etc.) | 6 tipos + Visual Query Builder |
| Lifecycle hooks         | Sí (`mutateFormDataBefore...`) | Sí (`beforeSave`/`afterSave`/etc.) | Nombres diferentes         |
| Policies                | Sí (Laravel Gates)           | Sí (Laravel Gates)                   | Idénticas                  |
| Multi-tenancy           | Sí                           | Sí (`arqel-dev/tenant`)              | Adapters Stancl + Spatie   |
| Widgets / Dashboards    | Sí                           | Sí (`arqel-dev/widgets`)             | Stat/Chart/Table/Custom    |
| Notificaciones          | Sí                           | Parcial (Fase 2)                     | Usa Laravel Notifications  |
| Páginas personalizadas  | Sí (componentes Livewire)    | Sí (Inertia + React) — **paradigma diferente** | Reescritura requerida |
| Plugins                 | Rico                         | En desarrollo                        | Evaluar caso a caso        |
| File uploads            | Sí                           | Sí (`FileField` / `ImageField`)      |                            |
| Wizards                 | Sí                           | Sí (`WizardField` en fields-advanced) |                          |
| Rich text               | Sí (Trix)                    | Sí (Tiptap en `RichTextField`)       |                            |

## Side-by-side: 12 patrones

### 1. Declaración de Resource

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

> Diferencia: `public static` en lugar de `protected`. Los iconos son strings
> de nombre de componente React (no Heroicons hardcoded). `final` por convención.

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
        F::richText('body'), // requiere arqel-dev/fields-advanced
    ];
}
```

> Diferencia: método de instancia (`fields()`) en lugar de static. Los layouts
> (Section/Tabs/Grid) van en un `form()` separado cuando se necesitan.

### 3. Columnas de tabla

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

> Diferencia: `DateColumn` separado (no `TextColumn::date()`). Los colores en
> Arqel mapean `value → color` (más natural).

### 4. Filtros

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
    TernaryFilter::make('is_featured'), // Yes/No/All automáticamente
])
```

### 5. Actions: row, bulk y header

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

> Diferencia: `toolbarActions()` en lugar de `headerActions()`. Sin
> wrapper `BulkActionGroup` — el array es el grupo.

### 6. Policies / Autorización

**Filament**:

```php
// app/Policies/PostPolicy.php — estándar Laravel
public function update(User $user, Post $post): bool { return $user->id === $post->user_id; }
```

**Arqel** (idéntico — usa Laravel Gates nativos):

```php
public function update(User $user, Post $post): bool { return $user->id === $post->user_id; }
```

> Arqel respeta `Gate::authorize` automáticamente en los controllers y
> filtra acciones per-row vía `Action::canBeExecutedBy` (TABLE-007).
> Migración de Policy = cero código.

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
// dentro del Resource:
protected function beforeCreate(array $data): array
{
    $data['user_id'] = auth()->id();
    return $data;
}

protected function afterCreate(Model $record): void { /* ... */ }
```

> Diferencia: `afterCreate` recibe el `Model` (no usa `$this->record`).
> También hay `beforeSave`/`afterSave` que corren en create+update.

### 8. Multi-tenancy

**Filament**:

```php
// AdminPanelProvider
$panel->tenant(Team::class)->tenantRoutePrefix('team');
```

**Arqel** (con `arqel-dev/tenant`):

```php
// config/arqel.php
'tenancy' => [
    'resolver' => Arqel\Tenant\Resolvers\AuthUserResolver::class,
    'model'    => App\Models\Team::class,
    'foreign_key' => 'team_id',
],

// Modelos tenanted:
class Post extends Model
{
    use \Arqel\Tenant\Concerns\BelongsToTenant;
}

// Rutas:
Route::middleware(['web', 'auth', 'arqel.tenant'])->group(function () { /* ... */ });
```

> Arqel ofrece 5 resolvers (`Subdomain`, `Path`, `Header`, `Session`,
> `AuthUser`) + 2 adapters (`stancl/tenancy`, `spatie/laravel-multitenancy`).

### 9. Dashboards y Widgets

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

### 11. Páginas Inertia personalizadas (la mayor diferencia)

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

**Arqel** (Controller + Inertia + React — paradigma diferente):

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

> **Esta es la mayor reescritura.** Las páginas Livewire con estado reactivo
> necesitan ser portadas a componentes React. No hay atajo.

### 12. Notificaciones

**Filament**:

```php
Notification::make()->title('Saved').success()->send();
```

**Arqel** (Fase 2 — temporal):

```php
return back()->with('flash', ['message' => 'Saved', 'type' => 'success']);
// Lado React: hook useFlash() lee props.flash
```

> Un sistema unificado de notificaciones está planificado para Fase 2
> (`arqel-dev/notifications`). Hoy, usa Laravel Notifications + props
> compartidas de Inertia.

## Checklist paso a paso

1. [ ] Audita Models, Migrations, Policies — **no cambian**.
2. [ ] Instala Arqel: `composer require arqel-dev/framework` seguido de `php artisan arqel:install` (instala core + table + actions + fields, publica configs y el tema shadcn (new-york) sobre Radix UI).
3. [ ] Configura un panel paralelo en `/admin-v2` (coexistiendo con Filament).
4. [ ] Migra primero los Resources más simples (CRUD shallow, sin relaciones).
5. [ ] Porta `form()` → `fields()` + ajusta nombres de hooks (`mutateFormDataBeforeCreate` → `beforeCreate`).
6. [ ] Porta columnas/filtros de `table()` — paridad cercana a 1:1.
7. [ ] Migra Actions (header → toolbar, BulkActionGroup → array directo).
8. [ ] Migra Widgets/Dashboards a `arqel-dev/widgets`.
9. [ ] Reescribe páginas Livewire personalizadas en React + Inertia (último — mayor esfuerzo).
10. [ ] Configura `arqel-dev/tenant` si hay multi-tenancy; remueve el panel Filament; cambia el prefix `/admin-v2` a `/admin`.

## Pitfalls comunes

1. **`$this->record` no existe en los hooks de Arqel.** Usa el `Model $record` pasado como argumento. Los hooks reciben el record explícitamente.
2. **No hay two-way binding mágico.** Arqel usa Inertia + estado React — los updates requieren `useForm` de `@inertiajs/react`, no polling Livewire.
3. **Los colores de Badge están invertidos.** Filament: `colors(['color' => 'value'])`. Arqel: `colors(['value' => 'color'])` — más natural.
4. **`BulkActionGroup` no existe.** En Arqel, `bulkActions()` acepta un array directo.
5. **Páginas Livewire personalizadas = reescritura.** No hay traductor automático. Componentes con estado reactivo se vuelven componentes React; el estado del lado servidor se vuelve props de Inertia.

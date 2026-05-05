# Migrando de Filament v3 para Arqel

> Guia de migração com 12 mapeamentos side-by-side. Para o índice geral
> e a árvore de decisão, veja [`README.md`](./README.md).

## Por que migrar

Filament v3 é maduro e produtivo, especialmente para apps fortemente
centradas em formulários. Arqel é uma escolha melhor quando a equipe **já
investe em React** e quer um stack único Laravel + Inertia + React (sem o
salto cognitivo Livewire/Alpine). A tabela abaixo resume os trade-offs.

| Critério                          | Filament v3            | Arqel                       |
|-----------------------------------|------------------------|-----------------------------|
| Render layer                      | Livewire + Alpine      | Inertia 3 + React 19.2+     |
| Linguagem do client               | Blade + Alpine.js      | TypeScript strict           |
| Customização de UI                | Blade components       | React components (shadcn CLI v4 (new-york) sobre Radix UI) |
| Hot-reload em dev                 | Livewire reload        | Vite HMR                    |
| Bundle size de admin              | Médio (Alpine + Tailwind) | Maior (React + Inertia)  |
| Curva de aprendizado React        | Não exige              | Exige TS+React              |
| Plugins comunitários              | Ricos (4 anos)         | Em construção (v0.8)        |
| Multi-tenancy                     | Built-in               | `arqel-dev/tenant` (5 resolvers + 2 adapters) |
| Code splitting                    | Limitado               | Vite + tsup nativo          |
| Static analysis                   | Boa                    | PHPStan max + TS strict     |

**Quando NÃO migrar:** se 80% do trabalho está em forms simples + CRUD
e a equipe não tem fluência React, Filament continua ótimo. Arqel paga
dividendos quando o panel cresce em complexidade de UI custom.

## Matriz de decisão

| Feature                 | Filament v3                  | Arqel                                | Notas                      |
|-------------------------|------------------------------|--------------------------------------|----------------------------|
| Resource CRUD           | ✓ (`Filament\Resources`)     | ✓ (`Arqel\Core\Resources\Resource`)  | API muito similar          |
| Form schema             | ✓ (Livewire-bound)           | ✓ (`Form` declarativo + Inertia)     | Sem two-way binding mágico |
| Table builder           | ✓ (`Filament\Tables`)        | ✓ (`Arqel\Table\Table`)              | Paridade > 90% Phase 2     |
| Inline editing          | ✓                            | ✓ (TABLE-V2-002)                     | `TextInputColumn`/`SelectColumn`/`ToggleColumn` |
| Bulk actions            | ✓                            | ✓ (TABLE-008)                        | `BulkAction::execute(Collection)` |
| Header actions          | ✓                            | ✓ (`HeaderAction`)                   |                            |
| Row actions             | ✓                            | ✓ (`RowAction`)                      |                            |
| Filters                 | ✓                            | ✓ (`SelectFilter`/`DateRangeFilter`/etc) | 6 tipos + Visual Query Builder |
| Lifecycle hooks         | ✓ (`mutateFormDataBefore...`)| ✓ (`beforeSave`/`afterSave`/etc)     | Nomes diferentes           |
| Policies                | ✓ (Laravel Gates)            | ✓ (Laravel Gates)                    | Idêntico                   |
| Multi-tenancy           | ✓                            | ✓ (`arqel-dev/tenant`)                   | Adapters Stancl + Spatie   |
| Widgets / Dashboards    | ✓                            | ✓ (`arqel-dev/widgets`)                  | Stat/Chart/Table/Custom    |
| Notifications           | ✓                            | ⚠ (Phase 2)                          | Use Laravel Notifications  |
| Custom pages            | ✓ (Livewire components)      | ✓ (Inertia + React) — **paradigma diferente** | Reescrita necessária |
| Plugins                 | Ricos                        | Em desenvolvimento                   | Avaliar caso a caso        |
| File uploads            | ✓                            | ✓ (`FileField` / `ImageField`)       |                            |
| Wizards                 | ✓                            | ✓ (`WizardField` em fields-advanced) |                            |
| Rich text               | ✓ (Trix)                     | ✓ (Tiptap em `RichTextField`)        |                            |

## Side-by-side: 12 padrões

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

> Diferença: `public static` em vez de `protected`. Ícones são strings de
> componente React (não Heroicons hardcoded). `final` por convenção.

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
        F::richText('body'), // requer arqel-dev/fields-advanced
    ];
}
```

> Diferença: método de instância (`fields()`) em vez de estático. Layouts
> (Section/Tabs/Grid) ficam em `form()` separado quando necessário.

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

> Diferença: `DateColumn` separado (não `TextColumn::date()`). Cores em
> Arqel mapeiam `valor → cor` (mais natural).

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
    TernaryFilter::make('is_featured'), // Sim/Não/Todos automaticamente
])
```

### 5. Actions: row, bulk e header

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

> Diferença: `toolbarActions()` em vez de `headerActions()`. Sem
> `BulkActionGroup` wrapper — o array já é o grupo.

### 6. Policies / Authorization

**Filament**:

```php
// app/Policies/PostPolicy.php — padrão Laravel
public function update(User $user, Post $post): bool { return $user->id === $post->user_id; }
```

**Arqel** (idêntico — usa Laravel Gates nativos):

```php
public function update(User $user, Post $post): bool { return $user->id === $post->user_id; }
```

> Arqel respeita `Gate::authorize` automaticamente em controllers e
> filtra ações por linha via `Action::canBeExecutedBy` (TABLE-007).
> Migração de policies = zero código.

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
// dentro do Resource:
protected function beforeCreate(array $data): array
{
    $data['user_id'] = auth()->id();
    return $data;
}

protected function afterCreate(Model $record): void { /* ... */ }
```

> Diferença: `afterCreate` recebe o `Model` (não usa `$this->record`).
> Existe também `beforeSave`/`afterSave` que rodam em create+update.

### 8. Multi-tenancy

**Filament**:

```php
// AdminPanelProvider
$panel->tenant(Team::class)->tenantRoutePrefix('team');
```

**Arqel** (com `arqel-dev/tenant`):

```php
// config/arqel.php
'tenancy' => [
    'resolver' => Arqel\Tenant\Resolvers\AuthUserResolver::class,
    'model'    => App\Models\Team::class,
    'foreign_key' => 'team_id',
],

// Models tenanted:
class Post extends Model
{
    use \Arqel\Tenant\Concerns\BelongsToTenant;
}

// Routes:
Route::middleware(['web', 'auth', 'arqel.tenant'])->group(function () { /* ... */ });
```

> Arqel oferece 5 resolvers (`Subdomain`, `Path`, `Header`, `Session`,
> `AuthUser`) + 2 adapters (`stancl/tenancy`, `spatie/laravel-multitenancy`).

### 9. Dashboards e Widgets

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

### 11. Custom Inertia pages (a maior diferença)

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

> **Esta é a maior reescrita.** Páginas Livewire com state reativo
> precisam ser portadas para componentes React. Não há atalho.

### 12. Notifications

**Filament**:

```php
Notification::make()->title('Saved').success()->send();
```

**Arqel** (Phase 2 — temporário):

```php
return back()->with('flash', ['message' => 'Saved', 'type' => 'success']);
// Lado React: useFlash() hook lê props.flash
```

> Sistema unificado de notificações está planejado para Phase 2
> (`arqel-dev/notifications`). Hoje use Laravel Notifications + Inertia
> shared props.

## Checklist passo-a-passo

1. [ ] Auditar Models, Migrations, Policies — **não mudam**.
2. [ ] Instalar Arqel: `composer require arqel-dev/framework` seguido de `php artisan arqel:install` (instala core + table + actions + fields, publica configs e o tema shadcn (new-york) sobre Radix UI).
3. [ ] Configurar painel paralelo em `/admin-v2` (coexistência com Filament).
4. [ ] Migrar Resources mais simples primeiro (CRUD raso, sem relações).
5. [ ] Portar `form()` → `fields()` + ajustar nomes de hooks (`mutateFormDataBeforeCreate` → `beforeCreate`).
6. [ ] Portar `table()` columns/filters — paridade próxima de 1:1.
7. [ ] Migrar Actions (header → toolbar, BulkActionGroup → array direto).
8. [ ] Migrar Widgets/Dashboards para `arqel-dev/widgets`.
9. [ ] Reescrever páginas custom Livewire em React + Inertia (último — maior esforço).
10. [ ] Configurar `arqel-dev/tenant` se houver multi-tenancy; remover Filament panel; trocar prefixo `/admin-v2` para `/admin`.

## Pitfalls comuns

1. **`$this->record` não existe nos hooks Arqel.** Use o `Model $record` passado como argumento. Hooks recebem o record explicitamente.
2. **Sem two-way binding mágico.** Arqel usa Inertia + React state — atualizações exigem `useForm` do `@inertiajs/react`, não polling Livewire.
3. **Cores em badges são invertidas.** Filament: `colors(['cor' => 'valor'])`. Arqel: `colors(['valor' => 'cor'])` — mais natural.
4. **`BulkActionGroup` não existe.** Em Arqel, `bulkActions()` aceita um array direto.
5. **Custom Livewire pages = reescrita.** Não há tradutor automático. Componentes com state reativo viram componentes React; estado server-side vira Inertia props.

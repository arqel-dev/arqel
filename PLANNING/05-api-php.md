# 05 — API PHP

> Contratos canónicos das classes PHP que os usuários vão escrever ou estender. Este documento define **o que** a API oferece; implementação em tickets Fase 1.

## 1. Resource

### 1.1 Base class

```php
<?php

declare(strict_types=1);

namespace App\Arqel\Resources;

use Arqel\Core\Resources\Resource;
use Arqel\Fields\Field;
use Arqel\Table\Table;
use Arqel\Form\Form;
use Arqel\Actions\Action;
use App\Models\User;

final class UserResource extends Resource
{
    /**
     * The Eloquent model this resource represents.
     */
    public static string $model = User::class;

    /**
     * Singular label (defaults to derived from model name).
     */
    public static ?string $label = 'User';

    /**
     * Plural label.
     */
    public static ?string $pluralLabel = 'Users';

    /**
     * URL slug (defaults to kebab-case plural).
     */
    public static ?string $slug = 'users';

    /**
     * Icon for navigation (Lucide React icon name).
     */
    public static ?string $navigationIcon = 'users';

    /**
     * Navigation group.
     */
    public static ?string $navigationGroup = 'Access Control';

    /**
     * Navigation sort order.
     */
    public static ?int $navigationSort = 10;

    /**
     * Global search attribute (single-field quick search).
     */
    public static ?string $recordTitleAttribute = 'email';

    /**
     * Fields schema (used em Form).
     *
     * @return array<Field>
     */
    public function fields(): array
    {
        return [
            Field::text('name')
                ->required()
                ->maxLength(255)
                ->placeholder('John Doe'),

            Field::email('email')
                ->required()
                ->unique(ignorable: $this->record)
                ->columnSpan(2),

            Field::belongsTo('role', RoleResource::class)
                ->searchable()
                ->preload(),

            Field::password('password')
                ->required(fn () => $this->isCreating())
                ->dehydrated(fn ($state) => filled($state))
                ->hiddenOnDetail(),

            Field::date('birth_date')
                ->maxDate(now()),

            Field::toggle('is_active')
                ->default(true)
                ->helperText('Disable to prevent login without deletion.'),
        ];
    }

    /**
     * Table columns (used em index page).
     */
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Column::text('name')->searchable()->sortable(),
                Column::text('email')->searchable()->copyable(),
                Column::relationship('role', 'name')->badge(),
                Column::boolean('is_active')->label('Active'),
                Column::date('created_at')->label('Joined')->sortable(),
            ])
            ->filters([
                Filter::select('role_id')
                    ->options(fn () => Role::pluck('name', 'id')),
                Filter::ternary('is_active'),
                Filter::dateRange('created_at'),
            ])
            ->actions([
                Action::view(),
                Action::edit(),
                Action::delete()->requiresConfirmation(),
                Action::make('impersonate')
                    ->icon('user-check')
                    ->authorize(fn ($user, $record) => $user->isSuper() && $record->id !== $user->id)
                    ->action(fn ($record) => auth()->loginUsingId($record->id)),
            ])
            ->bulkActions([
                BulkAction::delete()->requiresConfirmation(),
                BulkAction::make('activate')
                    ->label('Activate selected')
                    ->action(fn ($records) => $records->each->update(['is_active' => true])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Standalone form config (optional, overrides fields() shape).
     */
    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Profile')
                ->columns(2)
                ->schema([
                    $this->getField('name'),
                    $this->getField('email'),
                ]),

            Section::make('Access')
                ->schema([
                    $this->getField('role'),
                    $this->getField('is_active'),
                    $this->getField('password'),
                ])
                ->collapsible(),
        ]);
    }

    /**
     * Eager loading for index query (optional — auto-detected from fields).
     */
    public function indexQuery(): ?Builder
    {
        return static::$model::query()->with('role');
    }

    /**
     * Record title (shown on detail page header).
     */
    public function recordTitle($record): string
    {
        return $record->name ?? $record->email;
    }

    /**
     * Subtitle (optional).
     */
    public function recordSubtitle($record): ?string
    {
        return $record->role?->name;
    }

    /**
     * Lifecycle hooks.
     */
    protected function beforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        return $data;
    }

    protected function afterCreate($record): void
    {
        event(new UserCreated($record));
    }

    protected function beforeUpdate($record, array $data): array
    {
        return $data;
    }

    protected function afterUpdate($record): void
    {
        cache()->forget("user.{$record->id}");
    }

    protected function beforeDelete($record): void
    {
        if ($record->hasRelatedOrders()) {
            throw new \DomainException('Cannot delete user with orders.');
        }
    }
}
```

### 1.2 Resource discovery

Resources são descobertos de duas formas:

**1. Directory scan (default):**

```php
// config/arqel.php
'resources' => [
    'path' => app_path('Arqel/Resources'),
    'namespace' => 'App\\Arqel\\Resources',
],
```

**2. Explicit registration:**

```php
// Panel definition
Arqel::panel('admin')
    ->resources([
        UserResource::class,
        PostResource::class,
        \App\Arqel\Resources\CommentResource::class,
    ]);
```

### 1.3 Resource lifecycle methods

| Hook | Invocado | Return |
|---|---|---|
| `beforeCreate(array $data)` | Antes de `$model::create()` | `array` (data modificada) |
| `afterCreate($record)` | Após create bem-sucedido | `void` |
| `beforeUpdate($record, array $data)` | Antes de `$record->update()` | `array` |
| `afterUpdate($record)` | Após update bem-sucedido | `void` |
| `beforeSave($record, array $data)` | Antes de create OU update | `array` |
| `afterSave($record)` | Após create OU update | `void` |
| `beforeDelete($record)` | Antes de delete (pode lançar para cancelar) | `void` |
| `afterDelete($record)` | Após delete | `void` |
| `mutateFormDataBeforeSave(array $data)` | Antes de save (form only) | `array` |
| `mutateFormDataBeforeFill(array $data)` | Ao popular edit form | `array` |

## 2. Field

### 2.1 Base API

```php
use Arqel\Fields\Field;

Field::text('name')                            // Static factory
    ->label('Full Name')                       // Override auto-label
    ->placeholder('John Doe')
    ->helperText('Your legal name')
    ->required()                               // Rule
    ->requiredWith('email')                    // Conditional rule
    ->nullable()
    ->default('Anonymous')
    ->readonly()                               // Readonly em form
    ->disabled(fn ($user) => $user->isGuest())
    ->hidden(fn ($record) => ! $record->shouldShowName())
    ->visibleOnDetail()                        // Shown em detail page
    ->visibleOnEdit()
    ->hiddenOnCreate()
    ->rules(['max:255', 'regex:/^[a-z]+$/i'])  // Extra Laravel rules
    ->maxLength(255)
    ->minLength(2)
    ->columnSpan(2)                            // Grid span
    ->columnSpanFull()
    ->dehydrated(false)                        // Don't persist
    ->live()                                   // Field is reactive
    ->liveDebounced(300)                       // Debounced 300ms
    ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state)))
    ->dependsOn(['role_id'])                   // Re-evaluate when role_id changes
    ->dataType('string')                       // TypeScript type hint
    ->canSee(fn ($user) => $user->can('view_sensitive'))
    ->canEdit(fn ($user, $record) => $user->id === $record->created_by);
```

### 2.2 Field type factories

```php
Field::text($name)                             // TextField
Field::textarea($name)                         // TextareaField  
Field::number($name)                           // NumberField
Field::currency($name)                         // CurrencyField
Field::boolean($name)                          // BooleanField (checkbox)
Field::toggle($name)                           // ToggleField (switch)
Field::select($name)                           // SelectField
Field::multiSelect($name)                      // MultiSelectField
Field::radio($name)                            // RadioField
Field::email($name)                            // EmailField
Field::url($name)                              // UrlField
Field::password($name)                         // PasswordField
Field::slug($name)                             // SlugField
Field::date($name)                             // DateField
Field::dateTime($name)                         // DateTimeField
Field::belongsTo($name, $relatedResource)      // BelongsToField
Field::hasMany($name, $relatedResource)        // HasManyField (readonly Fase 1)
Field::file($name)                             // FileField
Field::image($name)                            // ImageField
Field::color($name)                            // ColorField
Field::hidden($name)                           // HiddenField

// Fase 2+
Field::richText($name)                         // Tiptap
Field::markdown($name)
Field::code($name)                             // Shiki
Field::repeater($name)
Field::builder($name)
Field::keyValue($name)
Field::tags($name)
Field::wizard($steps)
Field::tabs($tabs)
```

### 2.3 Field-specific APIs

**SelectField:**

```php
Field::select('status')
    ->options([
        'draft' => 'Draft',
        'published' => 'Published',
        'archived' => 'Archived',
    ])
    ->searchable()
    ->native(false)                            // Use custom dropdown
    ->optionsRelationship('category', 'name')  // From Eloquent relation
    ->createOptionUsing(fn ($name) => Category::create(['name' => $name]))
    ->allowCustomValues()
    ->multiple();                              // Multi-select mode
```

**BelongsToField:**

```php
Field::belongsTo('author', UserResource::class)
    ->searchable()
    ->preload()
    ->searchColumns(['name', 'email'])
    ->optionLabel(fn ($user) => "{$user->name} ({$user->email})")
    ->createOptionForm(fn () => UserResource::form())
    ->relationship('author', 'name', fn ($query) => $query->where('active', true));
```

**FileField / ImageField:**

```php
Field::image('avatar')
    ->disk('s3')
    ->directory('avatars')
    ->visibility('public')
    ->maxSize(2048)                            // KB
    ->acceptedFileTypes(['image/jpeg', 'image/png'])
    ->imageCropAspectRatio('1:1')
    ->imageResizeTargetWidth(400)
    ->multiple()
    ->reorderable()
    ->using(MediaLibraryStrategy::class);       // Spatie Media Library opt-in
```

**DateField:**

```php
Field::date('published_at')
    ->minDate(now())
    ->maxDate(now()->addYear())
    ->format('Y-m-d')
    ->displayFormat('d/m/Y')
    ->closeOnDateSelection();
```

## 3. Action

### 3.1 Action types

```php
use Arqel\Actions\Action;
use Arqel\Actions\BulkAction;

// Row action (per-record)
Action::make('publish')
    ->icon('send')
    ->color('success')
    ->authorize('publish')                     // Policy ability
    ->requiresConfirmation()
    ->modalHeading('Publish post?')
    ->modalDescription('This will make the post visible to everyone.')
    ->action(fn ($record) => $record->publish())
    ->successNotification('Post published!');

// Bulk action
BulkAction::make('archive')
    ->icon('archive')
    ->requiresConfirmation()
    ->deselectRecordsAfterCompletion()
    ->action(fn (Collection $records) => $records->each->archive());

// Toolbar action (não associado a records)
ToolbarAction::make('import')
    ->label('Import CSV')
    ->icon('upload')
    ->form([
        Field::file('csv_file')->acceptedFileTypes(['text/csv']),
    ])
    ->action(fn (array $data) => dispatch(new ImportCsvJob($data['csv_file'])));
```

### 3.2 Action com form modal

```php
Action::make('transfer_ownership')
    ->icon('arrow-right-left')
    ->form([
        Field::belongsTo('new_owner', UserResource::class)
            ->required()
            ->searchable(),
        Field::textarea('reason')
            ->required()
            ->minLength(20),
    ])
    ->action(function ($record, array $data) {
        $record->transferOwnership($data['new_owner'], $data['reason']);
    });
```

### 3.3 Action assíncrona (queued)

```php
Action::make('export')
    ->queue(ExportJob::class)
    ->onQueue('exports')
    ->progress()                              // Habilita progress tracking via Reverb
    ->onComplete(fn ($record, $result) => sendNotification($record, $result));
```

### 3.4 Confirmable variants

```php
Action::delete()
    ->requiresConfirmation()
    ->modalIcon('trash-2')
    ->modalColor('destructive')                // 'destructive' | 'warning' | 'info'
    ->modalConfirmationRequiresText('DELETE')  // User types 'DELETE' to confirm
    ->modalSubmitButtonLabel('Yes, delete')
    ->modalCancelButtonLabel('Cancel');
```

## 4. Policy

Arqel usa Laravel Policies nativas. Convenção standard:

```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Post;

final class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('posts.view');
    }

    public function view(User $user, Post $post): bool
    {
        return $user->id === $post->author_id 
            || $user->hasRole('editor');
    }

    public function create(User $user): bool
    {
        return $user->can('posts.create');
    }

    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->author_id;
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, Post $post): bool
    {
        return $user->hasRole('admin');
    }

    // Custom abilities usadas em Actions
    public function publish(User $user, Post $post): bool
    {
        return $this->update($user, $post) && $post->isDraft();
    }

    public function impersonate(User $user, User $target): bool
    {
        return $user->isSuperAdmin() && $user->id !== $target->id;
    }
}
```

## 5. Panel

Um "Panel" é um conjunto de Resources + Navigation + Theme sob uma rota base.

```php
// app/Providers/ArqelServiceProvider.php

use Arqel\Core\Arqel;

public function boot(): void
{
    Arqel::panel('admin')
        ->path('/admin')
        ->brand(name: 'Acme Admin', logo: asset('logo.svg'))
        ->theme('arqel-default')
        ->primaryColor('blue')
        ->darkMode()
        ->middleware(['web', 'auth', 'verified'])
        ->resources([
            UserResource::class,
            PostResource::class,
            CategoryResource::class,
        ])
        ->widgets([
            StatsOverview::class,
            RecentActivity::class,
        ])
        ->navigationGroups([
            'Content',
            'Access Control',
        ])
        ->authGuard('admin')
        ->tenant(TeamScope::class);            // Fase 2+

    // Multiple panels supported
    Arqel::panel('customer')
        ->path('/app')
        ->middleware(['web', 'auth'])
        ->resources([
            OrderResource::class,
            ProfileResource::class,
        ]);
}
```

## 6. Navigation

### 6.1 Automatic

Resources auto-registradas no nav via `$navigationGroup` e `$navigationSort`:

```php
class UserResource extends Resource
{
    public static ?string $navigationGroup = 'Access Control';
    public static ?int $navigationSort = 10;
    public static ?string $navigationIcon = 'users';
}
```

### 6.2 Manual

```php
Arqel::panel('admin')
    ->navigation(function (Navigation $nav) {
        $nav->group('Content', function ($group) {
            $group->items([
                NavigationItem::make('Dashboard')
                    ->icon('home')
                    ->url('/admin'),
                NavigationItem::resource(PostResource::class),
                NavigationItem::make('Media Library')
                    ->icon('folder')
                    ->url('/admin/media')
                    ->badge(fn () => Media::pending()->count())
                    ->badgeColor('warning'),
            ]);
        });

        $nav->divider();

        $nav->group('Settings', function ($group) {
            $group->icon('settings');
            $group->items([/* ... */]);
            $group->collapsed();
        });
    });
```

## 7. Widget

### 7.1 Stat widget

```php
<?php

namespace App\Arqel\Widgets;

use Arqel\Widgets\StatWidget;

final class TotalUsersStat extends StatWidget
{
    protected ?string $heading = 'Total Users';

    protected function stat(): int|string
    {
        return User::count();
    }

    protected function description(): ?string
    {
        $diff = $this->percentChangeVsLastWeek();
        return $diff > 0 ? "+{$diff}% vs last week" : "{$diff}% vs last week";
    }

    protected function descriptionIcon(): string
    {
        return $this->percentChangeVsLastWeek() > 0 ? 'trending-up' : 'trending-down';
    }

    protected function color(): string
    {
        return $this->percentChangeVsLastWeek() > 0 ? 'success' : 'danger';
    }

    protected function chart(): ?array
    {
        return User::selectRaw('DATE(created_at) as day, COUNT(*) as count')
            ->where('created_at', '>', now()->subDays(30))
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('count')
            ->toArray();
    }
}
```

### 7.2 Chart widget

```php
final class UsersGrowthChart extends ChartWidget
{
    protected ?string $heading = 'Users over time';
    protected string $chartType = 'line';              // line | bar | area | pie | donut

    protected function data(): array
    {
        return [
            'labels' => $this->getLabels(),
            'datasets' => [
                [
                    'label' => 'New users',
                    'data' => $this->getData(),
                    'color' => 'primary',
                ],
            ],
        ];
    }

    protected function filters(): ?array
    {
        return [
            'today' => 'Today',
            '7days' => 'Last 7 days',
            '30days' => 'Last 30 days',
        ];
    }
}
```

### 7.3 Custom widget

```php
final class CustomWidget extends Widget
{
    protected string $view = 'widgets.custom';         // React component ref

    public function data(): array
    {
        return ['foo' => 'bar'];
    }
}
```

## 8. Tenant (Fase 2)

### 8.1 Config

```php
// config/arqel.php
'tenancy' => [
    'resolver' => \App\Tenancy\TenantResolver::class,
    'model' => \App\Models\Team::class,
    'foreign_key' => 'team_id',
    'scoped_models' => '*',                    // All models
],
```

### 8.2 Trait em models

```php
use Arqel\Tenant\Concerns\BelongsToTenant;

class Post extends Model
{
    use BelongsToTenant;                       // Auto-scope by tenant
}
```

### 8.3 TenantResolver

```php
final class TenantResolver implements \Arqel\Tenant\Contracts\TenantResolver
{
    public function resolve(Request $request): ?Team
    {
        if ($subdomain = $request->route('tenant')) {
            return Team::where('subdomain', $subdomain)->first();
        }

        return auth()->user()?->currentTeam;
    }
}
```

## 9. Custom field (developer extension)

```php
<?php

namespace App\Arqel\Fields;

use Arqel\Fields\Field;

final class RichMarkdownField extends Field
{
    protected string $type = 'rich-markdown';
    protected string $component = 'RichMarkdownInput';   // React component name

    public function toolbar(array $buttons): static
    {
        $this->state['toolbar'] = $buttons;
        return $this;
    }

    public function imageUploadDisk(string $disk): static
    {
        $this->state['imageUploadDisk'] = $disk;
        return $this;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'toolbar' => $this->state['toolbar'] ?? ['bold', 'italic'],
            'imageUploadDisk' => $this->state['imageUploadDisk'] ?? 'local',
        ]);
    }
}
```

Registo em `ArqelServiceProvider`:

```php
Arqel::registerField(RichMarkdownField::class);

// Usage: Field::richMarkdown('content') if registered as macro
Field::macro('richMarkdown', fn ($name) => RichMarkdownField::make($name));
```

Frontend component correspondente (ver `06-api-react.md`):

```tsx
// resources/js/arqel-dev/fields/RichMarkdownInput.tsx
export function RichMarkdownInput({ field, value, onChange }) {
  // ...
}

// Register em app.tsx
import { registerField } from '@arqel-dev/fields'
import { RichMarkdownInput } from './arqel-dev/fields/RichMarkdownInput'

registerField('RichMarkdownInput', RichMarkdownInput)
```

## 10. Macros

Arqel suporta macros em vários pontos:

```php
// Field macros
Field::macro('priceBRL', function (string $name) {
    return Field::currency($name)
        ->prefix('R$')
        ->decimals(2)
        ->thousandsSeparator('.')
        ->decimalSeparator(',');
});

// Usage
Field::priceBRL('amount')

// Action macros
Action::macro('softDelete', function () {
    return static::make('delete')
        ->icon('trash-2')
        ->color('destructive')
        ->requiresConfirmation()
        ->action(fn ($record) => $record->delete());
});

// Table macros
Table::macro('timestampColumns', function () {
    return [
        Column::date('created_at')->sortable(),
        Column::date('updated_at')->sortable()->hidden(),
    ];
});

// Column macros
Column::macro('moneyColumn', function (string $name, string $currency = 'USD') {
    return Column::text($name)
        ->formatStateUsing(fn ($state) => money_format($state, $currency));
});
```

## 11. Artisan commands

```bash
php artisan arqel:install                  # Initial setup
php artisan arqel:resource User            # Generate Resource
php artisan arqel:resource User --from-model
php artisan arqel:resource User --with-policy
php artisan arqel:field RichMarkdown       # Generate custom field
php artisan arqel:action Publish           # Generate Action class
php artisan arqel:widget TotalUsers        # Generate Widget (Fase 2)
php artisan arqel:panel customer           # Generate new Panel
php artisan arqel:publish                  # Publish config + assets
php artisan arqel:publish --tag=config
php artisan arqel:publish --tag=views
php artisan arqel:mcp                      # Start MCP server (Fase 2)
php artisan arqel:upgrade --from=0.9       # Upgrade CLI
```

## 12. Eventos

Arqel dispara eventos Laravel em pontos-chave:

```php
// Listener examples
Event::listen(\Arqel\Events\RecordCreated::class, function ($event) {
    logger()->info("Record {$event->resource}::{$event->record->id} created.");
});

// Eventos disponíveis:
Arqel\Events\PanelBooted              // Panel registered
Arqel\Events\ResourceRegistered
Arqel\Events\RecordCreated
Arqel\Events\RecordUpdated
Arqel\Events\RecordDeleted
Arqel\Events\RecordRestored
Arqel\Events\ActionStarted
Arqel\Events\ActionCompleted
Arqel\Events\ActionFailed
Arqel\Events\BulkActionProgress        // Fase 2
```

## 13. Facade

Arqel expõe Facade standard:

```php
use Arqel\Facades\Arqel;

Arqel::panel('admin');                    // PanelBuilder
Arqel::getCurrentPanel();                 // Panel
Arqel::getResource(User::class);          // Resource class
Arqel::getResources();                    // array<Resource>
Arqel::url('users.index');                // Named URL
Arqel::hasPanel('customer');              // bool
```

## 14. Próximos documentos

- **`06-api-react.md`** — contratos TypeScript e componentes React.
- **`07-roadmap-fases.md`** — plano mestre das 4 fases com dependências entre APIs.

# Resource

A **Resource** is the single PHP class that wires an Eloquent model into the admin panel: routes, sidebar entry, index/create/edit/show pages, fields, table, actions, and authorization all derive from it. If you only build one thing in Arqel, it's Resources.

This page walks through using `Arqel\Core\Resources\Resource` day to day. For the full method list, see the [`arqel-dev/core` reference](/reference/php/core).

## Creating a Resource

Scaffold one with the Artisan generator:

```bash
php artisan arqel:resource Post --with-policy
```

This produces `app/Arqel/Resources/PostResource.php`:

```php
namespace App\Arqel\Resources;

use App\Models\Post;
use Arqel\Core\Resources\Resource;
use Arqel\Fields\FieldFactory as Field;

final class PostResource extends Resource
{
    protected static string $model = Post::class;
    protected static ?string $navigationIcon = 'document-text';

    public function fields(): array
    {
        return [
            Field::text('title')->required()->maxLength(200),
            Field::slug('slug')->fromField('title')->required(),
            Field::textarea('body')->rows(8),
        ];
    }
}
```

The only two things a Resource strictly needs are `protected static string $model` and a `fields(): array` implementation. Everything else — routes, navigation, table columns — is derived automatically unless you override it.

Register the class on a `Panel` (see the Panels guide) and Arqel takes care of:

- Slug and route names (`PostResource` → `posts`, routes under `arqel.resources.posts.*`)
- Sidebar entry, grouped/sorted by `$navigationGroup`/`$navigationSort`
- Index page with auto-derived columns from `fields()`
- Create/edit forms rendering those same fields
- Authorization via `Gate::denies('create'|'update'|'delete'|'view'|'viewAny', ...)`

## Naming and metadata

Override the static properties to customize how a Resource presents itself — Arqel falls back to sensible defaults derived from the class name when they're absent:

```php
final class PostResource extends Resource
{
    protected static string $model = Post::class;
    protected static ?string $slug = 'articles';
    protected static ?string $label = 'Article';
    protected static ?string $pluralLabel = 'Articles';
    protected static ?string $navigationIcon = 'document-text';
    protected static ?string $navigationGroup = 'Content';
    protected static ?int $navigationSort = 10;
}
```

## Declaring fields

`fields(): array` is the one method you must implement. It's the single source of truth Arqel uses for validation rules, the auto-derived form, and the auto-derived table columns:

```php
public function fields(): array
{
    return [
        Field::text('title')->required()->maxLength(200),
        Field::slug('slug')->fromField('title')->required(),
        Field::textarea('body')->rows(8),
    ];
}
```

See the [Fields guide](/resources/fields) for the full catalog of input types.

## Customizing the table and form

`table()` and `form()` are both optional — when you omit them, Arqel derives a reasonable index table and a single-column form straight from `fields()`. Override them when you need custom columns, filters, or layout:

```php
use Arqel\Table\Table;
use Arqel\Table\Columns\{TextColumn, BadgeColumn, DateColumn};
use Arqel\Actions\Actions;

public function table(): Table
{
    return Table::make()
        ->columns([
            TextColumn::make('title')->sortable()->searchable(),
            BadgeColumn::make('status')->colors([
                'draft' => 'gray',
                'published' => 'green',
            ]),
            DateColumn::make('created_at')->dateTime('d/m/Y H:i'),
        ])
        ->actions([
            Actions::edit(),
            Actions::delete()->visible(fn ($record) => $record->status !== 'published'),
        ]);
}
```

See [Table](/resources/table) and [Form](/resources/form) for the full builder APIs.

## Lifecycle hooks

Override these `protected` hooks to run logic around save/delete — Arqel invokes them automatically from `runCreate`/`runUpdate`/`runDelete`:

```php
final class PostResource extends Resource
{
    protected static string $model = Post::class;

    protected function beforeSave(Model $record, array $data): array
    {
        $record->user_id = auth()->id();

        return $data;
    }

    protected function afterCreate(Model $record): void
    {
        Notification::send($record->user, new PostPublished($record));
    }
}
```

Available hooks:

- `beforeCreate(array $data): array` / `afterCreate(Model $record): void`
- `beforeUpdate(Model $record, array $data): array` / `afterUpdate(Model $record): void`
- `beforeSave(Model $record, array $data): array` / `afterSave(Model $record): void` — fire on both create and update
- `beforeDelete(Model $record): void` / `afterDelete(Model $record): void` — `afterDelete` only runs if `delete()` returned truthy

## Record identity: `recordTitle` and `recordSubtitle`

Control how a record is labeled in breadcrumbs and confirmation modals:

```php
public function recordTitle(Model $record): string
{
    return $record->title;
}

public function recordSubtitle(Model $record): ?string
{
    return $record->author?->name;
}
```

Without an override, Arqel uses `$recordTitleAttribute` (defaults to `title`/`name`, whichever exists on the model).

## Scoping the index: `indexQuery`

```php
public function indexQuery(): Builder
{
    return Post::query()->where('user_id', auth()->id())->latest();
}
```

`indexQuery()` only scopes the index listing — edit/show/delete stay unscoped, so real access control still belongs on a Policy, not here.

## Relation managers

A Resource can expose a parent record's Eloquent relation (`hasMany`/`morphMany`/`belongsToMany`) as a tab on its edit page:

```php
public function relations(): array
{
    return [CommentsRelationManager::class];
}
```

Each entry extends `Arqel\Core\Relations\RelationManager` and declares its own `table()` (required) and, optionally, `fields()`/`form()` for create/edit. See the [`arqel-dev/core` reference](/reference/php/core#relation-managers) for the full contract.

## Global search

Opt a Resource into the command palette's global search by declaring which columns are searchable:

```php
public static function globallySearchable(): array
{
    return ['title', 'slug'];
}
```

Resources that don't override this method (default `[]`) never show up in search results.

## Calling the orchestrators directly

`runCreate`, `runUpdate`, and `runDelete` are the public methods `ResourceController` calls under the hood. You rarely need them directly, but they're useful in feature tests or custom controllers that want to reuse the hook pipeline:

```php
$resource = new PostResource();
$post = $resource->runCreate(['title' => 'Hello', 'slug' => 'hello']);
$resource->runUpdate($post, ['title' => 'Hello world']);
$resource->runDelete($post);
```

Each orchestrator fires the full hook sequence (`beforeSave → beforeCreate/beforeUpdate → fill+save → afterCreate/afterUpdate → afterSave`).

## Next steps

- [Fields](/resources/fields) — the input catalog used by `fields()`
- [Table](/resources/table) — customizing the index listing
- [Form](/resources/form) — customizing create/edit layout
- [Actions](/resources/actions) — row, bulk, toolbar, and header buttons
- Full API: [`arqel-dev/core` reference](/reference/php/core)

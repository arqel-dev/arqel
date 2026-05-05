# Resources

A **Resource** is the declarative unit that links an Eloquent model to the admin: index/create/edit/show, fields, table, actions, auth — everything emerges from a single PHP class.

## The minimum

```bash
php artisan arqel:resource Post --with-policy
```

Generates `app/Arqel/Resources/PostResource.php`:

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

From there Arqel derives:

- Panel slug (`PostResource` → `posts`)
- Routes: `arqel.resources.{index,create,store,show,edit,update,destroy}`
- Sidebar entry with the "Posts" label
- Index page with auto-derived columns
- Create/edit forms with the fields above
- Inertia pages rendered by `@arqel-dev/ui` components

## Naming conventions

| Property | Default | Override |
|---|---|---|
| `$slug` | derived from name (`PostResource` → `posts`) | `protected static ?string $slug = 'articles'` |
| `$label` | "Post" | `protected static ?string $label = 'Article'` |
| `$pluralLabel` | "Posts" | `protected static ?string $pluralLabel = 'Articles'` |
| `$navigationIcon` | `null` | `'document-text'` (`lucide-react` ID) |
| `$navigationGroup` | `null` | `'Content'` (groups in the sidebar) |
| `$navigationSort` | `null` | `int` for ordering |
| `$recordTitleAttribute` | `'name'` or `'title'` | `'subject'` |

## Lifecycle hooks

Override `protected` hooks in your subclass — Arqel calls them around save/delete:

```php
final class PostResource extends Resource
{
    protected static string $model = Post::class;

    protected function beforeSave(Model $record, array $data): void
    {
        $record->user_id = auth()->id();
    }

    protected function afterCreate(Model $record): void
    {
        Notification::send($record->user, new PostPublished($record));
    }
}
```

Available hooks:

- `beforeCreate(Model $record, array $data)` / `afterCreate(Model $record)`
- `beforeUpdate(Model $record, array $data)` / `afterUpdate(Model $record)`
- `beforeSave(Model $record, array $data)` / `afterSave(Model $record)` — fire on both create and update
- `beforeDelete(Model $record)` / `afterDelete(Model $record)` — `afterDelete` only runs if `delete()` returned truthy

## `recordTitle` and `recordSubtitle`

How to display a record's name/identifier (breadcrumbs, confirmation modals, etc.):

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

Default: uses `$recordTitleAttribute` (`'title'`/`'name'`).

## `indexQuery` — scoping the listing

```php
public function indexQuery(Builder $query): Builder
{
    return $query->where('user_id', auth()->id())->latest();
}
```

Applies only to the index. Edit/show stay unscoped (use Policies for real auth).

## Table and Actions

The `table()` and `actions()` methods are optional — Arqel falls back to automatic derivation when absent:

```php
public function table(): Table
{
    return Table::make()
        ->columns([
            TextColumn::make('title')->sortable()->searchable(),
            BadgeColumn::make('status')->colors([
                'draft' => 'gray',
                'published' => 'green',
            ]),
            DateColumn::make('created_at')->displayFormat('d/m/Y H:i'),
        ])
        ->filters([
            SelectFilter::make('status')->options([...]),
        ])
        ->actions([Actions::edit(), Actions::delete()]);
}

public function actions(): array
{
    return [
        Actions::edit(),
        Actions::delete()->visible(fn ($record) => $record->status !== 'published'),
    ];
}
```

See [Tables & Forms](/guide/tables-forms) and [Actions](/guide/actions).

## `Resource::runCreate/runUpdate/runDelete`

These are the public **orchestrators** called by `ResourceController`. You rarely call them directly — they exist for feature tests and for custom controllers that reuse the hook logic:

```php
$resource = new PostResource();
$post = $resource->runCreate(['title' => 'Hello', 'slug' => 'hello']);
$resource->runUpdate($post, ['title' => 'Hello world']);
$resource->runDelete($post);
```

These methods fire the full hook pipeline (`beforeSave → beforeCreate → fill+save → afterCreate → afterSave`).

## Anti-patterns

- Overriding `__construct` — Arqel instantiates Resources via the container; keep the default constructor
- Business logic in `fields()` — that method should be declarative. Side effects belong in hooks
- Forgetting `--with-policy` — without a policy, `Gate::denies` falls back to "silently allow"; OK in dev, dangerous in prod

## Next steps

- [Fields](/guide/fields) — catalog of the 21 inputs
- [Actions](/guide/actions) — buttons and bulk operations
- [Auth](/guide/auth) — Policies + field-level auth

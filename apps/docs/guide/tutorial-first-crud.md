# Tutorial: your first complete CRUD

This tutorial declares an end-to-end blog admin — model + migration + Resource + custom fields + table + Policy. Target time: **< 30 minutes**.

Prerequisites: you've completed [Getting Started](/guide/getting-started) and have a Laravel project running with Arqel installed.

## 1. Scenario

We'll build a blog admin with:

- `Post` — title, slug, body, status (draft/published), author, published at
- `Category` — relation `Post belongsTo Category`
- Only the author can edit/delete their own post
- Only admins see archived posts

## 2. Migration + Model

```bash
php artisan make:model Category -m
php artisan make:model Post -m
```

In `database/migrations/..._create_categories_table.php`:

```php
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->timestamps();
});
```

In `database/migrations/..._create_posts_table.php`:

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->foreignId('category_id')->nullable()->constrained();
    $table->string('title');
    $table->string('slug')->unique();
    $table->text('body');
    $table->string('status')->default('draft');
    $table->timestamp('published_at')->nullable();
    $table->softDeletes();
    $table->timestamps();
});
```

Models in `app/Models/`:

```php
final class Post extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'category_id', 'title', 'slug', 'body', 'status', 'published_at'];

    protected $casts = ['published_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function category() { return $this->belongsTo(Category::class); }
}

final class Category extends Model
{
    protected $fillable = ['name', 'slug'];

    public function posts() { return $this->hasMany(Post::class); }
}
```

```bash
php artisan migrate
```

## 3. Generate UserResource

Already done in [Getting Started](/guide/getting-started). Confirm that `app/Arqel/Resources/UserResource.php` exists.

## 4. Generate PostResource and CategoryResource

```bash
php artisan arqel:resource Category --with-policy
php artisan arqel:resource Post --with-policy
```

## 5. Declare `CategoryResource`

`app/Arqel/Resources/CategoryResource.php`:

```php
namespace App\Arqel\Resources;

use App\Models\Category;
use Arqel\Core\Resources\Resource;
use Arqel\Fields\FieldFactory as Field;

final class CategoryResource extends Resource
{
    protected static string $model = Category::class;
    protected static ?string $navigationIcon = 'folder';
    protected static ?string $navigationGroup = 'Blog';

    public function fields(): array
    {
        return [
            Field::text('name')->required()->maxLength(120),
            Field::slug('slug')->fromField('name')->required()->uniqueIn(Category::class),
        ];
    }
}
```

## 6. Declare `PostResource`

`app/Arqel/Resources/PostResource.php`:

```php
namespace App\Arqel\Resources;

use App\Models\Post;
use Arqel\Actions\Actions;
use Arqel\Actions\Types\RowAction;
use Arqel\Core\Resources\Resource;
use Arqel\Fields\FieldFactory as Field;
use Arqel\Form\Form;
use Arqel\Form\Layout\Section;
use Arqel\Table\Columns\{BadgeColumn, DateColumn, TextColumn};
use Arqel\Table\Filters\SelectFilter;
use Arqel\Table\Table;
use Illuminate\Database\Eloquent\Builder;

final class PostResource extends Resource
{
    protected static string $model = Post::class;
    protected static ?string $navigationIcon = 'document-text';
    protected static ?string $navigationGroup = 'Blog';
    protected static ?int $navigationSort = 10;

    public function form(): Form
    {
        return Form::make()->schema([
            Section::make('Content')
                ->columns(2)
                ->schema([
                    Field::text('title')->required()->maxLength(200)->columnSpanFull(),
                    Field::slug('slug')->fromField('title')->required()->uniqueIn(Post::class),
                    Field::belongsTo('category_id', CategoryResource::class)->searchable()->preload(),
                    Field::textarea('body')->rows(12)->columnSpanFull(),
                ]),

            Section::make('Publishing')
                ->aside()
                ->schema([
                    Field::select('status')->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ])->default('draft')->required(),
                    Field::dateTime('published_at')->visibleIf(fn ($state) => $state['status'] !== 'draft'),
                ]),
        ])->columns(3);
    }

    public function table(): Table
    {
        return Table::make()
            ->columns([
                TextColumn::make('title')->sortable()->searchable()->limit(60),
                TextColumn::make('category.name')->label('Category'),
                TextColumn::make('user.name')->label('Author'),
                BadgeColumn::make('status')->colors([
                    'draft' => 'gray',
                    'published' => 'green',
                    'archived' => 'red',
                ])->sortable(),
                DateColumn::make('published_at')->displayFormat('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                    'archived' => 'Archived',
                ]),
                SelectFilter::make('category_id')->label('Category')
                    ->options(fn () => \App\Models\Category::pluck('name', 'id')->toArray()),
            ])
            ->defaultSort('created_at', 'desc')
            ->perPage(20)
            ->searchable()
            ->selectable()
            ->actions([
                Actions::edit(),
                RowAction::make('publish')
                    ->label('Publish')
                    ->icon('check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->action(fn ($record) => $record->update([
                        'status' => 'published',
                        'published_at' => now(),
                    ]))
                    ->successNotification('Post published!'),
                Actions::delete(),
            ])
            ->bulkActions([Actions::deleteBulk()])
            ->toolbarActions([Actions::create()]);
    }

    public function indexQuery(Builder $query): Builder
    {
        return $query->with(['user', 'category']);
    }

    protected function beforeCreate($record, array $data): void
    {
        $record->user_id = auth()->id();
    }
}
```

::: tip Eager loading
`indexQuery` does `with(['user', 'category'])` to avoid N+1 on the relations shown by the columns. Arqel also auto-detects `BelongsToField` and `HasManyField` in `EagerLoadingResolver` for forms.
:::

## 7. Policy

`app/Policies/PostPolicy.php`:

```php
final class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Post $post): bool
    {
        return $user->is_admin || $post->status !== 'archived';
    }

    public function create(User $user): bool
    {
        return $user->is_author ?? false;
    }

    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->user_id || $user->is_admin;
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->is_admin;
    }
}
```

## 8. Register on the Panel

`app/Providers/ArqelServiceProvider.php`:

```php
$panels->panel('admin')
    ->path('admin')
    ->brand('Acme Blog')
    ->resources([
        UserResource::class,
        CategoryResource::class,
        PostResource::class,
    ])
    ->middleware(['web', 'auth']);
```

## 9. Test the UI

```bash
php artisan serve
pnpm dev
```

Visit <http://127.0.0.1:8000/admin/posts>:

- Index with title/category/author/status/published_at columns
- Status and category filters in the toolbar
- Global search covering title
- Bulk select with "Delete selected"
- Inline "Publish" action (only on drafts)
- Create/edit form with main 2-col section + side publishing sidebar
- Slug auto-derived from title via `slugify` (client-side) and validated server-side via `uniqueIn`

## 10. Deploy considerations

::: warning Before going to prod
- `php artisan optimize` — cache routes/config/views
- `pnpm build` — production bundle in `public/build/`
- `composer install --no-dev --optimize-autoloader`
- Verify `APP_ENV=production` and `APP_DEBUG=false`
- Configure `arqel.cache.driver` to `redis` (Phase 2 — currently mem-only)
- Upload policy: `Field::file('attachment')->disk('s3')->directory('posts')->visibility('private')` instead of the default `local`
:::

## Next steps

- [Custom Fields](/advanced/custom-fields) — create your own RichTextField to replace the `textarea`
- [Macros](/advanced/macros) — shorten repeated configurations
- API reference: [PHP overview](/reference/php-overview)

# Tutorial: primeiro CRUD completo

Esse tutorial declara um blog admin de ponta a ponta — model + migration + Resource + fields customizados + table + Policy. Tempo alvo: **< 30 minutos**.

Pré-requisitos: você já completou o [Getting Started](/guide/getting-started) e tem um projeto Laravel rodando com Arqel instalado.

## 1. Cenário

Vamos construir um blog admin com:

- `Post` — título, slug, body, status (draft/published), autor, publicado em
- `Category` — relação `Post belongsTo Category`
- Apenas o autor pode editar/deletar próprio post
- Apenas admins veem posts archived

## 2. Migration + Model

```bash
php artisan make:model Category -m
php artisan make:model Post -m
```

Em `database/migrations/..._create_categories_table.php`:

```php
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->timestamps();
});
```

Em `database/migrations/..._create_posts_table.php`:

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

Models em `app/Models/`:

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

## 3. Gerar UserResource

Já feito no [Getting Started](/guide/getting-started). Confirme que `app/Arqel/Resources/UserResource.php` existe.

## 4. Gerar PostResource e CategoryResource

```bash
php artisan arqel:resource Category --with-policy
php artisan arqel:resource Post --with-policy
```

## 5. Declarar `CategoryResource`

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

## 6. Declarar `PostResource`

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
            Section::make('Conteúdo')
                ->columns(2)
                ->schema([
                    Field::text('title')->required()->maxLength(200)->columnSpanFull(),
                    Field::slug('slug')->fromField('title')->required()->uniqueIn(Post::class),
                    Field::belongsTo('category_id', CategoryResource::class)->searchable()->preload(),
                    Field::textarea('body')->rows(12)->columnSpanFull(),
                ]),

            Section::make('Publicação')
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
                    ->label('Publicar')
                    ->icon('check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->action(fn ($record) => $record->update([
                        'status' => 'published',
                        'published_at' => now(),
                    ]))
                    ->successNotification('Post publicado!'),
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
`indexQuery` faz `with(['user', 'category'])` para evitar N+1 nas relations exibidas pelas columns. Arqel também detecta automaticamente `BelongsToField` e `HasManyField` em `EagerLoadingResolver` para forms.
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

## 8. Registrar no Panel

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

## 9. Testar a UI

```bash
php artisan serve
pnpm dev
```

Visite <http://127.0.0.1:8000/admin/posts>:

- Index com columns title/category/author/status/published_at
- Filters de status e category na toolbar
- Search global cobrindo title
- Bulk select com "Delete selected"
- Action "Publicar" inline (apenas em drafts)
- Create/edit form com seção principal 2-col + sidebar lateral de publicação
- Slug auto-derivado do title via `slugify` (lado client) e validado server-side via `uniqueIn`

## 10. Deploy considerations

::: warning Antes de subir para prod
- ✅ `php artisan optimize` — cache de routes/config/views
- ✅ `pnpm build` — bundle de produção em `public/build/`
- ✅ `composer install --no-dev --optimize-autoloader`
- ✅ Verifique `APP_ENV=production` e `APP_DEBUG=false`
- ✅ Configure `arqel.cache.driver` para `redis` (Phase 2 — atualmente mem-only)
- ✅ Política de upload: `Field::file('attachment')->disk('s3')->directory('posts')->visibility('private')` em vez do default `local`
:::

## Próximos passos

- [Custom Fields](/advanced/custom-fields) — criar um RichTextField próprio para substituir o `textarea`
- [Macros](/advanced/macros) — encurtar configs repetidas
- API reference: [PHP overview](/reference/php-overview)

# Resources

Um **Resource** é a unidade declarativa que liga um model Eloquent ao admin: index/create/edit/show, fields, table, actions, auth — tudo emerge de uma única class PHP.

## O mínimo

```bash
php artisan arqel:resource Post --with-policy
```

Gera `app/Arqel/Resources/PostResource.php`:

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

A partir daí Arqel deriva:

- Slug do panel (`PostResource` → `posts`)
- Routes: `arqel.resources.{index,create,store,show,edit,update,destroy}`
- Sidebar entry com label "Posts"
- Index page com columns auto-derivadas
- Forms create/edit com os fields acima
- Inertia pages renderizadas pelos componentes de `@arqel/ui`

## Convenções de naming

| Property | Default | Override |
|---|---|---|
| `$slug` | derivado do nome (`PostResource` → `posts`) | `protected static ?string $slug = 'articles'` |
| `$label` | "Post" | `protected static ?string $label = 'Article'` |
| `$pluralLabel` | "Posts" | `protected static ?string $pluralLabel = 'Articles'` |
| `$navigationIcon` | `null` | `'document-text'` (ID do `lucide-react`) |
| `$navigationGroup` | `null` | `'Content'` (agrupa na sidebar) |
| `$navigationSort` | `null` | `int` para ordenar |
| `$recordTitleAttribute` | `'name'` ou `'title'` | `'subject'` |

## Lifecycle hooks

Override hooks `protected` na sua subclass — Arqel chama-os ao redor de save/delete:

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

Hooks disponíveis:

- `beforeCreate(Model $record, array $data)` / `afterCreate(Model $record)`
- `beforeUpdate(Model $record, array $data)` / `afterUpdate(Model $record)`
- `beforeSave(Model $record, array $data)` / `afterSave(Model $record)` — disparam em ambos create e update
- `beforeDelete(Model $record)` / `afterDelete(Model $record)` — `afterDelete` só roda se o `delete()` retornou truthy

## `recordTitle` e `recordSubtitle`

Como exibir o nome/identificador de um record (breadcrumbs, modais de confirmação, etc):

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

Default: usa `$recordTitleAttribute` (`'title'`/`'name'`).

## `indexQuery` — escopar a listagem

```php
public function indexQuery(Builder $query): Builder
{
    return $query->where('user_id', auth()->id())->latest();
}
```

Aplica-se apenas ao index. Edit/show continuam unscoped (use Policies para auth real).

## Tabela e Actions

Os métodos `table()` e `actions()` são opcionais — Arqel cai em derivação automática quando ausentes:

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

Veja [Tables & Forms](/guide/tables-forms) e [Actions](/guide/actions).

## `Resource::runCreate/runUpdate/runDelete`

São os **orchestrators** públicos chamados pelo `ResourceController`. Você raramente os chama direto — eles existem para testes feature e para custom controllers que reusam a lógica de hooks:

```php
$resource = new PostResource();
$post = $resource->runCreate(['title' => 'Hello', 'slug' => 'hello']);
$resource->runUpdate($post, ['title' => 'Hello world']);
$resource->runDelete($post);
```

Esses métodos disparam o pipeline completo de hooks (`beforeSave → beforeCreate → fill+save → afterCreate → afterSave`).

## Anti-patterns

- ❌ **Override `__construct`** — Arqel instancia Resources via container; mantenha o construtor default
- ❌ **Negócio em `fields()`** — esse método deve ser declarativo. Side effects vão em hooks
- ❌ **Esquecer `--with-policy`** — sem policy, Gate::denies cai em "silently allow"; OK em dev, perigoso em prod

## Próximos passos

- [Fields](/guide/fields) — catálogo dos 21 inputs
- [Actions](/guide/actions) — botões e bulk operations
- [Auth](/guide/auth) — Policies + field-level auth

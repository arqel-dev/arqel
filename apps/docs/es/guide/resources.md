# Resources

Un **Resource** es la unidad declarativa que vincula un modelo Eloquent al admin: index/create/edit/show, fields, table, actions, auth — todo emerge de una única clase PHP.

## Lo mínimo

```bash
php artisan arqel:resource Post --with-policy
```

Genera `app/Arqel/Resources/PostResource.php`:

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

A partir de ahí, Arqel deriva:

- Slug del panel (`PostResource` → `posts`)
- Rutas: `arqel.resources.{index,create,store,show,edit,update,destroy}`
- Entrada del Sidebar con la etiqueta "Posts"
- Página index con columnas auto-derivadas
- Formularios de create/edit con los fields anteriores
- Páginas Inertia renderizadas por componentes de `@arqel-dev/ui`

## Convenciones de nombres

| Propiedad | Por defecto | Override |
|---|---|---|
| `$slug` | derivado del nombre (`PostResource` → `posts`) | `protected static ?string $slug = 'articles'` |
| `$label` | "Post" | `protected static ?string $label = 'Article'` |
| `$pluralLabel` | "Posts" | `protected static ?string $pluralLabel = 'Articles'` |
| `$navigationIcon` | `null` | `'document-text'` (ID de `lucide-react`) |
| `$navigationGroup` | `null` | `'Content'` (agrupa en el sidebar) |
| `$navigationSort` | `null` | `int` para ordenar |
| `$recordTitleAttribute` | `'name'` o `'title'` | `'subject'` |

## Hooks de ciclo de vida

Sobrescribe los hooks `protected` en tu subclase — Arqel los llama alrededor de save/delete:

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

Hooks disponibles:

- `beforeCreate(Model $record, array $data)` / `afterCreate(Model $record)`
- `beforeUpdate(Model $record, array $data)` / `afterUpdate(Model $record)`
- `beforeSave(Model $record, array $data)` / `afterSave(Model $record)` — se disparan tanto en create como en update
- `beforeDelete(Model $record)` / `afterDelete(Model $record)` — `afterDelete` solo se ejecuta si `delete()` devolvió truthy

## `recordTitle` y `recordSubtitle`

Cómo mostrar el nombre/identificador de un registro (breadcrumbs, modales de confirmación, etc.):

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

Por defecto: usa `$recordTitleAttribute` (`'title'`/`'name'`).

## `indexQuery` — limitando el listado

```php
public function indexQuery(Builder $query): Builder
{
    return $query->where('user_id', auth()->id())->latest();
}
```

Se aplica solo al index. Edit/show quedan sin scope (usa Policies para auth real).

## Table y Actions

Los métodos `table()` y `actions()` son opcionales — Arqel recurre a derivación automática cuando faltan:

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

Ver [Tables & Forms](/es/guide/tables-forms) y [Actions](/es/guide/actions).

## `Resource::runCreate/runUpdate/runDelete`

Estos son los **orquestadores** públicos llamados por `ResourceController`. Raramente los llamarás directamente — existen para tests de feature y para controllers personalizados que reutilicen la lógica de hooks:

```php
$resource = new PostResource();
$post = $resource->runCreate(['title' => 'Hello', 'slug' => 'hello']);
$resource->runUpdate($post, ['title' => 'Hello world']);
$resource->runDelete($post);
```

Estos métodos disparan el pipeline completo de hooks (`beforeSave → beforeCreate → fill+save → afterCreate → afterSave`).

## Anti-patrones

- Sobrescribir `__construct` — Arqel instancia los Resources vía el container; mantén el constructor por defecto
- Lógica de negocio en `fields()` — ese método debe ser declarativo. Los efectos colaterales van en hooks
- Olvidar `--with-policy` — sin policy, `Gate::denies` cae en "permitir silenciosamente"; OK en dev, peligroso en prod

## Próximos pasos

- [Fields](/es/guide/fields) — catálogo de los 21 inputs
- [Actions](/es/guide/actions) — botones y operaciones bulk
- [Auth](/es/guide/auth) — Policies + auth a nivel de Field

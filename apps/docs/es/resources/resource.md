# Resource

Un **Resource** es la única clase PHP que conecta un modelo Eloquent con el panel de administración: rutas, entrada en la barra lateral, páginas de index/create/edit/show, fields, table, actions y autorización se derivan todos de él. Si solo vas a construir una cosa en Arqel, son los Resources.

Esta página recorre el uso cotidiano de `Arqel\Core\Resources\Resource`. Para la lista completa de métodos, consulta la [referencia de `arqel-dev/core`](/es/reference/php/core).

## Crear un Resource

Genera uno con el generador de Artisan:

```bash
php artisan arqel:resource Post --with-policy
```

Esto produce `app/Arqel/Resources/PostResource.php`:

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

Las dos únicas cosas que un Resource necesita estrictamente son `protected static string $model` y una implementación de `fields(): array`. Todo lo demás — rutas, navegación, columnas de la tabla — se deriva automáticamente a menos que lo sobrescribas.

Registra la clase en un `Panel` (consulta la guía de Panels) y Arqel se encarga de:

- Slug y nombres de ruta (`PostResource` → `posts`, rutas bajo `arqel.resources.posts.*`)
- Entrada en la barra lateral, agrupada y ordenada por `$navigationGroup`/`$navigationSort`
- Página de index con columnas autoderivadas a partir de `fields()`
- Formularios de create/edit que renderizan esos mismos fields
- Autorización vía `Gate::denies('create'|'update'|'delete'|'view'|'viewAny', ...)`

## Nomenclatura y metadatos

Sobrescribe las propiedades estáticas para personalizar cómo se presenta un Resource — Arqel recurre a valores por defecto sensatos derivados del nombre de la clase cuando están ausentes:

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

## Declarar fields

`fields(): array` es el único método que debes implementar. Es la única fuente de verdad que Arqel usa para las reglas de validación, el formulario autoderivado y las columnas autoderivadas de la tabla:

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

Consulta la [guía de Fields](/es/resources/fields) para el catálogo completo de tipos de input.

## Personalizar la table y el form

`table()` y `form()` son ambos opcionales — cuando los omites, Arqel deriva una tabla de index razonable y un formulario de una sola columna directamente desde `fields()`. Sobrescríbelos cuando necesites columnas, filtros o layout personalizados:

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

Consulta [Table](/es/resources/table) y [Form](/es/resources/form) para las APIs completas de los builders.

## Hooks de ciclo de vida

Sobrescribe estos hooks `protected` para ejecutar lógica alrededor de guardar/eliminar — Arqel los invoca automáticamente desde `runCreate`/`runUpdate`/`runDelete`:

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

Hooks disponibles:

- `beforeCreate(array $data): array` / `afterCreate(Model $record): void`
- `beforeUpdate(Model $record, array $data): array` / `afterUpdate(Model $record): void`
- `beforeSave(Model $record, array $data): array` / `afterSave(Model $record): void` — se disparan tanto en create como en update
- `beforeDelete(Model $record): void` / `afterDelete(Model $record): void` — `afterDelete` solo se ejecuta si `delete()` devolvió un valor verdadero

## Identidad del registro: `recordTitle` y `recordSubtitle`

Controla cómo se etiqueta un registro en las migas de pan y en los modales de confirmación:

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

Sin una sobrescritura, Arqel usa `$recordTitleAttribute` (por defecto `title`/`name`, el que exista en el modelo).

## Acotar el index: `indexQuery`

```php
public function indexQuery(): Builder
{
    return Post::query()->where('user_id', auth()->id())->latest();
}
```

`indexQuery()` solo acota el listado del index — edit/show/delete quedan sin acotar, así que el control de acceso real sigue perteneciendo a una Policy, no aquí.

## Relation managers

Un Resource puede exponer una relación Eloquent de un registro padre (`hasMany`/`morphMany`/`belongsToMany`) como una pestaña en su página de edición:

```php
public function relations(): array
{
    return [CommentsRelationManager::class];
}
```

Cada entrada extiende `Arqel\Core\Relations\RelationManager` y declara su propio `table()` (obligatorio) y, opcionalmente, `fields()`/`form()` para create/edit. Consulta la [referencia de `arqel-dev/core`](/es/reference/php/core#relation-managers) para el contrato completo.

## Búsqueda global

Habilita un Resource en la búsqueda global de la paleta de comandos declarando qué columnas son buscables:

```php
public static function globallySearchable(): array
{
    return ['title', 'slug'];
}
```

Los Resources que no sobrescriben este método (por defecto `[]`) nunca aparecen en los resultados de búsqueda.

## Llamar a los orquestadores directamente

`runCreate`, `runUpdate` y `runDelete` son los métodos públicos que `ResourceController` llama por debajo. Rara vez los necesitas directamente, pero resultan útiles en tests de feature o en controladores personalizados que quieran reutilizar el pipeline de hooks:

```php
$resource = new PostResource();
$post = $resource->runCreate(['title' => 'Hello', 'slug' => 'hello']);
$resource->runUpdate($post, ['title' => 'Hello world']);
$resource->runDelete($post);
```

Cada orquestador dispara la secuencia completa de hooks (`beforeSave → beforeCreate/beforeUpdate → fill+save → afterCreate/afterUpdate → afterSave`).

## Próximos pasos

- [Fields](/es/resources/fields) — el catálogo de inputs usado por `fields()`
- [Table](/es/resources/table) — personalizar el listado del index
- [Form](/es/resources/form) — personalizar el layout de create/edit
- [Actions](/es/resources/actions) — botones de fila, en lote, de toolbar y de header
- API completa: [referencia de `arqel-dev/core`](/es/reference/php/core)

# Resource

Um **Resource** é a única class PHP que liga um model Eloquent ao admin panel: routes, entrada na sidebar, páginas index/create/edit/show, fields, table, actions e autorização derivam todos dele. Se você for construir uma única coisa no Arqel, são os Resources.

Esta página mostra o uso do `Arqel\Core\Resources\Resource` no dia a dia. Para a lista completa de métodos, veja a [referência do `arqel-dev/core`](/pt-BR/reference/php/core).

## Criando um Resource

Gere um com o generator do Artisan:

```bash
php artisan arqel:resource Post --with-policy
```

Isso produz `app/Arqel/Resources/PostResource.php`:

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

As únicas duas coisas de que um Resource estritamente precisa são `protected static string $model` e uma implementação de `fields(): array`. Todo o resto — routes, navegação, columns da table — é derivado automaticamente, a menos que você sobrescreva.

Registre a class em um `Panel` (veja o guia de Panels) e o Arqel cuida de:

- Slug e nomes de route (`PostResource` → `posts`, routes sob `arqel.resources.posts.*`)
- Entrada na sidebar, agrupada/ordenada por `$navigationGroup`/`$navigationSort`
- Página de index com columns auto-derivadas de `fields()`
- Forms de create/edit renderizando esses mesmos fields
- Autorização via `Gate::denies('create'|'update'|'delete'|'view'|'viewAny', ...)`

## Nomenclatura e metadados

Sobrescreva as propriedades estáticas para personalizar como um Resource se apresenta — o Arqel cai em defaults sensatos derivados do nome da class quando elas estão ausentes:

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

## Declarando fields

`fields(): array` é o único método que você precisa implementar. É a fonte única de verdade que o Arqel usa para as regras de validação, o form auto-derivado e as columns auto-derivadas da table:

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

Veja o [guia de Fields](/pt-BR/resources/fields) para o catálogo completo de tipos de input.

## Personalizando table e form

`table()` e `form()` são ambos opcionais — quando você os omite, o Arqel deriva uma table de index razoável e um form de coluna única direto de `fields()`. Sobrescreva-os quando precisar de columns customizadas, filtros ou layout:

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

Veja [Table](/pt-BR/resources/table) e [Form](/pt-BR/resources/form) para as APIs completas dos builders.

## Lifecycle hooks

Sobrescreva estes hooks `protected` para rodar lógica em torno de save/delete — o Arqel os invoca automaticamente a partir de `runCreate`/`runUpdate`/`runDelete`:

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

Hooks disponíveis:

- `beforeCreate(array $data): array` / `afterCreate(Model $record): void`
- `beforeUpdate(Model $record, array $data): array` / `afterUpdate(Model $record): void`
- `beforeSave(Model $record, array $data): array` / `afterSave(Model $record): void` — disparam tanto no create quanto no update
- `beforeDelete(Model $record): void` / `afterDelete(Model $record): void` — `afterDelete` só roda se o `delete()` retornou truthy

## Identidade do record: `recordTitle` e `recordSubtitle`

Controle como um record é rotulado em breadcrumbs e modais de confirmação:

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

Sem um override, o Arqel usa `$recordTitleAttribute` (default `title`/`name`, o que existir no model).

## Escopando o index: `indexQuery`

```php
public function indexQuery(): Builder
{
    return Post::query()->where('user_id', auth()->id())->latest();
}
```

`indexQuery()` escopa apenas a listagem do index — edit/show/delete continuam sem escopo, então o controle de acesso de verdade continua pertencendo a uma Policy, não aqui.

## Relation managers

Um Resource pode expor uma relation Eloquent do record pai (`hasMany`/`morphMany`/`belongsToMany`) como uma aba na sua página de edit:

```php
public function relations(): array
{
    return [CommentsRelationManager::class];
}
```

Cada entrada estende `Arqel\Core\Relations\RelationManager` e declara sua própria `table()` (obrigatória) e, opcionalmente, `fields()`/`form()` para create/edit. Veja a [referência do `arqel-dev/core`](/pt-BR/reference/php/core#relation-managers) para o contrato completo.

## Busca global

Habilite um Resource na busca global do command palette declarando quais colunas são pesquisáveis:

```php
public static function globallySearchable(): array
{
    return ['title', 'slug'];
}
```

Resources que não sobrescrevem este método (default `[]`) nunca aparecem nos resultados da busca.

## Chamando os orchestrators diretamente

`runCreate`, `runUpdate` e `runDelete` são os métodos públicos que o `ResourceController` chama por baixo dos panos. Você raramente precisa deles diretamente, mas eles são úteis em testes de feature ou em controllers customizados que queiram reusar o pipeline de hooks:

```php
$resource = new PostResource();
$post = $resource->runCreate(['title' => 'Hello', 'slug' => 'hello']);
$resource->runUpdate($post, ['title' => 'Hello world']);
$resource->runDelete($post);
```

Cada orchestrator dispara a sequência completa de hooks (`beforeSave → beforeCreate/beforeUpdate → fill+save → afterCreate/afterUpdate → afterSave`).

## Próximos passos

- [Fields](/pt-BR/resources/fields) — o catálogo de inputs usado por `fields()`
- [Table](/pt-BR/resources/table) — personalizando a listagem do index
- [Form](/pt-BR/resources/form) — personalizando o layout de create/edit
- [Actions](/pt-BR/resources/actions) — botões de linha, em lote, de toolbar e de header
- API completa: [referência do `arqel-dev/core`](/pt-BR/reference/php/core)

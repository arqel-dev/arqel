# Table

`Arqel\Table\Table` é o builder fluente por trás da página de index de um Resource: columns, filtros, ordenação, paginação, busca e actions de linha/lote/toolbar. Ele é serializado como um schema no payload do Inertia, que o `<DataTable>` do `@arqel-dev/ui` renderiza no cliente.

Você não precisa declarar um método `table()` de forma alguma — o Arqel deriva uma table razoável direto de `Resource::fields()`. Recorra a uma `Table` customizada quando precisar de tipos de column, filtros ou comportamentos que a derivação automática não consegue expressar.

Referência completa de métodos: [`arqel-dev/table`](/pt-BR/reference/php/table).

## O mínimo

```php
use Arqel\Table\Table;
use Arqel\Table\Columns\TextColumn;

public function table(): Table
{
    return Table::make()
        ->columns([
            TextColumn::make('title')->sortable()->searchable(),
            TextColumn::make('author.name')->label('Author'),
        ]);
}
```

## Tipos de column

As columns seguem a convenção da ADR-019: classes concretas, não um alias de factory (`TextColumn::make('name')`, não `Column::text('name')`).

| Class | Caso de uso |
|---|---|
| `TextColumn` | String/texto padrão |
| `BadgeColumn` | Status com cores |
| `BooleanColumn` | Marca de seleção |
| `DateColumn` | Datas formatadas (`date`, `dateTime`, `since`) |
| `NumberColumn` | Valor numérico alinhado à direita |
| `IconColumn` | Ícone único |
| `ImageColumn` | Miniatura |
| `RelationshipColumn` | Relation com eager loading |
| `ComputedColumn` | Valor derivado de uma closure |
| `SelectColumn` | Célula de select editável (edição inline) |
| `TextInputColumn` | Célula editável de input de texto inline |
| `ToggleColumn` | Célula de toggle editável |

Todas as columns compartilham uma superfície comum de setters: `label`, `sortable(bool)`, `searchable(bool)`, `hidden(bool)`, `hiddenOnMobile(bool)`, `align(string)`, `width(string)`, `tooltip(string|Closure)`.

```php
use Arqel\Table\Columns\{BadgeColumn, DateColumn, RelationshipColumn};

->columns([
    BadgeColumn::make('status')->colors([
        'draft' => 'gray',
        'published' => 'green',
    ]),
    DateColumn::make('created_at')->since(),
    RelationshipColumn::make('category')->display('name'),
])
```

## Filtros

```php
use Arqel\Table\Filters\{SelectFilter, DateRangeFilter, TernaryFilter};

public function table(): Table
{
    return Table::make()
        ->columns([...])
        ->filters([
            SelectFilter::make('status')->options([
                'draft' => 'Draft',
                'published' => 'Published',
            ]),
            DateRangeFilter::make('created_at'),
            TernaryFilter::make('is_featured'),
        ]);
}
```

Oito tipos de filtro estão disponíveis: `SelectFilter`, `MultiSelectFilter`, `DateRangeFilter`, `TextFilter`, `TernaryFilter`, `ScopeFilter` (encapsula um scope do Eloquent), `QueryBuilderFilter` (uma árvore visual de condições AND/OR) e `TrashedFilter` (três estados de soft delete: sem/com/apenas).

## Ordenação, busca e paginação

```php
Table::make()
    ->defaultSort('created_at', 'desc')
    ->perPage(25)
    ->perPageOptions([10, 25, 50, 100])
    ->searchable()   // busca global entre colunas
    ->selectable()   // adiciona checkboxes + habilita bulk actions
    ->striped()
    ->compact();
```

A ordenação passa por uma allowlist no servidor, restrita às columns marcadas como `sortable()` — nomes arbitrários de coluna na requisição são ignorados. `per_page` é validado contra `perPageOptions`, e o eager loading é inferido automaticamente a partir de qualquer `RelationshipColumn` que você declarar, via `TableQueryBuilder`.

## Actions

Actions de linha, em lote e de toolbar são associadas diretamente à table:

```php
use Arqel\Actions\Actions;

Table::make()
    ->columns([...])
    ->actions([Actions::edit(), Actions::delete()])
    ->bulkActions([Actions::deleteBulk()])
    ->toolbarActions([Actions::create()]);
```

`bulkActions()` só tem efeito quando `selectable()` também está definido. Veja [Actions](/pt-BR/resources/actions) para o conjunto completo de variantes e como escrever as suas.

## Estado vazio

```php
Table::make()->emptyState([
    'icon' => 'inbox',
    'title' => 'No posts yet',
    'description' => 'Create your first post to get started.',
]);
```

## Anti-patterns

- Colocar lógica de escopo de query dentro de `table()` — use `Resource::indexQuery()`; a `Table` apenas descreve a apresentação.
- Recorrer a `Column::make()` por hábito — sempre use a class concreta (`TextColumn::make`, `BadgeColumn::make`, ...); a convenção de factory com alias é exclusiva de `Field` (ADR-019).
- Adicionar `bulkActions()` sem `selectable()` — os checkboxes não são renderizados e as actions ficam inacessíveis.

## Próximos passos

- [Resource](/pt-BR/resources/resource) — onde `table()` é declarado
- [Actions](/pt-BR/resources/actions) — botões de linha/lote/toolbar em profundidade
- API completa: [referência do `arqel-dev/table`](/pt-BR/reference/php/table)

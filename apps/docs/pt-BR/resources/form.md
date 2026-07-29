# Form

`Arqel\Form\Form` é o builder fluente por trás das páginas de create/edit de um Resource. Ele organiza Fields em seções, grids, abas e outros componentes de layout, e depois é serializado como um schema que o `<FormRenderer>` do `@arqel-dev/ui` renderiza no cliente.

Assim como `table()`, o método `form()` em um Resource é opcional. Quando você não o declara, o Arqel renderiza `Resource::fields()` em um layout direto de coluna única. Recorra a um `Form` customizado quando precisar de layouts com múltiplas colunas, seções, abas ou visibilidade condicional no nível do layout.

Referência completa de métodos: [`arqel-dev/form`](/pt-BR/reference/php/form).

## O mínimo

```php
use Arqel\Form\Form;
use Arqel\Form\Layout\{Section, Grid, Tabs, Tab};
use Arqel\Fields\FieldFactory as Field;

public function form(): Form
{
    return Form::make()->schema([
        Section::make('Content')
            ->description('Post title and body')
            ->schema([
                Field::text('title')->required()->columnSpan(2),
                Field::slug('slug')->fromField('title'),
            ])
            ->columns(2),

        Section::make('Publishing')
            ->aside()   // renderiza como painel lateral no desktop
            ->schema([
                Field::select('status')->options(['draft' => 'Draft', 'published' => 'Published']),
                Field::dateTime('published_at'),
            ]),
    ])->columns(3);
}
```

`schema()` aceita uma mistura heterogênea de Fields e componentes de layout — aninhe Fields dentro de componentes de layout à vontade, mas nunca o contrário.

## Componentes de layout

`Arqel\Form\Layout\Component` é a base abstrata compartilhada — todo componente de layout suporta `$columnSpan`, `$visibleIf` e `$canSee`.

| Class | Setters extras | Caso de uso |
|---|---|---|
| `Section` | `heading`, `description`, `icon`, `collapsible`, `collapsed`, `columns`, `compact`, `aside` | Um bloco com título, opcionalmente recolhível |
| `Fieldset` | `legend`, `columns` | Agrupamento leve com uma legenda |
| `Grid` | `columns(int)` ou `columns(['sm' => 1, 'md' => 2, 'lg' => 4])`, `gap` | Grid de colunas responsivo |
| `Columns` | — | Atalho semântico para `Grid::columns(2)` |
| `Group` | `orientation('horizontal'\|'vertical')` | Sem elementos visuais, apenas controle de fluxo |
| `Tabs` | `tabs(array<Tab>)`, `defaultTab(id)`, `vertical()`/`horizontal()` | Seções em abas |
| `Tab` | `id`, `label`, `icon`, `badge(int\|Closure)` | Uma aba dentro de `Tabs` |

`Section::collapsed()` ativa implicitamente `collapsible()` — você não precisa chamar os dois.

## Abas com badge

```php
Tabs::make()->tabs([
    Tab::make('content', 'Content')->schema([...]),
    Tab::make('seo', 'SEO')->schema([...]),
    Tab::make('comments', 'Comments')
        ->badge(fn ($record) => $record?->comments_count ?? 0)
        ->schema([...]),
])->defaultTab('content');
```

`badge()` aceita um `int` ou uma `Closure(?Model): int`. Uma closure que retorna algo diferente de um int é silenciosamente descartada em vez de renderizada.

## Visibilidade condicional no nível do layout

Componentes de layout — não apenas Fields — podem ocultar blocos inteiros:

```php
Section::make('Admin only')
    ->canSee(fn ($user) => $user?->is_admin)
    ->schema([
        Field::text('internal_id'),
    ]);
```

`isVisibleFor(?Model $record)` avalia `canSee` antes de `visibleIf`, com a mesma precedência que existe nos Fields.

## Opções no nível raiz

```php
Form::make()
    ->schema([...])
    ->columns(3)                       // colunas do grid raiz (limitadas a >= 1)
    ->model(Post::class)               // dica de model para o cliente
    ->inline()                         // renderiza inline em vez de em um modal
    ->disabled();                      // desabilita todos os fields globalmente
```

## FormRequests gerados

`php artisan arqel:resource Post --with-form-requests` gera `app/Http/Requests/StorePostRequest.php` e `UpdatePostRequest.php`:

```php
final class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Post::class);
    }

    public function rules(): array
    {
        return app(FieldRulesExtractor::class)->extract(
            app(PostResource::class)->fields()
        );
    }
}
```

Esses arquivos são gerados uma única vez — o Arqel nunca os sobrescreve silenciosamente em execuções seguintes (passe `--force` para regerar). Edite-os à vontade; o `FieldRulesExtractor` também expõe `extractMessages()` e `extractAttributes()` caso você queira mensagens de validação ou nomes de atributo customizados vindos das mesmas declarações de Field.

## Anti-patterns

- Redeclarar `fields()` dentro de `form()` apenas por causa do layout — reutilize as mesmas instâncias de Field via `fields()`/`effectiveFields()` e apenas envolva-as em componentes de layout.
- Um `form()` totalmente customizado quando uma única coluna basta — omita `form()` por completo e deixe o Arqel derivar automaticamente a partir de `fields()`.
- Aninhar um componente de layout dentro de um Field — a relação só funciona no outro sentido; o layout é sempre o pai.

## Próximos passos

- [Fields](/pt-BR/resources/fields) — o catálogo de inputs colocado dentro do schema de um form
- [Resource](/pt-BR/resources/resource) — onde `form()` é declarado
- API completa: [referência do `arqel-dev/form`](/pt-BR/reference/php/form)

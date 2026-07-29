# Fields

Um **Field** descreve um pedaço de dado em um model: seu tipo de input, label, regras de validação, visibilidade por contexto, dependências de outros fields e quem tem permissão para ver ou editar. Fields são a fonte única de verdade que o Arqel usa para derivar validação, o form gerado automaticamente e as columns de table geradas automaticamente a partir de `Resource::fields()`.

O Arqel traz 21 tipos canônicos de field com mapeamento 1:1 entre a class PHP (`arqel-dev/fields`) e seu componente React de input (`@arqel-dev/fields`). Referência completa, prop a prop: [`arqel-dev/fields`](/pt-BR/reference/php/fields).

## O mínimo

Conforme a ADR-019, Fields usam a convenção de factory com alias — importe `FieldFactory` como `Field` e chame-o como um construtor com namespace:

```php
use Arqel\Fields\FieldFactory as Field;

public function fields(): array
{
    return [
        Field::text('name')->required(),
        Field::email('email')->required()->unique(User::class, 'email'),
        Field::password('password')->required()->minLength(8),
    ];
}
```

Nunca instancie uma class de Field diretamente (`new TextField(...)`) — `Field::__construct` intencionalmente não é o ponto de entrada público; sempre passe pela factory.

## Catálogo

| Chamada da factory | Class PHP | Componente React | Caso de uso |
|---|---|---|---|
| `Field::text($name)` | `TextField` | `TextInput` | Strings curtas |
| `Field::textarea($name)` | `TextareaField` | `TextareaInput` | Texto de múltiplas linhas |
| `Field::email($name)` | `EmailField` | `EmailInput` | E-mail, com a regra `email` já aplicada |
| `Field::url($name)` | `UrlField` | `UrlInput` | URL, com a regra `url` já aplicada |
| `Field::password($name)` | `PasswordField` | `PasswordInput` | Senha com botão de revelar |
| `Field::slug($name)` | `SlugField` | `SlugInput` | Slug normalizado, opcionalmente derivado de outro field |
| `Field::number($name)` | `NumberField` | `NumberInput` | Inteiros/decimais com stepper |
| `Field::currency($name)` | `CurrencyField` | `CurrencyInput` | Valores monetários com formatação por locale |
| `Field::boolean($name)` | `BooleanField` | `Checkbox` | Verdadeiro/falso como checkbox |
| `Field::toggle($name)` | `ToggleField` | `Toggle` | Verdadeiro/falso como switch |
| `Field::select($name)` | `SelectField` | `SelectInput` | Seletor de valor único |
| `Field::multiSelect($name)` | `MultiSelectField` | `MultiSelectInput` | Seletor de múltiplos valores (chips) |
| `Field::radio($name)` | `RadioField` | `RadioGroup` | Seletor de valor único como radio buttons |
| `Field::belongsTo($name, $resource)` | `BelongsToField` | `BelongsToInput` | Foreign key, combobox pesquisável de forma assíncrona |
| `Field::hasMany($name, $resource)` | `HasManyField` | `HasManyReadonly` | Lista de registros relacionados, somente leitura |
| `Field::date($name)` | `DateField` | `DateInput` | Data nativa |
| `Field::dateTime($name)` | `DateTimeField` | `DateTimeInput` | Data e hora nativas |
| `Field::file($name)` | `FileField` | `FileInput` | Upload com arrastar e soltar |
| `Field::image($name)` | `ImageField` | `ImageInput` | Upload com preview + crop |
| `Field::color($name)` | `ColorField` | `ColorInput` | Seletor de cor com presets |
| `Field::hidden($name)` | `HiddenField` | `HiddenInput` | `<input type="hidden">` |

## API fluente comum

Todo Field compartilha uma superfície base de setters, além dos setters contribuídos por quatro traits (`HasValidation`, `HasVisibility`, `HasDependencies`, `HasAuthorization`):

```php
Field::text('title')
    ->label('Article title')
    ->placeholder('e.g. "Why Arqel beats Filament"')
    ->helperText('Appears as <title> on the public page')
    ->required()
    ->maxLength(200)
    ->minLength(3)
    ->columnSpan(2)             // span no grid do layout do form
    ->columnSpanFull()          // span = total de colunas do form
    ->disabled()                // ou disabled(fn ($record) => $record?->locked)
    ->readonly()
    ->dehydrated(false)         // excluído do payload de save
    ->live()                    // re-renderiza o form a cada tecla digitada
    ->liveDebounced(300)        // o mesmo, com debounce em ms
    ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state)));
```

## Validação

As regras seguem as convenções do Laravel e alimentam tanto a validação no servidor quanto a ponte Zod no cliente:

```php
Field::text('email')
    ->required()
    ->rule('email')
    ->unique(User::class, 'email')
    ->validationAttribute('email address')
    ->validationMessage('That email is already registered.');
```

Cada Field expõe `getValidationRules()`. O `FormRequestGenerator` (invocado via `php artisan arqel:resource {Model} --with-form-requests`) coleta essas regras de todos os fields usando o `FieldRulesExtractor` para construir `Store{Model}Request`/`Update{Model}Request`. No cliente, o `ValidationBridge` traduz as mesmas regras em um schema Zod para validação opcional em tempo real — note que regras exclusivas do servidor, como `confirmed`, `password` e `current_password`, são intencionalmente ignoradas pela ponte.

## Visibilidade

Fields podem ser exibidos ou ocultados por contexto de renderização:

```php
Field::text('internal_note')
    ->hiddenOnTable()                   // nunca exibido no index
    ->hiddenOnDetail()                  // nunca exibido na página de show
    ->visibleOn(['edit'])               // apenas no edit
    ->visibleIf(fn ($record) => $record?->is_admin);
```

Os quatro contextos são `create`, `edit`, `detail` e `table`. `visibleIf` e `hiddenIf` são mutuamente exclusivos — defina um ou outro, nunca os dois.

## Dependências entre fields

```php
Field::select('country')
    ->options(Country::pluck('name', 'id')->toArray()),

Field::select('state')
    ->dependsOn(['country'])
    ->resolveOptionsUsing(fn ($state) =>
        State::where('country_id', $state['country'] ?? null)->pluck('name', 'id')->toArray()
    ),
```

Quando `country` muda, o Arqel dispara um partial reload do Inertia com debounce (300ms) restrito a `fields.state.options` — sem TanStack Query nem qualquer biblioteca de fetch no cliente (ADR-016).

## Autorização

Visibilidade/editabilidade no nível do field é açúcar de UX, não a fronteira de segurança:

```php
Field::text('salary')
    ->canSee(fn ($user, $record) => $user?->hasRole('hr'))
    ->canEdit(fn ($user, $record) => $user?->id === $record?->user_id);
```

O servidor sempre revalida através de Policies, independentemente do que `canSee`/`canEdit` decidiram no cliente.

## Relations: `belongsTo` e `hasMany`

```php
Field::belongsTo('role_id', RoleResource::class)
    ->searchable()
    ->preload();
```

O nome da relation é derivado removendo o sufixo `_id` do nome do field (`role_id` → `role`). O `HasManyField` renderiza uma lista somente leitura na Fase 1; o `EagerLoadingResolver` adiciona ambos automaticamente ao `with(...)` da query do model para evitar N+1.

## Macros

Encapsule uma configuração repetida em uma chamada de factory reutilizável via `FieldFactory::macro`:

```php
// AppServiceProvider::boot
Field::macro('priceBRL', fn (string $name) =>
    Field::currency($name)
        ->prefix('R$ ')
        ->thousandsSeparator('.')
        ->decimalSeparator(',')
);

// uso
Field::priceBRL('price')->required(),
```

## Tipos de field customizados

Registre um novo tipo com `Field::register('rating', RatingField::class)` e depois gere os stubs PHP + React com `php artisan arqel:field Rating`. Veja [Custom Fields](/pt-BR/advanced/custom-fields) para o passo a passo completo.

## Anti-patterns

- `Field::text('email')` em vez de `Field::email('email')` — você perde a regra `email` automática e o tipo de input semântico.
- Presumir que a validação no cliente cobre tudo — regras como `confirmed`/`password`/`current_password` são exclusivas do servidor por design e nunca chegam ao schema Zod.
- Deixar de parear `->canSee()` com uma Policy em fields sensíveis — autorização no nível do field, sozinha, não é uma fronteira de segurança.

## Próximos passos

- [Table](/pt-BR/resources/table) — como fields viram columns de table
- [Form](/pt-BR/resources/form) — como fields são dispostos nos forms de create/edit
- API completa: [referência do `arqel-dev/fields`](/pt-BR/reference/php/fields)

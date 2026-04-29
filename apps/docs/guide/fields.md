# Fields

Um **Field** descreve uma coluna do model: tipo, label, validação, visibilidade, dependências. Arqel entrega 21 field types canónicos e 1:1 entre PHP (`packages/fields`) e React (`@arqel/fields`).

## O mínimo

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

## Catálogo

| Factory | Class | Component (React) | Use case |
|---|---|---|---|
| `Field::text(name)` | `TextField` | `TextInput` | Strings curtas |
| `Field::textarea(name)` | `TextareaField` | `TextareaInput` | Texto multi-linha |
| `Field::email(name)` | `EmailField` | `EmailInput` | Email com `email` rule |
| `Field::url(name)` | `UrlField` | `UrlInput` | URL com `url` rule |
| `Field::password(name)` | `PasswordField` | `PasswordInput` | Senha com toggle reveal |
| `Field::slug(name)` | `SlugField` | `SlugInput` | Slug normalizado |
| `Field::number(name)` | `NumberField` | `NumberInput` | Inteiros/decimais com stepper |
| `Field::currency(name)` | `CurrencyField` | `CurrencyInput` | Money com Intl format |
| `Field::boolean(name)` | `BooleanField` | `Checkbox` | True/false como checkbox |
| `Field::toggle(name)` | `ToggleField` | `Toggle` | True/false como switch |
| `Field::select(name)` | `SelectField` | `SelectInput` | Picker single-value |
| `Field::multiSelect(name)` | `MultiSelectField` | `MultiSelectInput` | Picker multi-value (chips) |
| `Field::radio(name)` | `RadioField` | `RadioGroup` | Picker single-value como radio |
| `Field::belongsTo(name, Resource)` | `BelongsToField` | `BelongsToInput` | Foreign key (combobox async) |
| `Field::hasMany(name, Resource)` | `HasManyField` | `HasManyReadonly` | Lista readonly (Phase 1) |
| `Field::date(name)` | `DateField` | `DateInput` | Data nativa |
| `Field::dateTime(name)` | `DateTimeField` | `DateTimeInput` | DateTime nativo |
| `Field::file(name)` | `FileField` | `FileInput` | Upload com drag-drop |
| `Field::image(name)` | `ImageField` | `ImageInput` | Upload com preview |
| `Field::color(name)` | `ColorField` | `ColorInput` | Color picker + presets |
| `Field::hidden(name)` | `HiddenField` | `HiddenInput` | `<input type=hidden>` |

## API fluente comum

Setters disponíveis em todos os Fields (via traits `HasValidation`, `HasVisibility`, `HasDependencies`, `HasAuthorization`):

```php
Field::text('title')
    ->label('Article title')
    ->placeholder('e.g. "Why Arqel beats Filament"')
    ->helperText('Aparece como `<title>` na página pública')
    ->required()
    ->maxLength(200)
    ->minLength(3)
    ->columnSpan(2)            // grid span
    ->columnSpanFull()         // span = colunas do form
    ->disabled()               // ou disabled(fn($record) => $record?->locked)
    ->readonly()
    ->dehydrated(false)        // não serializa para o save
    ->live()                   // re-render do form a cada keystroke
    ->liveDebounced(300)       // debounce em ms
    ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state)));
```

## Validação

Regras Laravel-native:

```php
Field::text('email')
    ->required()
    ->rule('email')
    ->unique(User::class, 'email')
    ->validationAttribute('endereço de email')
    ->validationMessage('Esse email já está cadastrado.');
```

Cada Field expõe `getValidationRules()` que é usado pelo `FormRequestGenerator` (`php artisan arqel:form-request`) para gerar `Store{Model}Request`/`Update{Model}Request`. No client, `ValidationBridge` traduz as rules para um schema Zod (`z.string().email().min(1).max(255).nullable()`) — útil para validação real-time se você quiser.

## Visibilidade

```php
Field::text('internal_note')
    ->hiddenOnTable()                   // não aparece no index
    ->hiddenOnDetail()                  // não aparece no show
    ->visibleOn(['edit'])               // apenas edit
    ->visibleIf(fn ($record) => $record?->is_admin);
```

4 contextos: `create`, `edit`, `detail`, `table`. `visibleIf` e `hiddenIf` são mutuamente exclusivos.

## Dependências

```php
Field::select('country')
    ->options(Country::pluck('name', 'id')->toArray()),

Field::select('state')
    ->dependsOn(['country'])
    ->resolveOptionsUsing(fn ($state) =>
        State::where('country_id', $state['country'] ?? null)->pluck('name', 'id')->toArray()
    ),
```

Arqel faz partial reload Inertia (`only: ['fields.state.options']`) com debounce de 300ms quando `country` muda — sem TanStack Query.

## Authorization

```php
Field::text('salary')
    ->canSee(fn ($user, $record) => $user?->hasRole('hr'))
    ->canEdit(fn ($user, $record) => $user?->id === $record?->user_id);
```

Field-level auth é *UX-only* — o servidor sempre re-valida via Policies. Veja [Auth](/guide/auth).

## Currency PT-BR

```php
Field::currency('price')
    ->prefix('R$ ')
    ->thousandsSeparator('.')
    ->decimalSeparator(',')
    ->decimals(2);
```

Exibe `R$ 1.234,56` no client; serializa como `1234.56` para o backend.

## Macros

Encurte configs repetidas via `FieldFactory::macro`:

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

## Custom Field

Veja [Custom Fields](/advanced/custom-fields) para criar um type novo (PHP class + React component + register).

## Anti-patterns

- ❌ **`Field::text('email')`** — use `Field::email('email')` para herdar a `email` rule automaticamente
- ❌ **Validation server-only ignorada no client** — regras como `confirmed`, `password`, `current_password` são saltadas pelo `ValidationBridge` (server-only por design)
- ❌ **Hardcode de cor em Field** — use CSS vars do `@arqel/ui`

## Próximos passos

- [Tables & Forms](/guide/tables-forms) — onde os fields aparecem
- [Custom Fields](/advanced/custom-fields) — criar inputs próprios
- API reference: [`packages/fields/SKILL.md`](https://github.com/arqel/arqel/blob/main/packages/fields/SKILL.md)

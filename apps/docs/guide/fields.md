# Fields

A **Field** describes a model column: type, label, validation, visibility, dependencies. Arqel ships 21 canonical field types with a 1:1 mapping between PHP (`packages/fields`) and React (`@arqel-dev/fields`).

## The minimum

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

## Catalog

| Factory | Class | Component (React) | Use case |
|---|---|---|---|
| `Field::text(name)` | `TextField` | `TextInput` | Short strings |
| `Field::textarea(name)` | `TextareaField` | `TextareaInput` | Multi-line text |
| `Field::email(name)` | `EmailField` | `EmailInput` | Email with `email` rule |
| `Field::url(name)` | `UrlField` | `UrlInput` | URL with `url` rule |
| `Field::password(name)` | `PasswordField` | `PasswordInput` | Password with reveal toggle |
| `Field::slug(name)` | `SlugField` | `SlugInput` | Normalized slug |
| `Field::number(name)` | `NumberField` | `NumberInput` | Integers/decimals with stepper |
| `Field::currency(name)` | `CurrencyField` | `CurrencyInput` | Money with Intl format |
| `Field::boolean(name)` | `BooleanField` | `Checkbox` | True/false as checkbox |
| `Field::toggle(name)` | `ToggleField` | `Toggle` | True/false as switch |
| `Field::select(name)` | `SelectField` | `SelectInput` | Single-value picker |
| `Field::multiSelect(name)` | `MultiSelectField` | `MultiSelectInput` | Multi-value picker (chips) |
| `Field::radio(name)` | `RadioField` | `RadioGroup` | Single-value picker as radio |
| `Field::belongsTo(name, Resource)` | `BelongsToField` | `BelongsToInput` | Foreign key (async combobox) |
| `Field::hasMany(name, Resource)` | `HasManyField` | `HasManyReadonly` | Readonly list (Phase 1) |
| `Field::date(name)` | `DateField` | `DateInput` | Native date |
| `Field::dateTime(name)` | `DateTimeField` | `DateTimeInput` | Native datetime |
| `Field::file(name)` | `FileField` | `FileInput` | Upload with drag-drop |
| `Field::image(name)` | `ImageField` | `ImageInput` | Upload with preview |
| `Field::color(name)` | `ColorField` | `ColorInput` | Color picker + presets |
| `Field::hidden(name)` | `HiddenField` | `HiddenInput` | `<input type=hidden>` |

## Common fluent API

Setters available on all Fields (via the `HasValidation`, `HasVisibility`, `HasDependencies`, `HasAuthorization` traits):

```php
Field::text('title')
    ->label('Article title')
    ->placeholder('e.g. "Why Arqel beats Filament"')
    ->helperText('Appears as `<title>` on the public page')
    ->required()
    ->maxLength(200)
    ->minLength(3)
    ->columnSpan(2)            // grid span
    ->columnSpanFull()         // span = form columns
    ->disabled()               // or disabled(fn($record) => $record?->locked)
    ->readonly()
    ->dehydrated(false)        // does not serialize on save
    ->live()                   // re-render the form on every keystroke
    ->liveDebounced(300)       // debounce in ms
    ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state)));
```

## Validation

Laravel-native rules:

```php
Field::text('email')
    ->required()
    ->rule('email')
    ->unique(User::class, 'email')
    ->validationAttribute('email address')
    ->validationMessage('That email is already registered.');
```

Each Field exposes `getValidationRules()`, used by the `FormRequestGenerator` (`php artisan arqel:form-request`) to generate `Store{Model}Request`/`Update{Model}Request`. On the client, `ValidationBridge` translates the rules into a Zod schema (`z.string().email().min(1).max(255).nullable()`) — useful for real-time validation if you want it.

## Visibility

```php
Field::text('internal_note')
    ->hiddenOnTable()                   // hidden on the index
    ->hiddenOnDetail()                  // hidden on the show
    ->visibleOn(['edit'])               // edit only
    ->visibleIf(fn ($record) => $record?->is_admin);
```

4 contexts: `create`, `edit`, `detail`, `table`. `visibleIf` and `hiddenIf` are mutually exclusive.

## Dependencies

```php
Field::select('country')
    ->options(Country::pluck('name', 'id')->toArray()),

Field::select('state')
    ->dependsOn(['country'])
    ->resolveOptionsUsing(fn ($state) =>
        State::where('country_id', $state['country'] ?? null)->pluck('name', 'id')->toArray()
    ),
```

Arqel performs an Inertia partial reload (`only: ['fields.state.options']`) with a 300ms debounce when `country` changes — without TanStack Query.

## Authorization

```php
Field::text('salary')
    ->canSee(fn ($user, $record) => $user?->hasRole('hr'))
    ->canEdit(fn ($user, $record) => $user?->id === $record?->user_id);
```

Field-level auth is *UX-only* — the server always re-validates via Policies. See [Auth](/guide/auth).

## Currency PT-BR

```php
Field::currency('price')
    ->prefix('R$ ')
    ->thousandsSeparator('.')
    ->decimalSeparator(',')
    ->decimals(2);
```

Displays `R$ 1.234,56` on the client; serializes as `1234.56` to the backend.

## Macros

Shorten repeated configurations via `FieldFactory::macro`:

```php
// AppServiceProvider::boot
Field::macro('priceBRL', fn (string $name) =>
    Field::currency($name)
        ->prefix('R$ ')
        ->thousandsSeparator('.')
        ->decimalSeparator(',')
);

// usage
Field::priceBRL('price')->required(),
```

## Custom Field

See [Custom Fields](/advanced/custom-fields) to create a new type (PHP class + React component + register).

## Anti-patterns

- `Field::text('email')` — use `Field::email('email')` to inherit the `email` rule automatically
- Server-only validation ignored on the client — rules like `confirmed`, `password`, `current_password` are skipped by `ValidationBridge` (server-only by design)
- Hardcoding colors in a Field — use the CSS vars from `@arqel-dev/ui`

## Next steps

- [Tables & Forms](/guide/tables-forms) — where the fields appear
- [Custom Fields](/advanced/custom-fields) — create your own inputs
- API reference: [`packages/fields/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/fields/SKILL.md)

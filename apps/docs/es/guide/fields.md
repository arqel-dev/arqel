# Fields

Un **Field** describe una columna del modelo: tipo, label, validación, visibilidad, dependencias. Arqel incluye 21 tipos de Field canónicos con un mapeo 1:1 entre PHP (`packages/fields`) y React (`@arqel-dev/fields`).

## Lo mínimo

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

| Factory | Clase | Componente (React) | Caso de uso |
|---|---|---|---|
| `Field::text(name)` | `TextField` | `TextInput` | Strings cortos |
| `Field::textarea(name)` | `TextareaField` | `TextareaInput` | Texto multi-línea |
| `Field::email(name)` | `EmailField` | `EmailInput` | Email con regla `email` |
| `Field::url(name)` | `UrlField` | `UrlInput` | URL con regla `url` |
| `Field::password(name)` | `PasswordField` | `PasswordInput` | Contraseña con toggle de revelar |
| `Field::slug(name)` | `SlugField` | `SlugInput` | Slug normalizado |
| `Field::number(name)` | `NumberField` | `NumberInput` | Enteros/decimales con stepper |
| `Field::currency(name)` | `CurrencyField` | `CurrencyInput` | Dinero con formato Intl |
| `Field::boolean(name)` | `BooleanField` | `Checkbox` | True/false como checkbox |
| `Field::toggle(name)` | `ToggleField` | `Toggle` | True/false como switch |
| `Field::select(name)` | `SelectField` | `SelectInput` | Picker de un solo valor |
| `Field::multiSelect(name)` | `MultiSelectField` | `MultiSelectInput` | Picker multi-valor (chips) |
| `Field::radio(name)` | `RadioField` | `RadioGroup` | Picker de un solo valor como radio |
| `Field::belongsTo(name, Resource)` | `BelongsToField` | `BelongsToInput` | Foreign key (combobox async) |
| `Field::hasMany(name, Resource)` | `HasManyField` | `HasManyReadonly` | Lista readonly (Fase 1) |
| `Field::date(name)` | `DateField` | `DateInput` | Fecha nativa |
| `Field::dateTime(name)` | `DateTimeField` | `DateTimeInput` | Datetime nativo |
| `Field::file(name)` | `FileField` | `FileInput` | Upload con drag-drop |
| `Field::image(name)` | `ImageField` | `ImageInput` | Upload con preview |
| `Field::color(name)` | `ColorField` | `ColorInput` | Color picker + presets |
| `Field::hidden(name)` | `HiddenField` | `HiddenInput` | `<input type=hidden>` |

## API fluida común

Setters disponibles en todos los Fields (vía los traits `HasValidation`, `HasVisibility`, `HasDependencies`, `HasAuthorization`):

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
    ->disabled()               // o disabled(fn($record) => $record?->locked)
    ->readonly()
    ->dehydrated(false)        // no serializa al guardar
    ->live()                   // re-renderiza el form en cada tecla
    ->liveDebounced(300)       // debounce en ms
    ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state)));
```

## Validación

Reglas nativas de Laravel:

```php
Field::text('email')
    ->required()
    ->rule('email')
    ->unique(User::class, 'email')
    ->validationAttribute('email address')
    ->validationMessage('That email is already registered.');
```

Cada Field expone `getValidationRules()`, usado por el `FormRequestGenerator` (`php artisan arqel:form-request`) para generar `Store{Model}Request`/`Update{Model}Request`. En el cliente, `ValidationBridge` traduce las reglas a un schema Zod (`z.string().email().min(1).max(255).nullable()`) — útil para validación en tiempo real si la quieres.

## Visibilidad

```php
Field::text('internal_note')
    ->hiddenOnTable()                   // oculto en el index
    ->hiddenOnDetail()                  // oculto en el show
    ->visibleOn(['edit'])               // solo edit
    ->visibleIf(fn ($record) => $record?->is_admin);
```

4 contextos: `create`, `edit`, `detail`, `table`. `visibleIf` y `hiddenIf` son mutuamente excluyentes.

## Dependencias

```php
Field::select('country')
    ->options(Country::pluck('name', 'id')->toArray()),

Field::select('state')
    ->dependsOn(['country'])
    ->resolveOptionsUsing(fn ($state) =>
        State::where('country_id', $state['country'] ?? null)->pluck('name', 'id')->toArray()
    ),
```

Arqel ejecuta un partial reload de Inertia (`only: ['fields.state.options']`) con un debounce de 300ms cuando `country` cambia — sin TanStack Query.

## Autorización

```php
Field::text('salary')
    ->canSee(fn ($user, $record) => $user?->hasRole('hr'))
    ->canEdit(fn ($user, $record) => $user?->id === $record?->user_id);
```

La auth a nivel de Field es *solo de UX* — el servidor siempre re-valida vía Policies. Ver [Auth](/es/guide/auth).

## Currency PT-BR

```php
Field::currency('price')
    ->prefix('R$ ')
    ->thousandsSeparator('.')
    ->decimalSeparator(',')
    ->decimals(2);
```

Muestra `R$ 1.234,56` en el cliente; serializa como `1234.56` al backend.

## Macros

Acorta configuraciones repetidas vía `FieldFactory::macro`:

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

## Field personalizado

Ver [Custom Fields](/es/advanced/custom-fields) para crear un nuevo tipo (clase PHP + componente React + registro).

## Anti-patrones

- `Field::text('email')` — usa `Field::email('email')` para heredar la regla `email` automáticamente
- Validación solo del servidor ignorada en el cliente — reglas como `confirmed`, `password`, `current_password` son saltadas por `ValidationBridge` (server-only por diseño)
- Hardcodear colores en un Field — usa las CSS vars de `@arqel-dev/ui`

## Próximos pasos

- [Tables & Forms](/es/guide/tables-forms) — dónde aparecen los fields
- [Custom Fields](/es/advanced/custom-fields) — crea tus propios inputs
- Referencia API: [`packages/fields/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/fields/SKILL.md)

# Fields

A **Field** describes one piece of data on a model: its input type, label, validation rules, visibility per context, dependencies on other fields, and who's allowed to see or edit it. Fields are the single source of truth Arqel uses to derive validation, the auto-generated form, and the auto-generated table columns from `Resource::fields()`.

Arqel ships 21 canonical field types with a 1:1 mapping between the PHP class (`arqel-dev/fields`) and its React input component (`@arqel-dev/fields`). Full prop-by-prop reference: [`arqel-dev/fields`](/reference/php/fields).

## The minimum

Per ADR-019, Fields use the factory-alias convention — import `FieldFactory` as `Field` and call it like a namespaced constructor:

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

Never instantiate a Field class directly (`new TextField(...)`) — `Field::__construct` is intentionally not the public entry point; always go through the factory.

## Catalog

| Factory call | PHP class | React component | Use case |
|---|---|---|---|
| `Field::text($name)` | `TextField` | `TextInput` | Short strings |
| `Field::textarea($name)` | `TextareaField` | `TextareaInput` | Multi-line text |
| `Field::email($name)` | `EmailField` | `EmailInput` | Email, with the `email` rule pre-applied |
| `Field::url($name)` | `UrlField` | `UrlInput` | URL, with the `url` rule pre-applied |
| `Field::password($name)` | `PasswordField` | `PasswordInput` | Password with reveal toggle |
| `Field::slug($name)` | `SlugField` | `SlugInput` | Normalized slug, optionally derived from another field |
| `Field::number($name)` | `NumberField` | `NumberInput` | Integers/decimals with stepper |
| `Field::currency($name)` | `CurrencyField` | `CurrencyInput` | Money with locale-aware formatting |
| `Field::boolean($name)` | `BooleanField` | `Checkbox` | True/false as a checkbox |
| `Field::toggle($name)` | `ToggleField` | `Toggle` | True/false as a switch |
| `Field::select($name)` | `SelectField` | `SelectInput` | Single-value picker |
| `Field::multiSelect($name)` | `MultiSelectField` | `MultiSelectInput` | Multi-value picker (chips) |
| `Field::radio($name)` | `RadioField` | `RadioGroup` | Single-value picker as radio buttons |
| `Field::belongsTo($name, $resource)` | `BelongsToField` | `BelongsToInput` | Foreign key, async searchable combobox |
| `Field::hasMany($name, $resource)` | `HasManyField` | `HasManyReadonly` | Read-only related list |
| `Field::date($name)` | `DateField` | `DateInput` | Native date |
| `Field::dateTime($name)` | `DateTimeField` | `DateTimeInput` | Native datetime |
| `Field::file($name)` | `FileField` | `FileInput` | Upload with drag-and-drop |
| `Field::image($name)` | `ImageField` | `ImageInput` | Upload with preview + crop |
| `Field::color($name)` | `ColorField` | `ColorInput` | Color picker with presets |
| `Field::hidden($name)` | `HiddenField` | `HiddenInput` | `<input type="hidden">` |

## Common fluent API

Every Field shares a base setter surface, plus setters contributed by four traits (`HasValidation`, `HasVisibility`, `HasDependencies`, `HasAuthorization`):

```php
Field::text('title')
    ->label('Article title')
    ->placeholder('e.g. "Why Arqel beats Filament"')
    ->helperText('Appears as <title> on the public page')
    ->required()
    ->maxLength(200)
    ->minLength(3)
    ->columnSpan(2)             // grid span in the form layout
    ->columnSpanFull()          // span = total form columns
    ->disabled()                // or disabled(fn ($record) => $record?->locked)
    ->readonly()
    ->dehydrated(false)         // excluded from the save payload
    ->live()                    // re-render the form on every keystroke
    ->liveDebounced(300)        // same, debounced in ms
    ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state)));
```

## Validation

Rules follow Laravel conventions and feed both server-side validation and the client-side Zod bridge:

```php
Field::text('email')
    ->required()
    ->rule('email')
    ->unique(User::class, 'email')
    ->validationAttribute('email address')
    ->validationMessage('That email is already registered.');
```

Each Field exposes `getValidationRules()`. The `FormRequestGenerator` (invoked via `php artisan arqel:resource {Model} --with-form-requests`) collects these across all fields using `FieldRulesExtractor` to build `Store{Model}Request`/`Update{Model}Request`. On the client, `ValidationBridge` translates the same rules into a Zod schema for optional real-time validation — note that server-only rules like `confirmed`, `password`, and `current_password` are intentionally skipped by the bridge.

## Visibility

Fields can be shown or hidden per rendering context:

```php
Field::text('internal_note')
    ->hiddenOnTable()                   // never shown on the index
    ->hiddenOnDetail()                  // never shown on the show page
    ->visibleOn(['edit'])               // edit only
    ->visibleIf(fn ($record) => $record?->is_admin);
```

The four contexts are `create`, `edit`, `detail`, `table`. `visibleIf` and `hiddenIf` are mutually exclusive — set one or the other, not both.

## Dependencies between fields

```php
Field::select('country')
    ->options(Country::pluck('name', 'id')->toArray()),

Field::select('state')
    ->dependsOn(['country'])
    ->resolveOptionsUsing(fn ($state) =>
        State::where('country_id', $state['country'] ?? null)->pluck('name', 'id')->toArray()
    ),
```

When `country` changes, Arqel triggers a debounced (300ms) Inertia partial reload scoped to `fields.state.options` — no TanStack Query or client-side fetch library involved (ADR-016).

## Authorization

Field-level visibility/editability is UX sugar, not the security boundary:

```php
Field::text('salary')
    ->canSee(fn ($user, $record) => $user?->hasRole('hr'))
    ->canEdit(fn ($user, $record) => $user?->id === $record?->user_id);
```

The server always re-validates through Policies regardless of what `canSee`/`canEdit` decided on the client.

## Relations: `belongsTo` and `hasMany`

```php
Field::belongsTo('role_id', RoleResource::class)
    ->searchable()
    ->preload();
```

The relation name is derived by stripping the `_id` suffix from the field name (`role_id` → `role`). `HasManyField` renders a read-only list in Phase 1; `EagerLoadingResolver` automatically adds both to the model query's `with(...)` to avoid N+1s.

## Macros

Wrap a repeated configuration into a reusable factory call via `FieldFactory::macro`:

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

## Custom field types

Register a new type with `Field::register('rating', RatingField::class)`, then generate the PHP + React stubs with `php artisan arqel:field Rating`. See [Custom Fields](/advanced/custom-fields) for the full walkthrough.

## Anti-patterns

- `Field::text('email')` instead of `Field::email('email')` — you lose the automatic `email` rule and the semantic input type.
- Assuming client-side validation covers everything — rules like `confirmed`/`password`/`current_password` are server-only by design and never reach the Zod schema.
- Skipping `->canSee()`/Policy pairing on sensitive fields — field-level auth alone is not a security boundary.

## Next steps

- [Table](/resources/table) — how fields become table columns
- [Form](/resources/form) — how fields are laid out in create/edit forms
- Full API: [`arqel-dev/fields` reference](/reference/php/fields)

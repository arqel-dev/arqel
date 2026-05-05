# `arqel-dev/fields` — API Reference

Namespace `Arqel\Fields\`. 21 canonical field types + ValidationBridge + macro registry.

## `Arqel\Fields\Field` (abstract)

Base of every Field. `__construct` is `final` by design — subclasses only declare `protected string $type` and `protected string $component`.

### Fluent setters (via traits)

| Trait | Setters |
|---|---|
| **base** | `label`, `placeholder`, `helperText`, `default`, `readonly`, `disabled`, `columnSpan`, `columnSpanFull`, `dehydrated`, `live`, `liveDebounced`, `afterStateUpdated` |
| `HasValidation` | `required`, `nullable`, `rules`, `rule`, `unique`, `maxLength`, `minLength`, `requiredIf`, `requiredWith`, `requiredWithout`, `validationAttribute`, `validationMessage` |
| `HasVisibility` | `hidden`, `hiddenOnCreate`, `hiddenOnEdit`, `hiddenOnDetail`, `hiddenOnTable`, `visibleOn(string\|array)`, `hiddenOn(string\|array)`, `visibleIf(Closure)`, `hiddenIf(Closure)` |
| `HasDependencies` | `dependsOn(string\|array)`, `resolveOptionsUsing(Closure)` |
| `HasAuthorization` | `canSee(Closure)`, `canEdit(Closure)` |

### Oracles

```php
isVisibleIn(string $context, ?Model $record = null): bool   // create|edit|detail|table
canBeSeenBy(?Authenticatable $user, ?Model $record = null): bool
canBeEditedBy(?Authenticatable $user, ?Model $record = null): bool
isDisabled(?Model $record = null): bool
isDehydrated(?Model $record = null): bool
getValidationRules(): array
handleDependencyUpdate(array $formState, string $changedField): array
```

## `Arqel\Fields\FieldFactory` (final)

Public factory. **Don't** call `new TextField('name')` — use `FieldFactory::text('name')` (common alias: `use Arqel\Fields\FieldFactory as Field;`).

| Method | Returns |
|---|---|
| `Field::text/textarea/email/url/password/slug(name)` | `TextField/TextareaField/...` |
| `Field::number/currency(name)` | `NumberField/CurrencyField` |
| `Field::boolean/toggle(name)` | `BooleanField/ToggleField` |
| `Field::select/multiSelect/radio(name)` | `SelectField/MultiSelectField/RadioField` |
| `Field::belongsTo(name, Resource)` | `BelongsToField` |
| `Field::hasMany(name, Resource)` | `HasManyField` |
| `Field::date/dateTime(name)` | `DateField/DateTimeField` |
| `Field::file/image(name)` | `FileField/ImageField` |
| `Field::color/hidden(name)` | `ColorField/HiddenField` |
| `Field::macro(name, Closure)` / `Field::hasMacro(name)` | macro registry |
| `Field::register(type, class)` | custom-type registry |

## Field Types — specific props

| Class | Extra setters |
|---|---|
| `TextField` | `maxLength`, `minLength`, `pattern`, `autocomplete`, `mask` |
| `TextareaField` | `rows`, `cols` |
| `EmailField` | (default rule `email`) |
| `UrlField` | (default rule `url`) |
| `PasswordField` | `revealable` |
| `SlugField` | `fromField`, `separator`, `reservedSlugs`, `uniqueIn` |
| `NumberField` | `min`, `max`, `step`, `integer`, `decimals` |
| `CurrencyField` | `prefix`, `suffix`, `thousandsSeparator`, `decimalSeparator`, `decimals` |
| `BooleanField` | `inline` |
| `ToggleField` | `onColor`, `offColor`, `onIcon`, `offIcon` |
| `SelectField` | `options(array\|Closure)`, `optionsRelationship`, `searchable`, `multiple`, `native`, `creatable`, `createOptionUsing`, `allowCustomValues` |
| `BelongsToField` | factory `make($name, $relatedResource)`, `searchable`, `preload`, `searchColumns`, `optionLabel(Closure)`, `relationship(name, ?query)` |
| `HasManyField` | factory `make($name, $relatedResource)`, `canAddRecords`, `canEditRecords` |
| `DateField` | `format`, `displayFormat`, `minDate`, `maxDate`, `closeOnDateSelection`, `timezone` |
| `DateTimeField` | adds `seconds(bool)` |
| `FileField` | `disk`, `directory`, `visibility`, `maxSize`, `acceptedFileTypes`, `multiple`, `reorderable`, `using(STRATEGY_*)` |
| `ImageField` | adds `imageCropAspectRatio`, `imageResizeTargetWidth` |
| `ColorField` | `presets`, `format('hex'\|'rgb'\|'hsl')`, `alpha` |
| `HiddenField` | (no extra setters) |

## `Arqel\Fields\ValidationBridge` (final)

Translates Laravel rule arrays to a Zod schema. 19 built-in translators.

| Method | Function |
|---|---|
| `translate(array $rules): string` | Zod schema (e.g.: `z.string().email().min(1).max(255).nullable()`) |
| `register(string $rule, Closure)` | Register a custom translator |
| `hasRule(string)`, `flush()` | Utilities |

Unknown rules are silently skipped (`confirmed`, `password`, `current_password` are server-only).

## `Arqel\Fields\EagerLoadingResolver` (final)

`resolve(array<Field>): array<int, string>` extracts relation names from `BelongsToField` and `HasManyField` for `Builder::with(...)`. Auto dedupe.

## HTTP Controllers

| Controller | Endpoint | Function |
|---|---|---|
| `FieldSearchController` | `GET {panel}/{resource}/fields/{field}/search?q=` | Async search for `BelongsToField` (max 20 results, throttle 30/min) |
| `FieldUploadController` | `POST/DELETE {panel}/{resource}/fields/{field}/upload` | Upload/delete for `FileField` |

## Macros & Custom

```php
// AppServiceProvider::boot
Field::macro('priceBRL', fn (string $name) =>
    Field::currency($name)->prefix('R$ ')->thousandsSeparator('.')->decimalSeparator(',')
);

// Custom type
Field::register('rating', RatingField::class);
```

## Artisan commands

| Command | Function |
|---|---|
| `arqel:field {name} {--force}` | Generates `app/Arqel/Fields/{Name}Field.php` + `resources/js/Arqel/Fields/{Name}Input.tsx` stubs |

## Related

- SKILL: [`packages/fields/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/fields/SKILL.md)
- Catalog: [`/guide/fields`](/guide/fields)
- Next: [`arqel-dev/table`](/reference/php/table)

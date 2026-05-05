# `arqel-dev/fields` — Referencia de API

Namespace `Arqel\Fields\`. 21 tipos canónicos de Field + ValidationBridge + registry de macros.

## `Arqel\Fields\Field` (abstract)

Base de cada Field. `__construct` es `final` por diseño — las subclases solo declaran `protected string $type` y `protected string $component`.

### Setters fluidos (vía traits)

| Trait | Setters |
|---|---|
| **base** | `label`, `placeholder`, `helperText`, `default`, `readonly`, `disabled`, `columnSpan`, `columnSpanFull`, `dehydrated`, `live`, `liveDebounced`, `afterStateUpdated` |
| `HasValidation` | `required`, `nullable`, `rules`, `rule`, `unique`, `maxLength`, `minLength`, `requiredIf`, `requiredWith`, `requiredWithout`, `validationAttribute`, `validationMessage` |
| `HasVisibility` | `hidden`, `hiddenOnCreate`, `hiddenOnEdit`, `hiddenOnDetail`, `hiddenOnTable`, `visibleOn(string\|array)`, `hiddenOn(string\|array)`, `visibleIf(Closure)`, `hiddenIf(Closure)` |
| `HasDependencies` | `dependsOn(string\|array)`, `resolveOptionsUsing(Closure)` |
| `HasAuthorization` | `canSee(Closure)`, `canEdit(Closure)` |

### Oráculos

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

Factory pública. **No** llames `new TextField('name')` — usa `FieldFactory::text('name')` (alias común: `use Arqel\Fields\FieldFactory as Field;`).

| Método | Retorna |
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
| `Field::macro(name, Closure)` / `Field::hasMacro(name)` | registry de macros |
| `Field::register(type, class)` | registry de tipos personalizados |

## Tipos de Field — props específicas

| Clase | Setters extra |
|---|---|
| `TextField` | `maxLength`, `minLength`, `pattern`, `autocomplete`, `mask` |
| `TextareaField` | `rows`, `cols` |
| `EmailField` | (regla por defecto `email`) |
| `UrlField` | (regla por defecto `url`) |
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
| `DateTimeField` | añade `seconds(bool)` |
| `FileField` | `disk`, `directory`, `visibility`, `maxSize`, `acceptedFileTypes`, `multiple`, `reorderable`, `using(STRATEGY_*)` |
| `ImageField` | añade `imageCropAspectRatio`, `imageResizeTargetWidth` |
| `ColorField` | `presets`, `format('hex'\|'rgb'\|'hsl')`, `alpha` |
| `HiddenField` | (sin setters extra) |

## `Arqel\Fields\ValidationBridge` (final)

Traduce arrays de reglas Laravel a un schema Zod. 19 traductores incorporados.

| Método | Función |
|---|---|
| `translate(array $rules): string` | Schema Zod (e.g.: `z.string().email().min(1).max(255).nullable()`) |
| `register(string $rule, Closure)` | Registra un traductor personalizado |
| `hasRule(string)`, `flush()` | Utilidades |

Las reglas desconocidas se omiten en silencio (`confirmed`, `password`, `current_password` son solo del servidor).

## `Arqel\Fields\EagerLoadingResolver` (final)

`resolve(array<Field>): array<int, string>` extrae nombres de relaciones de `BelongsToField` y `HasManyField` para `Builder::with(...)`. Auto dedupe.

## HTTP Controllers

| Controller | Endpoint | Función |
|---|---|---|
| `FieldSearchController` | `GET {panel}/{resource}/fields/{field}/search?q=` | Búsqueda asíncrona para `BelongsToField` (máx 20 resultados, throttle 30/min) |
| `FieldUploadController` | `POST/DELETE {panel}/{resource}/fields/{field}/upload` | Upload/delete para `FileField` |

## Macros & Custom

```php
// AppServiceProvider::boot
Field::macro('priceBRL', fn (string $name) =>
    Field::currency($name)->prefix('R$ ')->thousandsSeparator('.')->decimalSeparator(',')
);

// Custom type
Field::register('rating', RatingField::class);
```

## Comandos Artisan

| Comando | Función |
|---|---|
| `arqel:field {name} {--force}` | Genera stubs `app/Arqel/Fields/{Name}Field.php` + `resources/js/Arqel/Fields/{Name}Input.tsx` |

## Relacionado

- SKILL: [`packages/fields/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/fields/SKILL.md)
- Catálogo: [`/es/guide/fields`](/es/guide/fields)
- Siguiente: [`arqel-dev/table`](/es/reference/php/table)

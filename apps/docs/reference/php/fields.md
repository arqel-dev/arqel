# `arqel-dev/fields` — API Reference

Namespace `Arqel\Fields\`. 21 field types canónicos + ValidationBridge + macro registry.

## `Arqel\Fields\Field` (abstract)

Base de todos os Fields. `__construct` é `final` por design — subclasses só declaram `protected string $type` e `protected string $component`.

### Setters fluentes (via traits)

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

Factory pública. **Não** chame `new TextField('name')` — use `FieldFactory::text('name')` (alias comum: `use Arqel\Fields\FieldFactory as Field;`).

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
| `Field::register(type, class)` | registry de types custom |

## Field Types — props específicas

| Class | Setters extra |
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
| `DateTimeField` | adiciona `seconds(bool)` |
| `FileField` | `disk`, `directory`, `visibility`, `maxSize`, `acceptedFileTypes`, `multiple`, `reorderable`, `using(STRATEGY_*)` |
| `ImageField` | adiciona `imageCropAspectRatio`, `imageResizeTargetWidth` |
| `ColorField` | `presets`, `format('hex'\|'rgb'\|'hsl')`, `alpha` |
| `HiddenField` | (sem setters extra) |

## `Arqel\Fields\ValidationBridge` (final)

Traduz arrays de regras Laravel para schema Zod. 19 translators built-in.

| Método | Função |
|---|---|
| `translate(array $rules): string` | Schema Zod (ex: `z.string().email().min(1).max(255).nullable()`) |
| `register(string $rule, Closure)` | Registrar translator custom |
| `hasRule(string)`, `flush()` | Utilitários |

Regras desconhecidas são saltadas silenciosamente (`confirmed`, `password`, `current_password` são server-only).

## `Arqel\Fields\EagerLoadingResolver` (final)

`resolve(array<Field>): array<int, string>` extrai relation names de `BelongsToField` e `HasManyField` para `Builder::with(...)`. Dedupe automático.

## HTTP Controllers

| Controller | Endpoint | Função |
|---|---|---|
| `FieldSearchController` | `GET {panel}/{resource}/fields/{field}/search?q=` | Async search para `BelongsToField` (max 20 results, throttle 30/min) |
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

| Comando | Função |
|---|---|
| `arqel:field {name} {--force}` | Gera `app/Arqel/Fields/{Name}Field.php` + `resources/js/Arqel/Fields/{Name}Input.tsx` stubs |

## Related

- SKILL: [`packages/fields/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/fields/SKILL.md)
- Catálogo: [`/guide/fields`](/guide/fields)
- Próximo: [`arqel-dev/table`](/reference/php/table)

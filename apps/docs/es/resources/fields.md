# Fields

Un **Field** describe una pieza de datos de un modelo: su tipo de input, su etiqueta, sus reglas de validación, su visibilidad por contexto, sus dependencias con otros fields y quién tiene permiso para verlo o editarlo. Los Fields son la única fuente de verdad que Arqel usa para derivar la validación, el formulario autogenerado y las columnas autogeneradas de la tabla a partir de `Resource::fields()`.

Arqel incluye 21 tipos canónicos de field con un mapeo 1:1 entre la clase PHP (`arqel-dev/fields`) y su componente React de input (`@arqel-dev/fields`). Referencia completa prop por prop: [`arqel-dev/fields`](/es/reference/php/fields).

## Lo mínimo

Según el ADR-019, los Fields usan la convención de alias de factory — importa `FieldFactory` como `Field` y llámalo como si fuera un constructor con namespace:

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

Nunca instancies una clase Field directamente (`new TextField(...)`) — `Field::__construct` intencionalmente no es el punto de entrada público; pasa siempre por la factory.

## Catálogo

| Llamada a la factory | Clase PHP | Componente React | Caso de uso |
|---|---|---|---|
| `Field::text($name)` | `TextField` | `TextInput` | Cadenas cortas |
| `Field::textarea($name)` | `TextareaField` | `TextareaInput` | Texto multilínea |
| `Field::email($name)` | `EmailField` | `EmailInput` | Email, con la regla `email` preaplicada |
| `Field::url($name)` | `UrlField` | `UrlInput` | URL, con la regla `url` preaplicada |
| `Field::password($name)` | `PasswordField` | `PasswordInput` | Contraseña con botón para revelarla |
| `Field::slug($name)` | `SlugField` | `SlugInput` | Slug normalizado, opcionalmente derivado de otro field |
| `Field::number($name)` | `NumberField` | `NumberInput` | Enteros/decimales con stepper |
| `Field::currency($name)` | `CurrencyField` | `CurrencyInput` | Dinero con formato según el locale |
| `Field::boolean($name)` | `BooleanField` | `Checkbox` | Verdadero/falso como checkbox |
| `Field::toggle($name)` | `ToggleField` | `Toggle` | Verdadero/falso como interruptor |
| `Field::select($name)` | `SelectField` | `SelectInput` | Selector de un solo valor |
| `Field::multiSelect($name)` | `MultiSelectField` | `MultiSelectInput` | Selector de varios valores (chips) |
| `Field::radio($name)` | `RadioField` | `RadioGroup` | Selector de un solo valor como botones de radio |
| `Field::belongsTo($name, $resource)` | `BelongsToField` | `BelongsToInput` | Clave foránea, combobox buscable asíncrono |
| `Field::hasMany($name, $resource)` | `HasManyField` | `HasManyReadonly` | Lista relacionada de solo lectura |
| `Field::date($name)` | `DateField` | `DateInput` | Fecha nativa |
| `Field::dateTime($name)` | `DateTimeField` | `DateTimeInput` | Fecha y hora nativas |
| `Field::file($name)` | `FileField` | `FileInput` | Subida con arrastrar y soltar |
| `Field::image($name)` | `ImageField` | `ImageInput` | Subida con vista previa y recorte |
| `Field::color($name)` | `ColorField` | `ColorInput` | Selector de color con presets |
| `Field::hidden($name)` | `HiddenField` | `HiddenInput` | `<input type="hidden">` |

## API fluida común

Todo Field comparte una superficie base de setters, más los setters aportados por cuatro traits (`HasValidation`, `HasVisibility`, `HasDependencies`, `HasAuthorization`):

```php
Field::text('title')
    ->label('Article title')
    ->placeholder('e.g. "Why Arqel beats Filament"')
    ->helperText('Appears as <title> on the public page')
    ->required()
    ->maxLength(200)
    ->minLength(3)
    ->columnSpan(2)             // ancho en la grilla del layout del formulario
    ->columnSpanFull()          // ancho = total de columnas del formulario
    ->disabled()                // o disabled(fn ($record) => $record?->locked)
    ->readonly()
    ->dehydrated(false)         // excluido del payload de guardado
    ->live()                    // re-renderiza el formulario en cada pulsación de tecla
    ->liveDebounced(300)        // igual, con debounce en ms
    ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state)));
```

## Validación

Las reglas siguen las convenciones de Laravel y alimentan tanto la validación del lado del servidor como el puente Zod del lado del cliente:

```php
Field::text('email')
    ->required()
    ->rule('email')
    ->unique(User::class, 'email')
    ->validationAttribute('email address')
    ->validationMessage('That email is already registered.');
```

Cada Field expone `getValidationRules()`. El `FormRequestGenerator` (invocado vía `php artisan arqel:resource {Model} --with-form-requests`) las recopila de todos los fields usando `FieldRulesExtractor` para construir `Store{Model}Request`/`Update{Model}Request`. En el cliente, `ValidationBridge` traduce esas mismas reglas a un esquema Zod para una validación opcional en tiempo real — ten en cuenta que las reglas exclusivas del servidor como `confirmed`, `password` y `current_password` son omitidas intencionalmente por el puente.

## Visibilidad

Los Fields pueden mostrarse u ocultarse según el contexto de renderizado:

```php
Field::text('internal_note')
    ->hiddenOnTable()                   // nunca se muestra en el index
    ->hiddenOnDetail()                  // nunca se muestra en la página de detalle
    ->visibleOn(['edit'])               // solo en edición
    ->visibleIf(fn ($record) => $record?->is_admin);
```

Los cuatro contextos son `create`, `edit`, `detail` y `table`. `visibleIf` y `hiddenIf` son mutuamente excluyentes — usa uno u otro, no ambos.

## Dependencias entre fields

```php
Field::select('country')
    ->options(Country::pluck('name', 'id')->toArray()),

Field::select('state')
    ->dependsOn(['country'])
    ->resolveOptionsUsing(fn ($state) =>
        State::where('country_id', $state['country'] ?? null)->pluck('name', 'id')->toArray()
    ),
```

Cuando `country` cambia, Arqel dispara una recarga parcial de Inertia con debounce (300 ms) acotada a `fields.state.options` — sin TanStack Query ni ninguna librería de fetch del lado del cliente (ADR-016).

## Autorización

La visibilidad y editabilidad a nivel de field son azúcar de UX, no la frontera de seguridad:

```php
Field::text('salary')
    ->canSee(fn ($user, $record) => $user?->hasRole('hr'))
    ->canEdit(fn ($user, $record) => $user?->id === $record?->user_id);
```

El servidor siempre revalida a través de Policies, sin importar lo que `canSee`/`canEdit` hayan decidido en el cliente.

## Relaciones: `belongsTo` y `hasMany`

```php
Field::belongsTo('role_id', RoleResource::class)
    ->searchable()
    ->preload();
```

El nombre de la relación se deriva quitando el sufijo `_id` del nombre del field (`role_id` → `role`). `HasManyField` renderiza una lista de solo lectura en la Fase 1; `EagerLoadingResolver` añade ambos automáticamente al `with(...)` de la consulta del modelo para evitar problemas N+1.

## Macros

Envuelve una configuración repetida en una llamada de factory reutilizable mediante `FieldFactory::macro`:

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

## Tipos de field personalizados

Registra un nuevo tipo con `Field::register('rating', RatingField::class)` y luego genera los stubs de PHP y React con `php artisan arqel:field Rating`. Consulta [Fields personalizados](/es/advanced/custom-fields) para el recorrido completo.

## Antipatrones

- `Field::text('email')` en lugar de `Field::email('email')` — pierdes la regla `email` automática y el tipo de input semántico.
- Asumir que la validación del lado del cliente lo cubre todo — reglas como `confirmed`/`password`/`current_password` son exclusivas del servidor por diseño y nunca llegan al esquema Zod.
- Omitir el emparejamiento `->canSee()`/Policy en fields sensibles — la autorización a nivel de field por sí sola no es una frontera de seguridad.

## Próximos pasos

- [Table](/es/resources/table) — cómo los fields se convierten en columnas de la tabla
- [Form](/es/resources/form) — cómo se disponen los fields en los formularios de create/edit
- API completa: [referencia de `arqel-dev/fields`](/es/reference/php/fields)

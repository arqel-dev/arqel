# SKILL.md — arqel-dev/fields

> Contexto canónico para AI agents (Claude Code, Cursor via MCP, etc.) a trabalhar no pacote `arqel-dev/fields`. Estrutura conforme `PLANNING/04-repo-structure.md` §11.

## Purpose

`arqel-dev/fields` define a abstracção declarativa de campos. Um `Field` descreve **um e apenas um** atributo do model — como aparece num formulário, numa tabela, numa página de detalhe — e leva consigo:

- **Tipo + componente React** (`type` → `component` mapping)
- **Label, placeholder, helper text** (i18n-ready via `getLabel()` server-side)
- **Validação Laravel server-side** + schema **Zod** inferido client-side via `ValidationBridge`
- **Visibilidade contextual** e dependências reactivas (FIELDS-016/017, em construção)
- **Default values** e flags de `dehydrated`/`live`

## Status (2026-04)

**Entregue:**

- `Arqel\Fields\Field` abstract base com construtor `final` e fluent API completa (FIELDS-002)
- `Arqel\Fields\FieldFactory` registry + macros + `__callStatic` (FIELDS-003)
- 21 tipos concretos em `src/Types/` (ver tabela abaixo)
- `Arqel\Fields\ValidationBridge` Laravel rules → Zod schema string (FIELDS-012)
- 21 snapshot tests do shape JSON canónico em `tests/Snapshots/` (FIELDS-013)

**Ainda por chegar:**

- `Arqel\Fields\Concerns\HasValidation`/`HasVisibility`/`HasDependencies`/`HasAuthorization` (FIELDS-015..018)
- Eager loading automático (FIELDS-019)
- Endpoints search/upload/createOption (FIELDS-020/021, dependem de CORE-006)
- Macros + field registry runtime polish (FIELDS-022)

## Field types

Todos registados no `FieldServiceProvider::packageBooted()` para que `FieldFactory::{type}(...)` funcione via `__callStatic`.

| Type | Class | Component | Notes |
|---|---|---|---|
| `text` | `TextField` | `TextInput` | Base extensível: `maxLength`/`minLength`/`pattern`/`autocomplete`/`mask` |
| `textarea` | `TextareaField` | `TextareaInput` | Herda Text + `rows`/`cols` |
| `email` | `EmailField` | `EmailInput` | Default rule `email` |
| `url` | `UrlField` | `UrlInput` | Default rule `url` |
| `password` | `PasswordField` | `PasswordInput` | `revealable()` toggle |
| `slug` | `SlugField` | `SlugInput` | `fromField`/`separator`/`reservedSlugs`/`unique` |
| `number` | `NumberField` | `NumberInput` | `min`/`max`/`step`/`integer`/`decimals` |
| `currency` | `CurrencyField` | `CurrencyInput` | `prefix`/`suffix`/`thousandsSeparator`/`decimalSeparator` |
| `boolean` | `BooleanField` | `Checkbox` | `inline()` para layout horizontal |
| `toggle` | `ToggleField` | `Toggle` | `onColor`/`offColor`/`onIcon`/`offIcon` |
| `select` | `SelectField` | `SelectInput` | Static array, Closure, ou `optionsRelationship` |
| `multiSelect` | `MultiSelectField` | `MultiSelectInput` | `multiple=true`, `native=false` defaults |
| `radio` | `RadioField` | `RadioInput` | `native=false` default |
| `belongsTo` | `BelongsToField` | `BelongsToInput` | Factory `make($name, $resource)`. `searchable`/`preload`/`searchColumns` |
| `hasMany` | `HasManyField` | `HasManyTable` | Readonly Phase 1; `canAdd`/`canEdit` forward-compat |
| `date` | `DateField` | `DateInput` | `minDate`/`maxDate` (`string|Closure`), `format`/`displayFormat`/`timezone` |
| `dateTime` | `DateTimeField` | `DateTimeInput` | Herda Date + `seconds(bool)` |
| `file` | `FileField` | `FileInput` | `disk`/`directory`/`visibility`/`maxSize`/`acceptedFileTypes`/`multiple`/`reorderable`/`using(strategy)` |
| `image` | `ImageField` | `ImageInput` | Herda File + mime gate default + `imageCropAspectRatio`/`imageResizeTargetWidth` |
| `color` | `ColorField` | `ColorInput` | `presets`/`format(hex|rgb|hsl)`/`alpha` |
| `hidden` | `HiddenField` | `HiddenInput` | Para passing IDs sem UI |

## Examples

### Resource típico

```php
namespace App\Arqel\Resources;

use Arqel\Core\Resources\Resource;
use Arqel\Fields\FieldFactory as Field;
use Arqel\Fields\Types\BelongsToField;
use Arqel\Fields\Types\HasManyField;

final class UserResource extends Resource
{
    public static string $model = \App\Models\User::class;

    public function fields(): array
    {
        return [
            Field::text('name')->maxLength(255),
            Field::email('email'),
            Field::password('password')->revealable(),
            Field::date('birthday')->maxDate('today'),
            Field::toggle('is_active')->default(true),
            BelongsToField::make('team_id', TeamResource::class)
                ->searchable()
                ->preload(),
            HasManyField::make('posts', PostResource::class),
        ];
    }
}
```

### Currency PT-BR

```php
Field::currency('price')
    ->prefix('R$')
    ->thousandsSeparator('.')
    ->decimalSeparator(',')
    ->decimals(2);
```

### Custom select com closure

```php
Field::select('category_id')
    ->options(fn () => \App\Models\Category::pluck('name', 'id')->all())
    ->searchable();
```

## Creating custom fields

1. Criar `src/Types/MyField.php` extendendo `Arqel\Fields\Field` (NÃO override do `__construct` — é `final` por design)
2. Declarar `protected string $type = 'myType'; protected string $component = 'MyInput';`
3. Adicionar setters fluent que retornam `static`
4. Override `getTypeSpecificProps(): array` se houver props específicas
5. Override `getDefaultRules(): array` se houver regras Laravel implícitas
6. Registar via `FieldFactory::register('myType', MyField::class)` no ServiceProvider da app
7. Snapshot test em `tests/Snapshots/myType.json` para garantir shape

## Macros

```php
// Em ServiceProvider::boot()
FieldFactory::macro('priceBRL', fn (string $name) =>
    \Arqel\Fields\Types\CurrencyField::class
        ::make($name)
        ->prefix('R$')
        ->thousandsSeparator('.')
        ->decimalSeparator(',')
);

// Uso
Field::priceBRL('price'); // Returns CurrencyField pré-configurado
```

## ValidationBridge

```php
use Arqel\Fields\ValidationBridge;

ValidationBridge::translate(['required', 'email', 'max:255']);
// → 'z.string().min(1).email().max(255)'

ValidationBridge::translate(['in:draft,published,archived']);
// → 'z.enum(["draft", "published", "archived"])'

ValidationBridge::register('shouty', function (?string $arg, \Arqel\Fields\Translation $t): void {
    $t->ensureType('z.string()');
    $t->addChain('.transform((v) => v.toUpperCase())');
});
```

## Conventions

- `declare(strict_types=1)` obrigatório
- Subclasses concretas são `final` por convenção; bases (Text, Number, Date, File, Boolean, Select) são extensíveis intencionalmente
- Closures em props (`disabled`, `dehydrated`, `minDate`, `maxDate`) são avaliadas em `getTypeSpecificProps()` — non-string returns (em `minDate`/`maxDate`) são descartados graciosamente
- **Sem dependência inversa para `arqel-dev/core`**: core não depende de fields. Fields depende de core (precisa de `HasResource` para BelongsTo/HasMany)
- Snapshot tests obrigatórios para cada tipo novo

## Anti-patterns

- ❌ **Override de `Field::__construct`** — é `final` por design. Use static factory `make()` ou setters
- ❌ **Mutar field state directamente** — usar setters fluent que retornam `static`
- ❌ **Skip de validação client-side** — `ValidationBridge` é o espelho server→client, não corte sem motivo (UX inferior)
- ❌ **`FieldFactory::register()` em código não-ServiceProvider** — o registry é shared state global; registo deve ser idempotente e early-boot
- ❌ **Stringly-typed types** — sempre `Field::text(...)` ou subclasse, nunca `'text'` em arrays
- ❌ **Acoplamento ao `ResourceRegistry`** — Fields conhecem-se a si próprios e ao record que recebem; o registry é responsabilidade do core

## Related

- Source: [`packages/fields/src/`](./src/)
- Testes: [`packages/fields/tests/`](./tests/)
- Snapshots: [`packages/fields/tests/Snapshots/`](./tests/Snapshots/)
- APIs detalhadas: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §3
- Schema TS equivalente: [`PLANNING/06-api-react.md`](../../PLANNING/06-api-react.md) §4
- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §FIELDS-001..014
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia como única bridge
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3 obrigatório
  - [ADR-014](../../PLANNING/03-adrs.md) — Field design (Filament-like fluent API)

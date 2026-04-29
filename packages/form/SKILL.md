# SKILL.md — arqel/form

> Contexto canónico para AI agents. Estrutura conforme `PLANNING/04-repo-structure.md` §11.

## Purpose

`arqel/form` constrói formulários declarativos sobre `arqel/fields`. Um `Form` é um schema heterogéneo de **layout components** (Section, Fieldset, Grid, Columns, Group, Tabs/Tab) e **fields** (qualquer subclasse de `Arqel\Fields\Field`). O builder serializa para o payload Inertia consumido pelo lado React; em FORM-007/008 será gerado também um FormRequest com as regras de validação espelhadas.

## Status

**Entregue (FORM-001..008, 010 parcial):**

- `Arqel\Form\Form` — fluent builder (`schema`/`columns`/`model`/`inline`/`disabled`), `getFields()` flatten recursivo, `toArray()` com `kind: field|layout`
- `Arqel\Form\Layout\Component` — abstract base partilhada
- `Section`, `Fieldset`, `Grid`, `Columns`, `Group`, `Tabs`, `Tab`
- `Arqel\Form\FieldRulesExtractor` — agrega `extract`/`extractMessages`/`extractAttributes` a partir de uma lista de Fields
- `Arqel\Form\FormRequestGenerator` — gera `Store{Model}Request`/`Update{Model}Request` com stub que delega rules ao `FieldRulesExtractor`
- `FormServiceProvider` auto-discovery
- **Integração com `ResourceController` via `Resource::form()` hook (FORM-006)** — `Resource` ganha `form(): mixed` (default `null`); `Arqel\Core\Support\InertiaDataBuilder::resolveFormFields` é duck-typed contra `arqel/form` e detecta presença de `getFields()` + `toArray()`. Quando declarado, os payloads `buildCreateData`/`buildEditData`/`buildShowData` ganham chave `form` (= `Form::toArray()`) e o `fields` payload é sourced de `Form::getFields()` (flatten); sem `form()`, fallback para `Resource::fields()` flat (zero breaking-change). Retornos não-objeto também caem no fallback graciosamente
- Inertia useForm flow consumido transparentemente: `ResourceController::validated()` lança `ValidationException` → Laravel converte em `back()->withErrors()->withInput()` (FORM-008)
- Precognition stub em routes (`Route::middleware('precognitive')->post`/`put`/`patch`) — Fase 2 expande para field-level real-time
- 37 testes Pest no pacote form + 5 testes de integração (`FormPayloadIntegrationTest` em `arqel/core`) cobrindo: no-form fallback, form declarado emite payload + getFields, propagação em Edit/Show com record, fallback gracioso para retorno não-objeto

## Key Contracts

### `Arqel\Form\Form` (final)

```php
Form::make()
    ->columns(2)                                 // grid columns no nível raiz; clamp ≥ 1
    ->model(\App\Models\User::class)             // class-string<Model>|null
    ->inline()                                   // renderização inline (toggle bool)
    ->disabled()                                 // form todo desactivado (toggle bool)
    ->schema([
        Section::make('Profile')->schema([...]),
        new TextField('email'),
    ]);
```

Métodos chave:
- `getSchema(): array<int, Component|Field>` — schema raw (preserva tree)
- `getFields(): array<int, Field>` — flatten recursivo, descende por todos os layout components
- `toArray(): array{schema, columns, model, inline, disabled}` — payload Inertia

### `Arqel\Form\Layout\Component` (abstract)

Todos os layout components herdam:

- `schema(array): static` — define filhos (fields ou outros components)
- `columnSpan(int|string): static` — placement no grid pai (`'full'`, `1`..`12`, etc.)
- `visibleIf(Closure): static` / `canSee(Closure): static` — visibilidade contextual
- `isVisibleFor(?Model): bool` — oracle (canSee primeiro, depois visibleIf)
- `toArray(): array{type, component, columnSpan, props}` — final, delega para `getTypeSpecificProps()`

Subclasses só declaram `$type` / `$component` e implementam `getTypeSpecificProps(): array`.

### Layout components

| Classe | Type | Component (React) | Setters chave |
|---|---|---|---|
| `Section` | `section` | `FormSection` | `heading`/`description`/`icon`/`collapsible`/`collapsed`/`columns`/`compact`/`aside` |
| `Fieldset` | `fieldset` | `FormFieldset` | `legend`/`columns` |
| `Grid` | `grid` | `FormGrid` | `columns(int\|array)`/`gap` |
| `Columns` | `columns` | `FormColumns` | (atalho semântico: 2 colunas) |
| `Group` | `group` | `FormGroup` | `orientation('horizontal'\|'vertical')` |
| `Tabs` | `tabs` | `FormTabs` | `tabs(array<Tab>)`/`defaultTab(id)`/`vertical()` |
| `Tab` | `tab` | `FormTab` | `id`/`label`/`icon`/`badge(int\|Closure)` |

Notas:

- `Section::collapsed()` automaticamente activa `collapsible()`.
- `Grid::columns()` aceita `int` ou `array<string,int>` (e.g. `['sm' => 1, 'md' => 2, 'lg' => 4]`) — o React traduz para classes Tailwind responsivas.
- `Tabs::getDefaultTab()` faz fallback para o primeiro `Tab` filho quando não há `defaultTab(id)` explícito.
- `Tab::badge(Closure)` é resolvido em serialize-time; retornos não-int são descartados graciosamente.

## Examples

### Form típico com Section + Grid

```php
use Arqel\Fields\Types\TextField;
use Arqel\Fields\Types\SelectField;
use Arqel\Form\Form;
use Arqel\Form\Layout\Grid;
use Arqel\Form\Layout\Section;

Form::make()
    ->columns(1)
    ->model(\App\Models\User::class)
    ->schema([
        Section::make('Profile')
            ->description('Public information shown on your profile.')
            ->icon('user')
            ->columns(2)
            ->schema([
                new TextField('first_name'),
                new TextField('last_name'),
                Grid::make()->columns(['sm' => 1, 'md' => 3])->schema([
                    new TextField('email')->columnSpan(2),
                    SelectField::make('role')->options([...]),
                ]),
            ]),
        Section::make('Advanced')
            ->collapsed()
            ->schema([new TextField('api_token')]),
    ]);
```

### Tabs + visibility

```php
use Arqel\Form\Layout\Tab;
use Arqel\Form\Layout\Tabs;

Tabs::make()
    ->defaultTab('general')
    ->tabs([
        Tab::make('general', 'General')
            ->schema([new TextField('name')]),
        Tab::make('billing', 'Billing')
            ->icon('credit-card')
            ->badge(fn () => auth()->user()->unpaid_invoices_count)
            ->canSee(fn ($user) => $user?->can('billing.view'))
            ->schema([new TextField('vat_number')]),
    ]);
```

### Group invisível para visibility batch

```php
use Arqel\Form\Layout\Group;

Group::make()
    ->visibleIf(fn ($record) => $record?->status === 'archived')
    ->schema([
        new TextField('archive_reason'),
        new TextField('archived_by'),
    ]);
```

## Conventions

- `declare(strict_types=1)` obrigatório
- Layout components são `final` — extensibilidade nasce em FORM-006+ se houver pedido
- `Form` e Layout components não consultam DB nem auth — apenas declaram intenção; o controller (CORE-006) materializa
- Per-field auth (`canSee`/`canEdit`) e per-field visibility (`isVisibleIn`) são respeitados pelo controller, não pelo `Form`
- Quando precisas de visibility no nível do bloco, usa `Component::visibleIf` / `canSee` — não dupliques em todos os fields filhos

## Anti-patterns

- ❌ **Lógica de query/persistência em closures de schema** — schema é descritivo, não executável fora do controller. Closures aceitas (visibleIf/canSee/badge) são puras (sem side effects).
- ❌ **Field types definidos em `arqel/form`** — pertence a `arqel/fields`. Aqui só compomos.
- ❌ **Dependências circulares em `visibleIf`** — A depende de B que depende de A. O resolver não detecta loops; resultado é UI inconsistente.
- ❌ **Layout component sem `$type`/`$component`** — `Component::toArray()` espera ambos (PHP error em runtime se não declarado pelo subclasse).
- ❌ **Misturar `Tab` fora de `Tabs`** — `Form::getFields()` ainda flattens correctamente, mas o `defaultTab` lookup só faz sentido dentro de um `Tabs`.

## Related

- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §FORM-001..010
- ADRs: [ADR-001](../../PLANNING/03-adrs.md) Inertia-only · [ADR-008](../../PLANNING/03-adrs.md) Pest 3
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §Form
- Source: [`packages/form/src/`](src/)
- Tests: [`packages/form/tests/`](tests/)

# SKILL.md — arqel-dev/form

> Contexto canónico para AI agents. Estrutura conforme `PLANNING/04-repo-structure.md` §11.

## Purpose

`arqel-dev/form` constrói formulários declarativos sobre `arqel-dev/fields`. Um `Form` é um schema heterogéneo de **layout components** (Section, Fieldset, Grid, Columns, Group, Tabs/Tab) e **fields** (qualquer subclasse de `Arqel\Fields\Field`). O builder serializa para o payload Inertia consumido pelo lado React; em FORM-007/008 será gerado também um FormRequest com as regras de validação espelhadas.

## Status

**Entregue (FORM-001..008, 010 parcial):**

- `Arqel\Form\Form` — fluent builder (`schema`/`columns`/`model`/`inline`/`disabled`), `getFields(?Model $record = null)` flatten recursivo, `toArray(?Model $record = null)` com `kind: field|layout`. **Ambos aceitam o record atual e podam (prune) qualquer layout component (Section/Tab/Group/...) cujo `isVisibleFor($record)` seja `false` (#115)** — ver §"Layout-level visibility enforcement" abaixo
- `Arqel\Form\Layout\Component` — abstract base partilhada
- `Section`, `Fieldset`, `Grid`, `Columns`, `Group`, `Tabs`, `Tab`
- `Arqel\Form\FieldRulesExtractor` — agrega `extract`/`extractMessages`/`extractAttributes` a partir de uma lista de Fields
- `Arqel\Form\FormRequestGenerator` — gera `Store{Model}Request`/`Update{Model}Request` (via `arqel:resource --with-requests`) com stub que delega `rules()`/`messages()`/`attributes()` ao `FieldRulesExtractor`. **As regras derivam de `Resource::effectiveFields()` (v0.14.0)** — a fonte unificada de fields (validação + render), espelhando a validação em runtime do `ResourceController` — **não** só de `fields()`. **Regenere FormRequests antigos** que ainda extraíam de `fields()`, senão divergem da validação efetiva
- `FormServiceProvider` auto-discovery
- **Integração com `ResourceController` via `Resource::form()` hook (FORM-006)** — `Resource` ganha `form(): mixed` (default `null`); `Arqel\Core\Support\InertiaDataBuilder::resolveFormFields` é duck-typed contra `arqel-dev/form` e detecta presença de `getFields()` + `toArray()`. Quando declarado, os payloads `buildCreateData`/`buildEditData`/`buildShowData` ganham chave `form` (= `Form::toArray()`) e o `fields` payload é sourced de `Form::getFields()` (flatten); sem `form()`, fallback para `Resource::fields()` flat (zero breaking-change). Retornos não-objeto também caem no fallback graciosamente
- Inertia useForm flow consumido transparentemente: `ResourceController::validated()` lança `ValidationException` → Laravel converte em `back()->withErrors()->withInput()` (FORM-008)
- Precognition stub em routes (`Route::middleware('precognitive')->post`/`put`/`patch`) — Fase 2 expande para field-level real-time
- 37 testes Pest no pacote form + 5 testes de integração (`FormPayloadIntegrationTest` em `arqel-dev/core`) cobrindo: no-form fallback, form declarado emite payload + getFields, propagação em Edit/Show com record, fallback gracioso para retorno não-objeto

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
- `getFields(?Model $record = null): array<int, Field>` — flatten recursivo, descende por todos os layout components. Quando um `$record` é passado, components ocultos para esse record (`canSee`/`visibleIf` → `isVisibleFor($record) === false`) são podados, **e os fields aninhados neles não entram no flatten** — assim a validação não exige (nem persiste) fields de uma Section invisível (#115)
- `toArray(?Model $record = null): array{schema, columns, model, inline, disabled}` — payload Inertia. Quando um `$record` é passado, components ocultos para esse record são omitidos da serialização — o cliente nunca recebe (e portanto nunca renderiza) um bloco escondido (#115)

### Layout-level visibility enforcement (#115)

`Section`, `Tab`, `Group`, `Fieldset`, `Grid` e `Columns` expõem `canSee(Closure)` / `visibleIf(Closure)`, resolvidos por `Component::isVisibleFor(?Model $record)`. Antes do fix, esses oracles eram **declarativos** — o `Form` serializava e fazia flatten do schema inteiro independentemente do record, então um bloco "invisível" continuava a ser enviado ao cliente e os seus fields continuavam validados/persistidos no write path. Agora `Form::toArray($record)` e `Form::getFields($record)` **threadam o record** por toda a árvore e cortam qualquer subárvore cujo component-pai `isVisibleFor($record)` seja `false`. Net effect: o que o usuário não pode ver não é serializado, não é validado e não é gravado. A per-field auth (`canSee`/`canEdit` no nível de Field individual) continua a ser enforçada downstream pelo controller/`FieldSchemaSerializer` (não pelo `Form`).

### `Arqel\Form\Layout\Component` (abstract)

Todos os layout components herdam:

- `schema(array): static` — define filhos (fields ou outros components)
- `columnSpan(int|string): static` — placement no grid pai (`'full'`, `1`..`12`, etc.)
- `visibleIf(Closure): static` / `canSee(Closure): static` — visibilidade contextual
- `isVisibleFor(?Model): bool` — oracle (canSee primeiro, depois visibleIf). **Consumido por `Form::toArray($record)`/`getFields($record)` para podar o bloco — não é apenas metadata cosmético (#115)**
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
- `Form` e Layout components não consultam DB nem auth por conta própria — apenas declaram intenção via closures puras; é o controller (CORE-006) que injeta o `$record` em `Form::toArray($record)`/`getFields($record)` para materializar payload e regras
- **Visibility no nível de bloco (`Component::canSee`/`visibleIf`) é agora enforçada pelo próprio `Form`** (em serialize + flatten, #115) — não dupliques em cada field filho; coloca o gate no bloco-pai e os fields aninhados herdam o corte
- Per-field auth (`canSee`/`canEdit` no nível de Field individual) continua a ser respeitada downstream pelo controller/`FieldSchemaSerializer`, não pelo `Form`

## Anti-patterns

- ❌ **Lógica de query/persistência em closures de schema** — schema é descritivo, não executável fora do controller. Closures aceitas (visibleIf/canSee/badge) são puras (sem side effects).
- ❌ **Field types definidos em `arqel-dev/form`** — pertence a `arqel-dev/fields`. Aqui só compomos.
- ❌ **Dependências circulares em `visibleIf`** — A depende de B que depende de A. O resolver não detecta loops; resultado é UI inconsistente.
- ❌ **Layout component sem `$type`/`$component`** — `Component::toArray()` espera ambos (PHP error em runtime se não declarado pelo subclasse).
- ❌ **Misturar `Tab` fora de `Tabs`** — `Form::getFields()` ainda flattens correctamente, mas o `defaultTab` lookup só faz sentido dentro de um `Tabs`.
- ❌ **Dois fields com o mesmo `name`** — o `<FormRenderer>` agora resolve cada entry para o seu próprio field posicionalmente (match exato `(name,type)` primeiro, sem reusar um field já reclamado), então ambos renderizam em vez de um colapsar no último (#233, v0.14.0). Mas continua um **smell**: o `useForm` keya valores/erros por `name`, então dois fields homónimos partilham valor e mensagem de erro. Use nomes distintos.
- ❌ **Field com `component` custom não registado** — um `field.component` cujo componente nunca foi registado (`registerField(...)` no `@arqel-dev/fields`) agora renderiza um **aviso inline visível** (`<UnregisteredField>`, `data-testid="arqel-unregistered-field"`) + `console.warn`, em vez de não renderizar nada silenciosamente (#233, v0.14.0). Registe o componente — o aviso é diagnóstico, não um placeholder aceitável.

## Related

- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §FORM-001..010
- ADRs: [ADR-001](../../PLANNING/03-adrs.md) Inertia-only · [ADR-008](../../PLANNING/03-adrs.md) Pest 3
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §Form
- Source: [`packages/form/src/`](src/)
- Tests: [`packages/form/tests/`](tests/)

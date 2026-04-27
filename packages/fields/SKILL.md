# SKILL.md — arqel/fields

> Contexto canónico para AI agents (Claude Code, Cursor via MCP, etc.) a trabalhar no pacote `arqel/fields`. Estrutura conforme `PLANNING/04-repo-structure.md` §11.

## Purpose

`arqel/fields` é o pacote que define a abstracção declarativa de campos do ecossistema Arqel. Um `Field` descreve **um e apenas um** atributo do model — como deve aparecer num formulário, numa tabela, numa página de detalhe — e leva consigo:

- **Tipo + componente React** (`type` → `component` mapping)
- **Label, placeholder, helper text** (i18n-ready)
- **Validação Laravel server-side** + schema **Zod** inferido para o cliente
- **Autorização per-field** (`canSee`, `canEdit`)
- **Visibilidade contextual** (`visibleOnIndex`, `visibleOnDetail`, etc.)
- **Dependências reactivas** entre fields (`dependsOn`, `live`, `liveDebounce`)
- **Default values** e `cast` Eloquent conscientes

## Status (FIELDS-001)

Apenas o esqueleto:

- `composer.json` com dep em `arqel/core: @dev`
- Auto-discovery do `FieldServiceProvider` via `extra.laravel.providers`
- PSR-4 `Arqel\Fields\` → `src/`
- Pest + Orchestra Testbench configurados
- Smoke test verifica que o ServiceProvider boota

Ainda **NÃO existem**:

- `Arqel\Fields\Field` abstract (FIELDS-002)
- `Arqel\Fields\FieldFactory` (FIELDS-003)
- Tipos concretos: `TextField`, `NumberField`, `BooleanField`, `SelectField`, `DateField`, `FileField`, etc. (FIELDS-004..011)
- `ValidationBridge` (FIELDS-012)
- Concerns (`HasValidation`, `HasVisibility`, `HasDependencies`, `HasAuthorization`) (FIELDS-015..018)

## Key Contracts (futuros)

A API que apps consumidoras vão escrever:

```php
use Arqel\Fields\Field;

public function fields(): array
{
    return [
        Field::text('name')->required()->maxLength(255),
        Field::email('email')->required()->unique('users', 'email'),
        Field::select('role')->options(['admin' => 'Admin', 'user' => 'User']),
        Field::belongsTo('team', TeamResource::class),
        Field::date('birth_date')->maxDate('today'),
        Field::toggle('is_active')->default(true),
    ];
}
```

## Conventions

- `declare(strict_types=1)` obrigatório
- Classes `final` por default; extensão via composição (`Concerns`) preferida sobre herança
- Cada tipo concreto vive em `src/Types/{TipoField}.php`; trait partilhado em `src/Concerns/`
- **Sem dependência inversa para `arqel/core`**: core não pode depender de fields. Fields depende de core (precisa de `Arqel\Core\Contracts\HasFields` e da classe base `Resource`).

## Common tasks

### Adicionar um tipo novo de Field

1. Criar `src/Types/FooField.php` extendendo `Arqel\Fields\Field` (FIELDS-002)
2. Adicionar factory method em `Arqel\Fields\FieldFactory` (FIELDS-003)
3. Documentar `getType()` único + componente React equivalente em `06-api-react.md`
4. Testes em `tests/Unit/Types/FooFieldTest.php` cobrindo: serialização, validação, visibility/auth se aplicáveis
5. Snapshot test do JSON serializado

## Anti-patterns

- ❌ **Field com lógica de query** — eager loading vive no `ResourceController`/`indexQuery`, não no Field
- ❌ **Field com state mutável runtime** — Fields são definição imutável; runtime state vive no record/request
- ❌ **String types** — `'text'`, `'number'` em vez de classes. Usa sempre as factories
- ❌ **Acoplamento ao `ResourceRegistry`** — Fields conhecem-se a si próprios e ao record que recebem; o registry é responsabilidade do core

## Related

- Source: [`packages/fields/src/`](./src/)
- Testes: [`packages/fields/tests/`](./tests/)
- Documentação: https://arqel.dev/docs/fields (em construção)
- APIs detalhadas: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §3
- Schema TS equivalente: [`PLANNING/06-api-react.md`](../../PLANNING/06-api-react.md) §4
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia como única bridge (Fields serializam para o cliente)
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3 obrigatório

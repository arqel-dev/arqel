# SKILL.md — arqel/core

> Este ficheiro é contexto canónico para **AI agents** (Claude Code, Cursor via MCP, etc.) a trabalhar no pacote `arqel/core`. Estrutura conforme [`PLANNING/04-repo-structure.md`](../../PLANNING/04-repo-structure.md) §11.

## Purpose

`arqel/core` é o pacote base do ecossistema Arqel. Contém:

- **Service Provider** (`ArqelServiceProvider`) com auto-discovery via Laravel package discovery
- **Contracts** (`HasResource`, `HasFields`, `HasActions`, `HasPolicies`, `Renderable`) que outros pacotes implementam
- **Registries** (`ResourceRegistry`, `PanelRegistry`) para descoberta de componentes em runtime
- **Classe base `Resource`** abstracta, estendida por Resources concretos em apps consumidoras
- **Controllers HTTP** genéricos (`ResourceController`, `ActionController`, `DashboardController`) que servem Inertia responses
- **Middleware Inertia** que injecta estado partilhado (`HandleArqelInertia`, `ScopedForPanel`)
- **Facade `Arqel`** como fachada pública
- **Comandos Artisan** para install e generators (`arqel:install`, `arqel:resource`, etc.)
- **`FieldSchemaSerializer`** — serialização de instâncias de `Field` para JSON que o React consome
- **`InertiaDataBuilder`** — construção consistente de props Inertia com metadados de Resource

## Key Contracts

As APIs principais que outros pacotes e apps consomem:

### Service Provider

```php
Arqel\Core\ArqelServiceProvider
```

Auto-registado via `composer.json`:

```json
"extra": {
    "laravel": {
        "providers": ["Arqel\\Core\\ArqelServiceProvider"]
    }
}
```

### Facade

```php
use Arqel\Core\Facades\Arqel;

Arqel::registerResource(UserResource::class);
Arqel::panel('admin')->register(...);
```

### Base Resource

```php
namespace App\Arqel\Resources;

use Arqel\Core\Resources\Resource;

final class UserResource extends Resource
{
    public static string $model = \App\Models\User::class;

    public function fields(): array { /* ... */ }
    public function table(): Table { /* ... */ }
    public function actions(): array { /* ... */ }
}
```

### Contracts

- `Arqel\Core\Contracts\HasResource` — marca classe como Resource discoverable
- `Arqel\Core\Contracts\HasFields` — objetos que têm schema de fields (Resource, Form, Action)
- `Arqel\Core\Contracts\HasActions` — objetos que expõem acções
- `Arqel\Core\Contracts\HasPolicies` — ponto de integração com Laravel Policies (ADR-017)
- `Arqel\Core\Contracts\Renderable` — o que pode ir via Inertia para React

## Conventions

- **`declare(strict_types=1)`** em todos os ficheiros (enforçado por Pint — ver `pint.json` na raiz)
- **Classes `final`** por default. Se permitires extensão, documenta o contrato
- **Namespace espelha directory**: `src/Resources/Resource.php` → `Arqel\Core\Resources\Resource`
- **Testes em Pest 3**: sintaxe `it(...)` / `test(...)` em `tests/Unit` e `tests/Feature` (Orchestra Testbench para Feature)
- **Sem mocks para Eloquent** — usa factories e SQLite in-memory (ou Postgres via Testbench)
- **Coverage target: ≥90%** (core package PHP — ver [`PLANNING/12-processos-qa.md`](../../PLANNING/12-processos-qa.md) §2.2)

## Common tasks

### Adicionar um novo contract

1. Criar interface em `src/Contracts/HasFoo.php`
2. Documentar aqui no SKILL.md
3. Adicionar teste em `tests/Unit/Contracts/HasFooTest.php` verificando que classes que o implementam satisfazem o contrato

### Registar um comando Artisan

1. Criar classe em `src/Commands/MakeBarCommand.php` extendendo `Illuminate\Console\Command`
2. Registar em `ArqelServiceProvider::configurePackage()` (via `spatie/laravel-package-tools`)
3. Testar com `$this->artisan('arqel:bar')->assertSuccessful()`

### Adicionar middleware

1. Criar em `src/Http/Middleware/HandleArqelBar.php`
2. Registar em `ArqelServiceProvider::boot()` via `Route::middlewareGroup('arqel', [...])`
3. Testar acesso via `actingAs($user)->get('/arqel/...')`

## Anti-patterns

- ❌ **Depender directamente de pacotes descendentes** (`arqel/fields`, `arqel/table`, ...). Core é a base; inversão de dependência. Se precisas de algo de `fields`, expõe um contract em `core` que `fields` implementa.
- ❌ **Stringly-typed APIs** (e.g., `$resource->addField('text', 'email')`). Usa classes: `TextField::make('email')`.
- ❌ **Mutar Inertia props depois do response**. Todas as props são construídas no controller e enviadas.
- ❌ **Registrar rotas globais** fora do controlo do Panel. Rotas são sempre scoped para o `Panel` actual.
- ❌ **Chamar `Auth::user()` directo em Resources**. Usa `request()->user()` ou injeção via controller — facilita testes com `actingAs()`.
- ❌ **Macros em Eloquent em service provider** — quebra previsibilidade. Se precisares, documenta explicitamente.

## Related

- Source: [`packages/core/src/`](./src/)
- Testes: [`packages/core/tests/`](./tests/)
- Docs: https://arqel.dev/docs/core (em breve)
- MCP tools (Fase 2+): `mcp__arqel__describe_core`, `mcp__arqel__list_contracts`
- APIs detalhadas: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md)
- ADRs relevantes:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia como única bridge
  - [ADR-003](../../PLANNING/03-adrs.md) — Eloquent-native
  - [ADR-017](../../PLANNING/03-adrs.md) — Policies como authorization canónica
  - [ADR-018](../../PLANNING/03-adrs.md) — Service Provider auto-discovery

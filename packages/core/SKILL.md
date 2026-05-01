# SKILL.md — arqel/core

> Este ficheiro é contexto canónico para **AI agents** (Claude Code, Cursor via MCP, etc.) a trabalhar no pacote `arqel/core`. Estrutura conforme [`PLANNING/04-repo-structure.md`](../../PLANNING/04-repo-structure.md) §11.

## Purpose

`arqel/core` é o pacote base do ecossistema Arqel. Contém:

- **Service Provider** (`ArqelServiceProvider`) com auto-discovery via Laravel package discovery (ADR-018)
- **Contracts** (`HasResource`, `HasFields`, `HasActions`, `HasPolicies`) que outros pacotes implementam
- **Registries**:
  - `ResourceRegistry` (em `src/Resources/`): `register`/`registerMany`/`discover` (PSR-4 + Symfony Finder)/`findByModel`/`findBySlug`/`has`/`clear`/`all`. Idempotente; valida o contract via `is_subclass_of`
  - `PanelRegistry` (em `src/Panel/`): create-or-get via `panel($id)`, `setCurrent`/`getCurrent`, `all`, `has`, `clear`. Lança `PanelNotFoundException` em ID desconhecido
- **`Panel` fluent builder** (`src/Panel/Panel.php`): `path`/`brand`/`theme`/`primaryColor`/`darkMode`/`middleware`/`resources`/`widgets`/`navigationGroups`/`authGuard`/`tenant` com getters tipados
- **Classe base `Resource`** abstracta (`src/Resources/Resource.php`): static metadata (`$model`, `$slug`, `$label`, `$pluralLabel`, navigation), auto-derivation de slug/label/plural, 8 lifecycle hooks no-op, `recordTitle`/`recordSubtitle`/`indexQuery` defaults
- **Facade `Arqel`** (`src/Facades/Arqel.php`) com accessor `arqel` aliasado ao `PanelRegistry`
- **Comandos Artisan**:
  - `arqel:install {--force}` — bootstrap pipeline com Laravel Prompts (publica config, faz scaffold de `app/Arqel`, `resources/js/Pages/Arqel`, gera provider/layout/`AGENTS.md`)
  - `arqel:resource {model} {--with-policy} {--force}` — gera Resource em `app/Arqel/Resources` a partir de `stubs/resource.stub`. `--from-model`/`--from-migration` adiados até `Arqel\Fields\Field` existir
- **Blade root view** `arqel::app` (`resources/views/app.blade.php`) com `@inertia`, CSRF, FOUC guard de tema; publicada para `resources/views/vendor/arqel/`
- **Traduções** (`resources/lang/{en,pt_BR}/`): `messages`, `actions`, `table`, `form`, `validation`. Acesso via `__('arqel::messages.actions.create')`

**Entregues após o scope inicial:**

- `ResourceController` (CORE-006) — 7 endpoints polimórficos (`index/create/store/show/edit/update/destroy`) sob `arqel.resources.{action}`. Resolve Resource pelo slug via `ResourceRegistry::findBySlug`, autoriza via `Gate::denies(viewAny|create|view|update|delete)`, materializa payload via `InertiaDataBuilder`, invoca lifecycle via `Resource::runCreate/runUpdate/runDelete`. Validation via `FieldRulesExtractor` (carregado via Reflection — sem hard dep em `arqel/form`).
- `HandleArqelInertiaRequests` middleware (CORE-007) — estende `Inertia\Middleware`. Shared props: `auth.user` (`only(['id','name','email'])`), `auth.can` (delegated to `AbilityRegistry::resolveForUser` quando `arqel/auth` está bound), `panel`, `tenant`, `flash` (success/error/info/warning closures), `translations` (`arqel::*`), `arqel.version`.
- `FieldSchemaSerializer` (CORE-010) — duck-typed contra `Arqel\Fields\Field`. Filtra fields por `canBeSeenBy(user, record)`, combina `isReadonly` com `canBeEditedBy` num único flag, emite `validation.{rules,messages,attribute}`, `visibility.{create,edit,detail,table,canSee}`, `dependsOn` e `props`. `stringifyRules` descarta Closures e converte rule objects para class-string.
- `InertiaDataBuilder` (CORE-006 partial → CORE-010) — assembler dos payloads index/create/edit/show. `buildIndexData` paginate sanitizado; `buildCreateData/EditData/ShowData` retornam `{resource, record, recordTitle, recordSubtitle, fields}`. Detecta `Resource::table()` via duck-typing e roteia para `buildTableIndexData` (delega ao `Arqel\Table\TableQueryBuilder` via Reflection).
- `arqel:install` extendido com auto-instalação frontend (CORE-016) — detecta package manager (`pnpm`/`yarn`/`npm`), instala `@arqel/{react,ui,hooks,fields,types}` + peer dev deps, scaffolda `resources/js/app.tsx` + `resources/css/app.css`. Flag `--no-frontend` para skip; `--force` re-escreve.
- **Command Palette backend (CMDPAL-001):**
  - `Arqel\Core\CommandPalette\Command` — value-object readonly com `id/label/url/description/category/icon`. `toArray()` devolve sempre as 6 chaves (com `null` explícito para os opcionais).
  - `Arqel\Core\CommandPalette\CommandProvider` — contract com `provide(?Authenticatable $user, string $query): array<Command>` para fontes lazy.
  - `Arqel\Core\CommandPalette\CommandRegistry` — singleton com `register(Command)`, `registerProvider(CommandProvider|Closure)` (closures embrulhadas num adapter anónimo), `resolveFor($user, $query)` (merge static + providers → `FuzzyMatcher::rank` → cap em 20), `all()` (só estáticos), `clear()`. Em empate de score, commands estáticos vêm antes dos commands emitidos por providers (ordem de inserção preservada).
  - `Arqel\Core\CommandPalette\FuzzyMatcher` — scorer estático: empty → 100, exact case-insensitive → 95, `str_contains` → 80, subsequence ordenada → 50 + bónus por runs consecutivos, miss → 0. `rank()` aplica `score()` ao label e à description (max), descarta zeros, sort estável (desc por score, asc por índice original), corta no limit (default 20).
  - `Arqel\Core\Http\Controllers\CommandPaletteController` — single-action invokable, lê `?q=`, chama `resolveFor($request->user(), $query)`, devolve `{ commands: [...] }`. Rota: `GET /admin/commands` (`web` + `auth`, name `arqel.commands`).
- **Command Palette built-in providers (CMDPAL-002):**
  - `Arqel\Core\CommandPalette\Providers\NavigationCommandProvider` — construtor toma `ResourceRegistry`; `provide()` itera `$registry->all()` e emite uma `Command` por Resource com id `nav:{slug}`, label `'Go to {pluralLabel}'`, url `/admin/{slug}`, category `'Navigation'`, icon de `getNavigationIcon()` (ou `null`). Defensivo — Resources que rebentem em `getSlug()` ou `getPluralLabel()` são silenciosamente saltados; falha em `getNavigationIcon()` apenas downgrade para `icon=null`.
  - `Arqel\Core\CommandPalette\Providers\ThemeCommandProvider` — 3 commands estáticos (`theme:light` / `theme:dark` / `theme:system`, category `'Settings'`, icons `sun`/`moon`/`monitor`). Sempre devolve os 3 independentemente de user/query — filtragem é responsabilidade do registry.
  - Auto-registo em `ArqelServiceProvider::packageBooted()` via `$registry->registerProvider(...)`. `CreateCommandProvider` + `RecordSearchProvider` ainda deferidos (Policy + Resource model traversal).
  - **Coverage:** 35 testes unit (`tests/Unit/CommandPalette/` — Registry, FuzzyMatcher, Command value-object, Navigation/Theme providers) + 2 feature (`tests/Feature/CommandPalette*Test.php`). Total core test count: **154** (suite completa, todos verdes).
- **Command Palette ergonomics (CMDPAL-004):**
  - `CommandRegistry::registerStatic(id, label, url, description?, category?, icon?)` — sugar que cria o `Command` e chama `register()`. Re-registar o mesmo `id` lança `InvalidArgumentException("Command id '{id}' already registered")` para fazer aflorar duplicados acidentais.
  - `CommandRegistry::registerClosureProvider(Closure)` — sugar explícito sobre `registerProvider(closure)`; mesmo comportamento, mas o nome lê melhor no call-site.
  - `Command` ganhou 2 flags opcionais nullable: `?bool $requiresAuth` (true → só visível para autenticados) e `?bool $hideForAuthenticated` (true → escondido depois de login). Ambos `null` por default = sempre visível. `resolveFor()` aplica o filtro **depois** do merge static + provider e **antes** do fuzzy ranking.
  - `Arqel\Core\CommandPalette\Concerns\HasCustomCommands` — trait com `commands(CommandRegistry $registry): void` (no-op por default) que classes user-land podem override para manter o registo de custom commands DRY e dependency-free.
  - **Exemplo de uso num `ArqelServiceProvider` da app:**
    ```php
    public function boot(CommandRegistry $registry): void
    {
        $registry->registerStatic(
            id: 'cache:clear',
            label: 'Clear application cache',
            url: '/admin/system/cache-clear',
            category: 'System',
            icon: 'refresh-cw',
        );

        $registry->registerClosureProvider(function ($user, string $query): array {
            if ($user === null) {
                return [];
            }

            return [
                new Command(
                    id: 'logout',
                    label: 'Log out',
                    url: '/logout',
                    category: 'Account',
                    icon: 'log-out',
                    requiresAuth: true,
                ),
            ];
        });
    }
    ```
  - **Coverage:** +8 testes unit (`CommandRegistryStaticHelperTest`, `AuthAwareFilterTest`).

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

// PanelRegistry::panel(): cria ou devolve o Panel com este ID
Arqel::panel('admin')
    ->path('/admin')
    ->brand('Acme')
    ->resources([UserResource::class, PostResource::class]);
```

Internamente o accessor `arqel` está aliasado ao `Arqel\Core\Panel\PanelRegistry`,
por isso `Arqel::panel(...)` é o mesmo que `app(PanelRegistry::class)->panel(...)`.

### Base Resource

```php
namespace App\Arqel\Resources;

use Arqel\Core\Resources\Resource;

final class UserResource extends Resource
{
    public static string $model = \App\Models\User::class;

    // Opcional — auto-derivado a partir do nome da classe quando null:
    // public static ?string $slug = null;          // → 'users'
    // public static ?string $label = null;         // → 'User'
    // public static ?string $pluralLabel = null;   // → 'Users'

    public static ?string $navigationIcon = 'heroicon-o-user';
    public static ?string $navigationGroup = 'System';
    public static ?int $navigationSort = 10;

    public function fields(): array
    {
        return []; // Field::* virão em FIELDS-*
    }

    // Hooks opcionais (defaults são no-op):
    // protected function beforeCreate(array $data): array
    // protected function afterCreate(Model $record): void
    // protected function beforeUpdate(Model $record, array $data): array
    // protected function afterUpdate(Model $record): void
    // protected function beforeSave(Model $record, array $data): array  // create + update
    // protected function afterSave(Model $record): void
    // protected function beforeDelete(Model $record): void
    // protected function afterDelete(Model $record): void
}
```

`getModel()` lança `LogicException` se `$model` não for declarado — falha cedo
em vez de obter erros opacos no controller.

### Contracts

- `Arqel\Core\Contracts\HasResource` — 7 métodos estáticos: `getModel`, `getSlug`, `getLabel`, `getPluralLabel`, `getNavigationIcon`, `getNavigationGroup`, `getNavigationSort`. A classe base `Resource` implementa todos com auto-derivation
- `Arqel\Core\Contracts\HasFields` — `fields(): array`. Tipo solto até `Arqel\Fields\Field` existir
- `Arqel\Core\Contracts\HasActions` — marker interface; assinaturas concretas chegam com `arqel/actions`
- `Arqel\Core\Contracts\HasPolicies` — `getPolicy(): ?string` opcional; integra com Laravel Policies (ADR-017)

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
2. Adicionar à array `hasCommands([...])` em `ArqelServiceProvider::configurePackage()`
3. Testar com `Artisan::call('arqel:bar', [...])` em Orchestra Testbench
4. Padrão `stringArg()` para narrowing PHPStan (ver `MakeResourceCommand`)

### Adicionar uma string traduzida

1. Adicionar em `resources/lang/en/{messages|actions|table|form}.php`
2. Replicar em `resources/lang/pt_BR/...` (PT-BR é locale obrigatório no MVP)
3. Usar `__('arqel::messages.foo.bar')` no PHP, `t('messages.foo.bar')` no React (via shared props)

### Adicionar middleware

1. Criar em `src/Http/Middleware/HandleArqelBar.php`
2. Registar em `ArqelServiceProvider::boot()` via `Route::middlewareGroup('arqel', [...])`
3. Testar acesso via `actingAs($user)->get('/arqel/...')`

## Diagnóstico (CLI-TUI-004 / Doctor)

Comando Artisan `arqel:doctor` (em `src/Console/DoctorCommand.php`) faz um health-check **read-only e idempotente** da app: nunca escreve no disco, nunca corre migrations, nunca toca em estado. Cada check captura `Throwable` e degrada para `warn` em vez de fazer crash do report inteiro.

**10 checks executados:**

1. `php.version` — `PHP >= 8.3` (fail caso contrário)
2. `laravel.version` — `Laravel >= 12.0` (fail caso contrário)
3. `php.extensions` — `json`, `pdo`, `mbstring`, `tokenizer`, `openssl`
4. `arqel.core.version` — via `Composer\InstalledVersions::getVersion('arqel/core')` (warn se desconhecida — path repo / dev install)
5. `arqel.provider` — `ArqelServiceProvider` está em `app->getLoadedProviders()`
6. `arqel.config` — namespace `config('arqel')` está populado
7. `database.migrations` — inspecciona o `Migrator` directamente (não corre `migrate:status` aninhado, evita corromper o buffer da Artisan); warn se há migrations pendentes ou se a tabela `migrations` ainda não existe
8. `storage.writable` — `storage_path('app')` é `is_writable`
9. `cache.driver` — warn quando driver é `'array'` (volátil)
10. `session.driver` — warn quando driver é `'array'`

**Modos:**

- Default — saída human-readable com emoji + cores ANSI + summary final.
- `--json` — emite uma única linha `{checks: [...], summary: {ok, warn, fail}}`.
- `--strict` — força exit code 1 quando há `warn`s (default só falha em `fail`).

**Exemplo (default):**

```
Arqel Doctor — diagnostic report

[ok]   ✅ php.version — PHP 8.3.12 satisfies >= 8.3.
[ok]   ✅ laravel.version — Laravel 12.5.0 satisfies >= 12.0.
[ok]   ✅ php.extensions — All required PHP extensions are loaded.
[ok]   ✅ arqel.provider — ArqelServiceProvider is registered.
[warn] ⚠️  cache.driver — Cache driver is 'array' — values are not persisted.
...

Summary: 8 ok • 2 warn • 0 fail
```

**Exit code:** `0` se nenhum `fail` (e em `--strict`, também sem `warn`); `1` caso contrário. Usável directamente em pipelines de deploy e em healthcheck endpoints.

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

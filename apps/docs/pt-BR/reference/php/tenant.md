# `arqel-dev/tenant` — Referência de API

Namespace `Arqel\Tenant\`. Primitivas de multi-tenancy: um singleton `TenantManager`, um contrato `TenantResolver` com cinco implementações concretas, middleware de boot, uma trait Eloquent mais global scope, uma regra de unicidade ciente do tenant, adapters opt-in para `stancl/tenancy` e `spatie/laravel-multitenancy`, troca de tenant, theming white-label e feature gates.

O pacote cobre nativamente o caso single-database, tenant-per-row, e delega o isolamento multi-database a soluções de terceiros já maduras através de adapters. Nem `stancl/tenancy` nem `spatie/laravel-multitenancy` são dependências rígidas — cada adapter é condicionado por `class_exists`.

## `Arqel\Tenant\TenantManager` (final, singleton)

O construtor recebe `(?TenantResolver $resolver = null, ?Dispatcher $events = null)` — uma aplicação sem tenancy ainda assim obtém um manager funcional.

| Método | Tipo | Descrição |
|---|---|---|
| `resolve(Request $request)` | `?Model` | Resolve via o resolver configurado, memoizado por requisição |
| `set(?Model $tenant)` | `void` | Define o tenant atual; dispara `TenantResolved` / `TenantForgotten` |
| `forget()` | `void` | Limpa o tenant atual e dispara `TenantForgotten` |
| `runFor(Model $tenant, Closure $callback)` | `mixed` | Troca-e-restaura via `try/finally`. Exige um `Model` — `null` não é aceito |
| `current()` | `?Model` | |
| `currentOrFail()` | `Model` | |
| `hasCurrent()` | `bool` | |
| `id()` | `int\|string\|null` | Chave primária do tenant atual |
| `identifier()` | `string` | Valor da coluna identificadora, via o `identifierFor()` do resolver |
| `resolved()` | `bool` | Se a resolução já foi executada nesta requisição |
| `availableFor(Authenticatable $user)` | `array` | Delega ao resolver; lança `LogicException` quando ele não implementa `SupportsTenantSwitching` |
| `canSwitchTo(Authenticatable $user, Model $tenant)` | `bool` | Mesma delegação |
| `switchTo(Authenticatable $user, Model $tenant)` | `void` | Mesma delegação; dispara `TenantSwitched` |

## Contracts

### `Arqel\Tenant\Contracts\TenantResolver`

```php
public function resolve(Request $request): ?Model;
public function identifierFor(Model $tenant): string;
```

### `Arqel\Tenant\Contracts\SupportsTenantSwitching`

```php
public function availableFor(Authenticatable $user): array;
public function canSwitchTo(Authenticatable $user, Model $tenant): bool;
public function switchTo(Authenticatable $user, Model $tenant): void;
```

## Resolvers

`Arqel\Tenant\Resolvers\AbstractTenantResolver` (abstract) implementa ambos os contratos. Construtor `(string $modelClass, string $identifierColumn = 'id')` — lança `InvalidArgumentException` quando `$modelClass` não é uma subclasse de `Model` do Eloquent. `identifierFor()` lê a coluna configurada e cai em `getKey()` como fallback.

Cinco resolvers concretos, todos em `Arqel\Tenant\Resolvers\` e intencionalmente **não** `final`, para que as aplicações possam sobrescrever o parsing de host/header:

| Class | Fonte de resolução |
|---|---|
| `SubdomainResolver` | Primeiro rótulo do host |
| `PathResolver` | Um segmento do path da URL |
| `HeaderResolver` | Um header da requisição |
| `SessionResolver` | Chave de sessão. Sobrescreve `switchTo()` para persistir o valor da coluna identificadora na **mesma** chave de sessão que seu `resolve()` lê |
| `AuthUserResolver` | `currentTeam` no estilo Jetstream sobre o usuário autenticado; aceita `availableRelation` + `foreignKeyColumn` |

## Middleware

### `Middleware\ResolveTenantMiddleware` (final, alias `arqel.tenant`)

`handle(Request $request, Closure $next, string $mode = self::MODE_REQUIRED)`. Duas constantes: `MODE_REQUIRED` (`'required'`) e `MODE_OPTIONAL` (`'optional'`). O parsing do modo é case-insensitive e tolerante a espaços; um valor desconhecido degrada para `required`. No modo required, um tenant não resolvido lança `Exceptions\TenantNotFoundException`, cujo `render()` retorna um 404 JSON, a view Inertia `arqel::errors.tenant-not-found`, ou um fallback 404 do Symfony.

### `Middleware\RequireTenantFeature` (final, alias `arqel.tenant.feature`)

`handle(Request $request, Closure $next, string $feature)` — usado como `'arqel.tenant.feature:analytics'`. Retorna 404 sem tenant, 500 com uma mensagem acionável quando o model de tenant não tem `hasFeature`, e `402` JSON `{error: 'feature_not_available', feature, message}` quando a feature está desabilitada.

## Integração com o Eloquent

### `Concerns\BelongsToTenant` (trait)

Registra `Scopes\TenantScope` e preenche automaticamente a foreign key na criação. A chave é resolvida na ordem: propriedade `$tenantForeignKey` → `config('arqel.tenancy.foreign_key')` → `'tenant_id'`.

| Método | Tipo | Descrição |
|---|---|---|
| `getTenantKeyName()` / `getQualifiedTenantKeyName()` | `string` | Foreign key resolvida |
| `tenant()` | `BelongsTo` | |
| `scopeWithoutTenant(Builder $query)` | `Builder` | Remove o global scope |
| `scopeForTenant(Builder $query, Model\|int\|string $tenant)` | `Builder` | Consulta cross-tenant explícita |

### `Scopes\TenantScope` (final, `implements Scope`)

`apply(Builder $builder, Model $model): void`. Vira no-op de forma graciosa quando não há tenant atual ou quando o manager não está bound no container.

### `Rules\ScopedUnique` (final, `implements ValidationRule`)

Substituto ciente do tenant para o `unique` do Laravel. Construtor:

```php
new ScopedUnique(
    table: 'projects',
    column: 'slug',
    ignore: $project->id,        // default null
    ignoreColumn: 'id',          // default 'id'
    tenantForeignKey: null,      // default: resolvido a partir da config
    connection: null,
);
```

Aplica `where(<tenant_fk>, <id>)` quando há um tenant atual, caindo numa verificação de unicidade global caso contrário. Antes de aplicar o filtro, verifica `hasColumn` na tabela alvo — se a coluna de foreign key do tenant não existir, o filtro é **omitido** em vez de produzir um erro "Unknown column". Qualquer falha ao inspecionar o schema trata a coluna como presente (mantendo o comportamento escopado por tenant).

### `Concerns\HasFeatures` (trait)

`hasFeature(string): bool`, `enableFeature(string): void`, `disableFeature(string): void`, `getFeatures(): array`. É defensiva contra um atributo `features` que não seja array e deduplica valores; declare `$casts = ['features' => 'array']` no model de tenant.

## Theming

### `Theming\TenantTheme` (final, value object readonly)

Cinco propriedades nullable: `primaryColor`, `logoUrl`, `fontFamily`, `secondaryColor`, `faviconUrl`. A factory `TenantTheme::fromTenant(?Model $tenant): self` lê os atributos canônicos de forma defensiva. `toArray(): array` e `isEmpty(): bool`.

### `Theming\TenantThemeResolver` (final, singleton)

`resolve(): TenantTheme` — constrói o tema a partir de `TenantManager::current()`.

### `Theming\CssVarsRenderer` (final)

`CssVarsRenderer::renderInlineStyle(TenantTheme $theme): string` emite `<style>:root { --color-primary: …; }`. Cada slot é validado contra uma allowlist para o seu contexto CSS (cores aceitam hex / `rgb()` / `hsl()` / nomes de cores; `font_family` aceita letras, dígitos, espaço, vírgula, hífen e aspas simples; URLs precisam ser `http(s)` ou relativas à raiz e são emitidas como um `url('…')` escapado). Valores que não passam pela allowlist são **omitidos** — nunca emitidos sem escape — o que neutraliza payloads de CSS injection contendo `}`.

## Adapters

Ambos residem em `Arqel\Tenant\Integrations\` e implementam `TenantResolver`; cada um é condicionado por `class_exists`, de modo que nenhum dos pacotes de terceiros se torna dependência rígida.

| Class | Comportamento |
|---|---|
| `StanclAdapter` (final) | Lê `Stancl\Tenancy\Tenancy::tenant` (nome do binding na constante `TENANCY_BINDING`); respeita `getTenantKey()` com fallback para `getKey()` |
| `SpatieAdapter` (final) | Chama o `current()` estático do Spatie; um `modelClass` vazio cai na constante `SPATIE_TENANT_CLASS` (`Spatie\Multitenancy\Models\Tenant`) |

## Eventos

Todos `final`, com propriedades promovidas readonly:

| Evento | Payload |
|---|---|
| `Events\TenantResolved` | `Model $tenant` |
| `Events\TenantForgotten` | `Model $tenant` |
| `Events\TenantSwitched` | `?Model $from`, `Model $to`, `Authenticatable $user` |

## HTTP

Registrado sob `web` + `auth` com o prefixo `admin/tenants`:

| Verbo | Route | Nome | Action |
|---|---|---|---|
| POST | `admin/tenants/{tenantId}/switch` | `arqel.tenant.switch` | `TenantSwitcherController::switch` — 404/403, depois dispatch + redirect |
| GET | `admin/tenants/available` | `arqel.tenant.available` | `TenantSwitcherController::list` — `{current, available[]}` |

## Comandos Artisan

Cada scaffolder escreve três stubs opt-in (controller + trecho de rotas + página Inertia), é idempotente (sai com código 0 sem fazer nada, a menos que `--force`) e faz append em `routes/web.php` exatamente uma vez, atrás de um marcador.

| Comando | Função |
|---|---|
| `arqel:tenant:scaffold-registration {--force}` | Fluxo de cadastro de tenant |
| `arqel:tenant:scaffold-profile {--force}` | Configurações de perfil do tenant |
| `arqel:tenant:scaffold-billing {--force}` | Esqueleto de página de billing |

## Exemplo

```php
// config/arqel.php
return [
    'tenancy' => [
        'resolver' => Arqel\Tenant\Resolvers\SubdomainResolver::class,
        'model' => App\Models\Tenant::class,
        'identifier_column' => 'slug',
        'foreign_key' => 'tenant_id',
    ],
];

// routes/web.php
Route::middleware(['web', 'auth', 'arqel.tenant'])->group(function (): void {
    Route::get('/admin', AdminController::class);
});
```

```php
use Arqel\Tenant\Concerns\BelongsToTenant;

final class Project extends Model
{
    use BelongsToTenant;

    // protected string $tenantForeignKey = 'organization_id';
}

Project::all();                     // escopado automaticamente ao tenant atual
Project::withoutTenant()->get();    // escape hatch explícita
Project::forTenant($otherId)->get();

// Override administrativo que preserva os lifecycle hooks / trilha de auditoria:
app(TenantManager::class)->runFor($otherTenant, fn () => Project::all());
```

```php
use Arqel\Tenant\Theming\TenantThemeResolver;

public function share(Request $request): array
{
    $theme = app(TenantThemeResolver::class)->resolve();

    return [
        ...parent::share($request),
        'tenant' => ['theme' => $theme->isEmpty() ? null : $theme->toArray()],
    ];
}
```

## Relacionados

- SKILL: [`packages/tenant/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/tenant/SKILL.md)
- Código-fonte: [`packages/tenant/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/tenant/src/)

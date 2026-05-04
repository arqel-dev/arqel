# 09 — Fase 2 (Essenciais): Tickets Detalhados

> Lista completa de tickets para a **Fase 2 (Essenciais)** do Arqel. Transforma o MVP em framework completo e production-ready com features que 80% dos projetos Laravel admin precisam.

## Índice

1. [Visão geral da fase](#1-visão-geral-da-fase)
2. [Multi-tenancy (TENANT)](#2-multi-tenancy-tenant)
3. [Dashboards e widgets (WIDGETS)](#3-dashboards-e-widgets-widgets)
4. [Advanced fields (FIELDS-ADV)](#4-advanced-fields-fields-adv)
5. [MCP server (MCP)](#5-mcp-server-mcp)
6. [Table enhancements (TABLE-V2)](#6-table-enhancements-table-v2)
7. [Export e import (EXPORT)](#7-export-e-import-export)
8. [Command palette (CMDPAL)](#8-command-palette-cmdpal)
9. [Audit log (AUDIT)](#9-audit-log-audit)
10. [Docs e release (DOCS-V2, GOV-V2)](#10-docs-e-release-docs-v2-gov-v2)
11. [Ordem sugerida de execução](#11-ordem-sugerida-de-execução)

## 1. Visão geral da fase

**Objetivo** (ver `07-roadmap-fases.md` §4): adicionar multi-tenancy, dashboards com widgets, 8 field types avançados (RichText, Markdown, Code, Repeater, Builder, KeyValue, Tags, Wizard), MCP server oficial, enhancements em table (virtual scrolling, inline editing, QueryBuilder), export/import, command palette e audit log.

**Duração:** 4-7 meses com 3-4 devs.

**Total de tickets Fase 2:** ~90, distribuídos:

| Pacote | Tickets | % |
|---|---|---|
| TENANT | 15 | 17% |
| WIDGETS | 15 | 17% |
| FIELDS-ADV | 20 | 22% |
| MCP | 10 | 11% |
| TABLE-V2 | 10 | 11% |
| EXPORT | 7 | 8% |
| CMDPAL | 5 | 6% |
| AUDIT | 4 | 4% |
| DOCS-V2 + GOV-V2 | 4 | 4% |

**Critérios de saída** (ver `07-roadmap-fases.md` §4.3):
- Multi-tenancy em produção em ≥3 pilot apps
- Dashboard demo com 5+ widgets diversos
- MCP server testado com Claude Code + Cursor
- Export de 10k records em <30s
- Virtual scrolling renderiza 100k rows smooth
- Command palette funcional com 20+ commands
- 100+ production users, 15+ third-party plugins, 2.000+ GitHub stars

**Release esperado ao fim:** v0.8.0 (RC)

---

## 2. Multi-tenancy (TENANT)

### [TENANT-001] Esqueleto do pacote `arqel-dev/tenant`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [CORE-008] (Fase 1)

**Contexto**

Multi-tenancy é feature crítica para SaaS B2B. Cobre RF-MT-01 a RF-MT-11. Abordagem: fornecer abstração flexível que funciona com single-DB (scoped queries via Eloquent global scopes) e integra com soluções mais robustas (stancl/tenancy, spatie/laravel-multitenancy) via adapters.

**Descrição técnica**

Estrutura `packages/tenant/`:

- `composer.json` (dep: `arqel-dev/core`)
- `src/TenantManager.php` (singleton)
- `src/TenantResolver.php` (interface + base implementations)
- `src/Concerns/BelongsToTenant.php` (Eloquent trait)
- `src/Scopes/TenantScope.php` (global scope)
- `src/Middleware/ResolveTenantMiddleware.php`
- `src/Integrations/` (StanclAdapter, SpatieAdapter)
- `src/Rules/ScopedUnique.php`
- `src/TenantServiceProvider.php`
- `SKILL.md`, `README.md`
- `tests/`

**Critérios de aceite**

- [ ] `composer require arqel-dev/tenant` (path) resolve sem erros
- [ ] ServiceProvider discovered
- [ ] SKILL.md esqueleto com estrutura canônica
- [ ] Tests diretório estruturado

**Notas de implementação**

- Multi-tenancy é complexo e varioso — nossa opção é não reinventar, mas integrar bem com o ecossistema existente.
- Suportar "tenant-per-model" (scoped) é o mínimo; multi-DB via adapters.

---

### [TENANT-002] `TenantResolver` — interface e implementações padrão

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [TENANT-001]

**Contexto**

Cobre RF-MT-02. Resolver extrai o tenant atual do request via diferentes estratégias: subdomain, path segment, header HTTP, session, ou authenticated user.

**Descrição técnica**

Criar `src/Contracts/TenantResolver.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Tenant\Contracts;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

interface TenantResolver
{
    public function resolve(Request $request): ?Model;
    
    /**
     * @return string Unique identifier for caching purposes.
     */
    public function identifierFor(Model $tenant): string;
}
```

Criar implementações em `src/Resolvers/`:

- `SubdomainResolver`: lê subdomain (`acme.myapp.com` → tenant 'acme')
- `PathResolver`: lê primeiro segmento path (`/acme/dashboard` → 'acme')
- `HeaderResolver`: lê header `X-Tenant-ID` ou configurável
- `SessionResolver`: lê session key `current_tenant_id`
- `AuthUserResolver`: usa `auth()->user()->currentTeam` ou similar — default mais comum

Cada resolver aceita config via constructor:

```php
class SubdomainResolver implements TenantResolver
{
    public function __construct(
        private string $modelClass,
        private string $identifierColumn = 'subdomain',
        private ?string $centralDomain = null,
    ) {}
    
    public function resolve(Request $request): ?Model
    {
        $subdomain = $this->extractSubdomain($request->getHost());
        if (!$subdomain) return null;
        
        return ($this->modelClass)::where($this->identifierColumn, $subdomain)->first();
    }
}
```

Registrar resolver default em `config/arqel.php`:

```php
'tenancy' => [
    'resolver' => \Arqel\Tenant\Resolvers\AuthUserResolver::class,
    'model' => null, // Set by user, e.g., \App\Models\Team::class
    'foreign_key' => 'team_id',
],
```

**Critérios de aceite**

- [ ] 5 resolvers implementados e testáveis
- [ ] `SubdomainResolver` extrai subdomain corretamente com central domain config
- [ ] `PathResolver` funciona com rotas parametrizadas
- [ ] `AuthUserResolver` lê currentTeam de user model (convention Jetstream-like)
- [ ] Resolver customizado pode ser registrado: `Arqel::tenantResolver(CustomResolver::class)`
- [ ] Teste: cada resolver em isolamento com fixtures
- [ ] Teste Feature: integration com request real

**Notas de implementação**

- SubdomainResolver precisa config `central_domain` para distinguir `app.com` de `tenant.app.com`.
- PathResolver pode conflitar com rotas Arqel (`/admin/...`) — oferecer prefix config.
- AuthUserResolver é o mais comum em apps Laravel modernas (Jetstream, Spark pattern).

---

### [TENANT-003] `TenantManager` singleton + resolução em request

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [TENANT-002]

**Contexto**

Manager central que guarda o tenant atual durante o request e expõe API para querying.

**Descrição técnica**

Criar `src/TenantManager.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Tenant;

use Arqel\Tenant\Contracts\TenantResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class TenantManager
{
    private ?Model $currentTenant = null;
    private bool $resolved = false;

    public function __construct(
        private readonly TenantResolver $resolver,
    ) {}

    public function resolve(Request $request): ?Model
    {
        if ($this->resolved) {
            return $this->currentTenant;
        }
        
        $this->currentTenant = $this->resolver->resolve($request);
        $this->resolved = true;
        
        if ($this->currentTenant) {
            $this->applyGlobalScope();
            event(new TenantResolved($this->currentTenant));
        }
        
        return $this->currentTenant;
    }

    public function current(): ?Model;
    public function id(): int|string|null;
    public function set(?Model $tenant): void;
    public function forget(): void;
    public function runFor(Model $tenant, \Closure $callback): mixed;
    public function resolved(): bool;
    
    private function applyGlobalScope(): void
    {
        // Applied via BelongsToTenant trait on models
    }
}
```

Registrar como singleton no ServiceProvider:

```php
$this->app->singleton(TenantManager::class, function ($app) {
    $resolverClass = config('arqel.tenancy.resolver');
    $model = config('arqel.tenancy.model');
    return new TenantManager(new $resolverClass($model));
});
```

**Critérios de aceite**

- [ ] `app(TenantManager::class)->resolve($request)` retorna tenant ou null
- [ ] `current()` após resolve retorna mesmo tenant (cached)
- [ ] `set($tenant)` permite override programático
- [ ] `runFor($tenant, $cb)` executa closure com tenant temporário e restaura
- [ ] `TenantResolved` event disparado quando resolve
- [ ] Testes cobrem: resolve happy, resolve null, override, runFor

**Notas de implementação**

- `runFor` é útil para background jobs (queue workers não têm request).
- Event `TenantResolved` útil para features que precisam reagir (analytics, logging).

---

### [TENANT-004] Middleware `ResolveTenantMiddleware`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [TENANT-003]

**Contexto**

Middleware que invoca TenantManager para cada request Arqel.

**Descrição técnica**

Criar `src/Middleware/ResolveTenantMiddleware.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Tenant\Middleware;

use Arqel\Tenant\TenantManager;
use Arqel\Tenant\Exceptions\TenantNotFoundException;
use Closure;
use Illuminate\Http\Request;

final class ResolveTenantMiddleware
{
    public function __construct(private TenantManager $manager) {}

    public function handle(Request $request, Closure $next, ?string $mode = 'required'): mixed
    {
        $tenant = $this->manager->resolve($request);
        
        if ($mode === 'required' && !$tenant) {
            throw new TenantNotFoundException();
        }
        
        return $next($request);
    }
}
```

Registrar alias no ServiceProvider:

```php
$router->aliasMiddleware('arqel.tenant', ResolveTenantMiddleware::class);
```

Uso em Panel:

```php
Arqel::panel('admin')
    ->middleware(['web', 'auth', 'arqel.tenant'])
    // Or optional:
    ->middleware(['web', 'auth', 'arqel.tenant:optional']);
```

Custom exception com handler que redireciona para landing page de erro:

```php
class TenantNotFoundException extends \Exception
{
    public function render(Request $request): Response
    {
        return Inertia::render('arqel::errors/tenant-not-found', [
            'tenantIdentifier' => $request->getHost(),
        ])->toResponse($request)->setStatusCode(404);
    }
}
```

**Critérios de aceite**

- [ ] Middleware aplicado em rotas resolve tenant antes de controller
- [ ] Mode `required` retorna 404 quando tenant não encontrado
- [ ] Mode `optional` permite continuar sem tenant
- [ ] Exception renderizada bonitamente (Inertia page)
- [ ] Teste Feature: request com/sem tenant válido

**Notas de implementação**

- Ordem de middleware importa: `arqel.tenant` deve rodar depois de `auth` se AuthUserResolver.
- `optional` mode útil para páginas públicas dentro de app multi-tenant (landing page marketing).

---

### [TENANT-005] Trait `BelongsToTenant` + `TenantScope` global

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [TENANT-004]

**Contexto**

Cobre RF-MT-03 (auto-scoping via global scope). Trait aplicada em Eloquent models garante queries sempre filtradas por tenant atual.

**Descrição técnica**

Criar `src/Scopes/TenantScope.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Tenant\Scopes;

use Arqel\Tenant\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $manager = app(TenantManager::class);
        $tenant = $manager->current();
        
        if ($tenant === null) {
            return; // No tenant: no scoping (e.g., background job without tenant)
        }
        
        $foreignKey = $model->getQualifiedTenantKeyName();
        $builder->where($foreignKey, $tenant->getKey());
    }
}
```

Criar `src/Concerns/BelongsToTenant.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Tenant\Concerns;

use Arqel\Tenant\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());
        
        static::creating(function (Model $model) {
            if (!$model->getAttribute($model->getTenantKeyName())) {
                $tenant = app(\Arqel\Tenant\TenantManager::class)->current();
                if ($tenant) {
                    $model->setAttribute($model->getTenantKeyName(), $tenant->getKey());
                }
            }
        });
    }

    public function getTenantKeyName(): string
    {
        return config('arqel.tenancy.foreign_key', 'team_id');
    }

    public function getQualifiedTenantKeyName(): string
    {
        return $this->getTable() . '.' . $this->getTenantKeyName();
    }

    public function tenant(): BelongsTo
    {
        $tenantModel = config('arqel.tenancy.model');
        return $this->belongsTo($tenantModel, $this->getTenantKeyName());
    }

    public function scopeWithoutTenant(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
    
    public function scopeForTenant(Builder $query, Model|int|string $tenant): Builder
    {
        $id = $tenant instanceof Model ? $tenant->getKey() : $tenant;
        return $query->withoutGlobalScope(TenantScope::class)
            ->where($this->getTenantKeyName(), $id);
    }
}
```

**Critérios de aceite**

- [ ] Model com trait BelongsToTenant auto-scope queries quando tenant ativo
- [ ] Creating event auto-preenche tenant_id
- [ ] `Model::withoutTenant()` escapa scope (útil para admin global)
- [ ] `Model::forTenant($tenantId)` filtra por tenant específico
- [ ] Relacionamento `tenant()` retorna Tenant model
- [ ] No tenant resolved = no scope applied (behavior graceful)
- [ ] Teste: CRUD isolado por tenant, cross-tenant prevention

**Notas de implementação**

- Global scope é magic — documentar cuidadosamente. N+1 debug pode confundir.
- `withoutTenant()` é escape hatch crítico — usar com cuidado, documentar risk.

---

### [TENANT-006] `ScopedUnique` validation rule

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [TENANT-005]

**Contexto**

Cobre RF-MT-09. `unique` rule tradicional Laravel valida globalmente; em multi-tenant precisamos unicidade dentro do tenant.

**Descrição técnica**

Criar `src/Rules/ScopedUnique.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Tenant\Rules;

use Arqel\Tenant\TenantManager;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ScopedUnique implements ValidationRule
{
    public function __construct(
        private string $table,
        private string $column,
        private mixed $ignore = null,
        private string $ignoreColumn = 'id',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $tenant = app(TenantManager::class)->current();
        $tenantKey = config('arqel.tenancy.foreign_key', 'team_id');
        
        $query = \DB::table($this->table)
            ->where($this->column, $value);
        
        if ($tenant) {
            $query->where($tenantKey, $tenant->getKey());
        }
        
        if ($this->ignore !== null) {
            $query->where($this->ignoreColumn, '!=', $this->ignore);
        }
        
        if ($query->exists()) {
            $fail(__('validation.unique', ['attribute' => $attribute]));
        }
    }
}
```

Helper factory em FieldFactory ou Rule macro:

```php
// Field side
Field::text('slug')
    ->rule(new ScopedUnique('posts', 'slug', ignore: $this->record?->id));

// Or shortcut
Field::text('slug')->uniqueInTenant('posts', 'slug');
```

**Critérios de aceite**

- [ ] Rule detecta duplicatas dentro do tenant atual
- [ ] Rule ignora registros com ID ignore
- [ ] Sem tenant ativo, valida globalmente (fallback graceful)
- [ ] Helper `uniqueInTenant` em Field disponível
- [ ] Teste: cross-tenant allow, same-tenant deny

**Notas de implementação**

- Se app usa multi-DB via stancl/tenancy, unique scoping já é automático (queries contra DB diferente). Rule é para single-DB tenancy.

---

### [TENANT-007] Adapter para `stancl/tenancy` (multi-DB)

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** L • **Camada:** php • **Depende de:** [TENANT-003]

**Contexto**

Cobre RF-MT-07. stancl/tenancy é a solução mais popular para multi-DB tenancy (cada tenant = database separado). Nosso adapter integra com ela.

**Descrição técnica**

Criar `src/Integrations/StanclAdapter.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Tenant\Integrations;

use Arqel\Tenant\Contracts\TenantResolver;
use Stancl\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

final class StanclAdapter implements TenantResolver
{
    public function __construct(private Tenancy $tenancy) {}

    public function resolve(Request $request): ?Model
    {
        // Stancl already initializes tenant via its own middleware
        return $this->tenancy->tenant ?? null;
    }

    public function identifierFor(Model $tenant): string
    {
        return $tenant->getTenantKey();
    }
}
```

Suggest em composer.json:

```json
"suggest": {
    "stancl/tenancy": "For multi-database tenancy (^3.7)"
}
```

Docs específicas em `guide/tenancy/with-stancl.md`:
- Setup stancl primeiro (tenancy.config, Tenant model)
- Então configurar Arqel para usar `StanclAdapter`
- Exemplo completo com subdomain + DB isolation

**Critérios de aceite**

- [ ] Adapter funciona com stancl/tenancy 3.7+
- [ ] Arqel panels respeitam tenant context stancl
- [ ] Teste integration: resolver retorna stancl tenant corretamente
- [ ] Docs completas com exemplo end-to-end

**Notas de implementação**

- stancl/tenancy tem middleware próprio (`InitializeTenancyByDomain`, etc.) — Arqel adapter roda DEPOIS desses.
- Testing requer fixtures SQLite separadas por tenant.

---

### [TENANT-008] Adapter para `spatie/laravel-multitenancy`

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php • **Depende de:** [TENANT-003]

**Contexto**

Cobre RF-MT-08. spatie/laravel-multitenancy é alternativa mais simples (não força multi-DB).

**Descrição técnica**

Criar `src/Integrations/SpatieAdapter.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Tenant\Integrations;

use Arqel\Tenant\Contracts\TenantResolver;
use Spatie\Multitenancy\Models\Tenant as SpatieTenant;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

final class SpatieAdapter implements TenantResolver
{
    public function resolve(Request $request): ?Model
    {
        return SpatieTenant::current();
    }

    public function identifierFor(Model $tenant): string
    {
        return (string) $tenant->getKey();
    }
}
```

Suggest em composer.json:

```json
"suggest": {
    "spatie/laravel-multitenancy": "For multi-tenancy with Spatie approach (^3.2)"
}
```

Docs em `guide/tenancy/with-spatie.md`.

**Critérios de aceite**

- [ ] Adapter funciona com spatie/laravel-multitenancy 3.2+
- [ ] Teste integration
- [ ] Docs completas

---

### [TENANT-009] Tenant switcher component (backend route)

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php • **Depende de:** [TENANT-003]

**Contexto**

Cobre RF-MT-04 (backend side). User que pertence a múltiplos tenants pode trocar. Component React vem em WIDGETS-002 equivalente.

**Descrição técnica**

Criar `src/Http/Controllers/TenantSwitcherController.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Tenant\Http\Controllers;

use Arqel\Tenant\TenantManager;
use Arqel\Tenant\Events\TenantSwitched;
use Illuminate\Http\Request;

final class TenantSwitcherController
{
    public function switch(Request $request, TenantManager $manager, string $tenantId): RedirectResponse
    {
        $user = $request->user();
        $tenant = $this->resolveTenantFor($user, $tenantId);
        
        abort_if(!$tenant, 404);
        abort_if(!$this->canSwitchTo($user, $tenant), 403);
        
        // Strategy depends on resolver
        $this->applySwitch($user, $tenant);
        
        event(new TenantSwitched(from: $manager->current(), to: $tenant, user: $user));
        
        return redirect()->intended('/admin');
    }
    
    public function list(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenants = $this->listAvailableTenantsFor($user);
        
        return response()->json([
            'current' => $user->currentTeam?->only(['id', 'name', 'slug', 'logo']),
            'available' => $tenants->map(fn ($t) => $t->only(['id', 'name', 'slug', 'logo'])),
        ]);
    }
}
```

Rota:

- `POST /admin/tenants/{tenantId}/switch`
- `GET /admin/tenants/available`

Expor tenant info em shared props (já stub em CORE-007 Fase 1):

```php
'tenant' => [
    'current' => $user?->currentTeam?->only(['id', 'name', 'slug', 'logo']),
    'available' => $user ? $this->availableTenants($user) : [],
],
```

**Critérios de aceite**

- [ ] Switch tenant valida que user tem acesso
- [ ] Switch dispara `TenantSwitched` event
- [ ] Shared props incluem available tenants para render no sidebar
- [ ] List endpoint retorna só tenants autorizados
- [ ] Teste Feature: switch happy path + unauthorized

**Notas de implementação**

- Estratégia de switch depende do resolver. Para `AuthUserResolver`, mudar `user.current_team_id`. Para session resolver, update session. Para stancl, usar API stancl.

---

### [TENANT-010] Tenant registration flow scaffolder

**Tipo:** feat • **Prioridade:** P2 • **Estimativa:** L • **Camada:** php • **Depende de:** [TENANT-003]

**Contexto**

Cobre RF-MT-05. Onboarding flow quando novo user cria tenant.

**Descrição técnica**

Artisan command `arqel:tenant:scaffold-registration`:

- Gera `app/Http/Controllers/TenantRegistrationController.php` com:
  - `showForm()` — exibe form Inertia
  - `register()` — cria Tenant + primeira User + relaciona
- Gera `app/Arqel/Resources/TenantResource.php` (se não existir)
- Gera page React `resources/js/Pages/Arqel/TenantRegister.tsx` (template Inertia básico)
- Adiciona rotas em `routes/web.php` (apenas se não existirem)

**Critérios de aceite**

- [ ] Comando gera arquivos corretos
- [ ] Flow end-to-end funciona: submeter form → cria tenant → redirect para painel
- [ ] Teste Feature: registration happy + validation errors

**Notas de implementação**

- Scaffolder é opt-in — users podem ter seus próprios flows.
- Generated code é ponto de partida, não mandatory.

---

### [TENANT-011] Tenant profile page scaffolder

**Tipo:** feat • **Prioridade:** P2 • **Estimativa:** M • **Camada:** php • **Depende de:** [TENANT-010]

**Contexto**

Cobre RF-MT-06. Profile settings do tenant (nome, logo, features, etc.).

**Descrição técnica**

Artisan command `arqel:tenant:scaffold-profile`:

- Gera `TenantSettingsResource` (ou atualiza TenantResource)
- Generic profile page route `/admin/settings/tenant` que renderiza edit form do current tenant
- Fields standard (name, slug, logo, timezone, locale) + extensible via config

**Critérios de aceite**

- [ ] Command gera files
- [ ] Tenant settings page funcional
- [ ] Teste: update tenant via UI

---

### [TENANT-012] White-labeling por tenant (CSS vars injection)

**Tipo:** feat • **Prioridade:** P2 • **Estimativa:** M • **Camada:** php + react • **Depende de:** [TENANT-003, UI-002] (Fase 1)

**Contexto**

Cobre RF-MT-10. Tenants podem customizar cores, logo, fontes via CSS vars injectadas.

**Descrição técnica**

Server-side:

- Extend Tenant model (convention) com fields: `primary_color`, `logo_url`, `font_family`
- Em `HandleArqelInertiaRequests::share()`, incluir:
  ```php
  'panel' => [
      // ... existing
      'theme' => [
          'primaryColor' => $tenant?->primary_color,
          'logoUrl' => $tenant?->logo_url,
          'fontFamily' => $tenant?->font_family,
      ],
  ],
  ```

Client-side em `@arqel-dev/react`:

- `ArqelProvider` aplica CSS vars via `<style>` tag ou `document.documentElement.style.setProperty()`:
  ```typescript
  useEffect(() => {
      if (panel.theme.primaryColor) {
          document.documentElement.style.setProperty('--color-primary', panel.theme.primaryColor);
      }
  }, [panel.theme]);
  ```

**Critérios de aceite**

- [ ] CSS vars são aplicadas no root com base em tenant
- [ ] Logo do tenant substitui default no sidebar
- [ ] Mudança de tenant atualiza tema sem full reload
- [ ] Teste E2E: tenant A vs tenant B com temas diferentes

**Notas de implementação**

- oklch colors preferidos (Tailwind v4 default).
- Não permitir CSS arbitrary (security); apenas presets ou color picker validado.

---

### [TENANT-013] Integração opcional com Laravel Cashier (billing)

**Tipo:** feat • **Prioridade:** P2 • **Estimativa:** L • **Camada:** php • **Depende de:** [TENANT-003]

**Contexto**

Cobre RF-MT-11. Tenants têm subscription tiers, billing. Cashier é a solução canônica Laravel.

**Descrição técnica**

- Documentação em `guide/tenancy/billing-cashier.md`
- Generic `SubscriptionResource` scaffolder: `arqel:tenant:scaffold-billing`
- Widget `CurrentSubscriptionWidget` (ver WIDGETS seção)
- `Feature::middleware(['has-feature:analytics'])` helper middleware que verifica plan features

**Critérios de aceite**

- [ ] Scaffolder gera resources de billing
- [ ] Feature gate middleware funciona
- [ ] Docs completas com Stripe + Paddle examples

**Notas de implementação**

- Cashier 15+ suporta Stripe e Paddle.
- Webhook handling fica com user (pattern Cashier standard).

---

### [TENANT-014] Testes completos do pacote TENANT

**Tipo:** test • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [TENANT-013]

**Contexto**

Multi-tenancy tem muitos edge cases. Cobertura é crítica.

**Descrição técnica**

- Unit tests: TenantManager, cada Resolver, TenantScope, BelongsToTenant trait
- Feature tests:
  - Request com subdomain valid/invalid
  - CRUD isolado por tenant
  - Cross-tenant data leak prevention
  - Switch tenant flow
  - ScopedUnique rule
  - Middleware modes (required vs optional)
- Integration tests com stancl (requires stancl installed)
- Coverage ≥ 90%

**Critérios de aceite**

- [ ] `vendor/bin/pest packages/tenant/tests` passa
- [ ] Coverage ≥ 90%
- [ ] Cross-tenant leakage test cobre todos CRUD operations
- [ ] Stancl integration test opcional (skipped if package não instalado)

**Notas de implementação**

- Setup fixtures: criar 2+ tenants, múltiplos records em cada, verificar isolation.
- Bug comum: forgot apply scope em bulk update — test cobre.

---

### [TENANT-015] SKILL.md do pacote tenant

**Tipo:** docs • **Prioridade:** P1 • **Estimativa:** S • **Camada:** docs • **Depende de:** [TENANT-014]

**Descrição técnica**

SKILL.md com:
- Purpose: multi-tenancy flexible
- Key contracts: TenantResolver, TenantManager, BelongsToTenant
- Strategies: single-DB (scoped), multi-DB (stancl), spatie
- Exemplos completos de cada strategy
- Anti-patterns:
  - ❌ Chamar `Model::where(...)` em bulk update sem considerar scope
  - ❌ Usar `withoutTenant()` sem entender risco
  - ❌ Não testar cross-tenant leakage
  - ❌ Esquecer de aplicar BelongsToTenant em todos os models tenant-scoped

**Critérios de aceite**

- [ ] SKILL.md completo e validado
- [ ] Cross-tenant leakage checklist incluído

---

## 3. Dashboards e widgets (WIDGETS)

### [WIDGETS-001] Esqueleto do pacote e classe `Widget` base

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [CORE-008] (Fase 1)

**Contexto**

Cobre RF-W-01 a RF-W-06. Dashboard é feature prime-time — primeiro contato frequente do user admin.

**Descrição técnica**

Widgets vivem dentro de `arqel-dev/core` (sub-diretório `Widgets/`) ou como pacote separado `arqel-dev/widgets`. **Decisão:** separar em `arqel-dev/widgets` para isolamento e versioning.

Estrutura `packages/widgets/`:

- `composer.json` (dep: `arqel-dev/core`)
- `src/Widget.php` (abstract base)
- `src/StatWidget.php`
- `src/ChartWidget.php`
- `src/TableWidget.php`
- `src/CustomWidget.php`
- `src/Dashboard.php`
- `src/WidgetRegistry.php`
- `src/Http/Controllers/DashboardController.php`
- `src/Http/Controllers/WidgetDataController.php`
- `src/WidgetsServiceProvider.php`
- `SKILL.md`, `tests/`

Classe `Widget` abstract:

```php
<?php

declare(strict_types=1);

namespace Arqel\Widgets;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;

abstract class Widget
{
    protected ?string $heading = null;
    protected ?string $description = null;
    protected ?int $sort = null;
    protected int|string $columnSpan = 1;
    protected ?int $pollingInterval = null;
    protected bool $deferred = false;
    protected ?Closure $canSee = null;
    protected array $filters = [];
    
    public function heading(string $heading): static;
    public function description(string $description): static;
    public function sort(int $sort): static;
    public function columnSpan(int|string $span): static;
    public function poll(int $seconds): static;
    public function deferred(bool $deferred = true): static;
    public function canSee(Closure $callback): static;
    public function filters(array $filters): static;
    
    abstract public function data(): array;
    abstract protected function component(): string;
    
    public function toArray(?Authenticatable $user = null): array
    {
        return [
            'type' => $this->type(),
            'component' => $this->component(),
            'id' => $this->id(),
            'heading' => $this->heading,
            'description' => $this->description,
            'sort' => $this->sort,
            'columnSpan' => $this->columnSpan,
            'pollingInterval' => $this->pollingInterval,
            'deferred' => $this->deferred,
            'data' => $this->deferred ? null : $this->data(),
        ];
    }
    
    public function canBeSeenBy(?Authenticatable $user): bool;
    public function id(): string;
    abstract protected function type(): string;
}
```

**Critérios de aceite**

- [ ] Pacote resolve via path composer repo
- [ ] Widget base é abstract e não instanciável
- [ ] Fluent API encadeia corretamente
- [ ] `toArray()` serializa com shape esperado
- [ ] Polling interval serializado para client
- [ ] Deferred serializado com `data: null` (client faz lazy fetch)
- [ ] Testes unitários cobrem fluent API e serialização

**Notas de implementação**

- `deferred` útil para widgets pesados que não devem bloquear first paint.
- Polling é server-pushed via client — client cuida do setInterval.

---

### [WIDGETS-002] `StatWidget` (KPI cards)

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [WIDGETS-001]

**Contexto**

Cobre RF-W-02 (KPI variant). Widget mais comum em dashboards: número grande + contexto.

**Descrição técnica**

```php
<?php

declare(strict_types=1);

namespace Arqel\Widgets;

abstract class StatWidget extends Widget
{
    protected function type(): string { return 'stat'; }
    protected function component(): string { return 'StatWidget'; }

    abstract protected function stat(): int|string|float;
    protected function description(): ?string { return null; }
    protected function descriptionIcon(): ?string { return null; }
    protected function color(): string { return 'primary'; } // primary, success, warning, danger, info
    protected function icon(): ?string { return null; }
    protected function chart(): ?array { return null; } // Sparkline data
    protected function url(): ?string { return null; } // Link when clicked
    
    public function data(): array
    {
        return [
            'stat' => $this->stat(),
            'description' => $this->description(),
            'descriptionIcon' => $this->descriptionIcon(),
            'color' => $this->color(),
            'icon' => $this->icon(),
            'chart' => $this->chart(),
            'url' => $this->url(),
        ];
    }
}
```

Exemplo de uso em app user:

```php
final class TotalUsersStat extends StatWidget
{
    protected ?string $heading = 'Total Users';

    protected function stat(): int
    {
        return User::count();
    }

    protected function description(): ?string
    {
        $diff = $this->percentChangeVsLastWeek();
        return $diff > 0 ? "+{$diff}% vs last week" : "{$diff}% vs last week";
    }

    protected function descriptionIcon(): string
    {
        return $this->percentChangeVsLastWeek() > 0 ? 'trending-up' : 'trending-down';
    }

    protected function color(): string
    {
        return $this->percentChangeVsLastWeek() > 0 ? 'success' : 'danger';
    }

    protected function chart(): ?array
    {
        return User::selectRaw('DATE(created_at) as day, COUNT(*) as count')
            ->where('created_at', '>', now()->subDays(30))
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('count')
            ->toArray();
    }
}
```

**Critérios de aceite**

- [ ] StatWidget estende Widget corretamente
- [ ] data() retorna shape esperado
- [ ] Sparkline chart opcional serializa corretamente
- [ ] URL click destination funciona
- [ ] Color variants serializam: primary, success, warning, danger, info
- [ ] Teste: widget concrete com dados reais

**Notas de implementação**

- Sparkline é array simples `[10, 15, 12, 20, ...]` — React renderiza line chart mini.
- Formatação (currency, percent) fica com user via `stat()` return.

---

### [WIDGETS-003] `ChartWidget` (Recharts-based)

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php + react • **Depende de:** [WIDGETS-001]

**Contexto**

Cobre RF-W-02 (Chart variant). Bar, line, area, pie, donut. Uses Recharts no client.

**Descrição técnica**

```php
<?php

declare(strict_types=1);

namespace Arqel\Widgets;

abstract class ChartWidget extends Widget
{
    protected string $chartType = 'line'; // line, bar, area, pie, donut, radar
    protected ?int $height = 300;
    protected bool $showLegend = true;
    protected bool $showGrid = true;
    
    protected function type(): string { return 'chart'; }
    protected function component(): string { return 'ChartWidget'; }

    abstract protected function chartData(): array;
    protected function chartOptions(): array { return []; }
    
    public function data(): array
    {
        return [
            'chartType' => $this->chartType,
            'chartData' => $this->chartData(),
            'chartOptions' => $this->chartOptions(),
            'height' => $this->height,
            'showLegend' => $this->showLegend,
            'showGrid' => $this->showGrid,
        ];
    }
}
```

Expected chart data shape:

```php
[
    'labels' => ['Jan', 'Feb', 'Mar'],
    'datasets' => [
        [
            'label' => 'New users',
            'data' => [10, 25, 18],
            'color' => 'primary',
        ],
        [
            'label' => 'Churned',
            'data' => [2, 5, 3],
            'color' => 'danger',
        ],
    ],
]
```

React side em `@arqel-dev/ui/widgets/ChartWidget.tsx`: polymorphic renderer baseado em `chartType` usando Recharts LineChart, BarChart, AreaChart, PieChart.

**Critérios de aceite**

- [ ] ChartWidget suporta 6 tipos: line, bar, area, pie, donut, radar
- [ ] Dados multi-series funcionam
- [ ] React renderiza corretamente cada tipo com Recharts
- [ ] Responsive (container-based sizing)
- [ ] Cores são mapeadas para CSS vars do tema
- [ ] Testes cobrem cada variant

**Notas de implementação**

- Recharts é maduro e tree-shakeable.
- Tema dark: cores devem adaptar automaticamente via CSS vars.

---

### [WIDGETS-004] `TableWidget` (mini-tabela em dashboard)

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php • **Depende de:** [WIDGETS-001, TABLE-002] (Fase 1)

**Contexto**

Cobre RF-W-02 (Table variant). Mini-tabela limitada (5-10 rows) para "Latest X" em dashboard.

**Descrição técnica**

```php
<?php

declare(strict_types=1);

namespace Arqel\Widgets;

use Arqel\Table\Table;

abstract class TableWidget extends Widget
{
    protected int $limit = 10;
    
    protected function type(): string { return 'table'; }
    protected function component(): string { return 'TableWidget'; }

    abstract protected function query(): \Illuminate\Database\Eloquent\Builder;
    abstract protected function columns(): array;
    
    public function data(): array
    {
        $records = $this->query()->limit($this->limit)->get();
        
        return [
            'columns' => array_map(fn ($c) => $c->toArray(), $this->columns()),
            'records' => $records->toArray(),
            'limit' => $this->limit,
            'seeAllUrl' => $this->seeAllUrl(),
        ];
    }
    
    protected function seeAllUrl(): ?string { return null; }
}
```

Exemplo:

```php
final class LatestUsersTable extends TableWidget
{
    protected ?string $heading = 'Latest Users';
    protected int $limit = 5;
    
    protected function query()
    {
        return User::query()->latest();
    }
    
    protected function columns(): array
    {
        return [
            Column::text('name'),
            Column::text('email'),
            Column::date('created_at')->label('Joined'),
        ];
    }
    
    protected function seeAllUrl(): ?string
    {
        return route('arqel.admin.users.index');
    }
}
```

**Critérios de aceite**

- [ ] TableWidget renderiza mini-tabela com columns especificadas
- [ ] `seeAllUrl` link aparece quando definido
- [ ] Limit respeitado
- [ ] Teste: query real com dados

---

### [WIDGETS-005] `CustomWidget` para componentes custom

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** S • **Camada:** php • **Depende de:** [WIDGETS-001]

**Contexto**

Cobre RF-W-02 (Custom). Escape hatch para widgets que não encaixam em Stat/Chart/Table.

**Descrição técnica**

```php
<?php

declare(strict_types=1);

namespace Arqel\Widgets;

abstract class CustomWidget extends Widget
{
    abstract protected function view(): string;
    
    protected function type(): string { return 'custom'; }
    
    protected function component(): string
    {
        return $this->view();
    }
    
    abstract public function data(): array;
}
```

Exemplo:

```php
final class OnboardingProgressWidget extends CustomWidget
{
    protected ?string $heading = 'Onboarding';

    protected function view(): string
    {
        return 'OnboardingProgressWidget'; // React component name
    }

    public function data(): array
    {
        $user = auth()->user();
        return [
            'steps' => [
                ['label' => 'Verify email', 'done' => $user->hasVerifiedEmail()],
                ['label' => 'Add team', 'done' => $user->teams()->exists()],
                ['label' => 'First Resource', 'done' => $user->resources()->exists()],
            ],
        ];
    }
}
```

User registra componente React correspondente em registry (similar a custom Fields):

```typescript
// resources/js/app.tsx
import { registerWidget } from '@arqel-dev/widgets'
import { OnboardingProgressWidget } from './widgets/OnboardingProgressWidget'

registerWidget('OnboardingProgressWidget', OnboardingProgressWidget)
```

**Critérios de aceite**

- [ ] CustomWidget serializa com component name
- [ ] React registry resolve custom components
- [ ] Teste: custom widget renderizado corretamente

---

### [WIDGETS-006] Dashboard class + registry

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [WIDGETS-002]

**Contexto**

Dashboard agrupa widgets + aplica layout. Cobre RF-W-03 (Grid).

**Descrição técnica**

```php
<?php

declare(strict_types=1);

namespace Arqel\Widgets;

final class Dashboard
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly ?string $path = null,
    ) {}

    protected array $widgets = [];
    protected array $filters = [];
    protected int|array $columns = 3; // Responsive: ['sm' => 1, 'md' => 2, 'lg' => 3, 'xl' => 4]
    
    public function widgets(array $widgets): static;
    public function filters(array $filters): static;
    public function columns(int|array $cols): static;
    
    public function resolve(?Authenticatable $user): array
    {
        $widgets = collect($this->widgets)
            ->map(fn ($widget) => is_string($widget) ? app($widget) : $widget)
            ->filter(fn ($w) => $w->canBeSeenBy($user))
            ->sortBy(fn ($w) => $w->getSort())
            ->map(fn ($w) => $w->toArray($user))
            ->values()
            ->all();

        return [
            'id' => $this->id,
            'label' => $this->label,
            'widgets' => $widgets,
            'filters' => $this->filters,
            'columns' => $this->columns,
        ];
    }
}
```

Registrar em Panel:

```php
Arqel::panel('admin')
    ->dashboards([
        Dashboard::make('main', 'Main Dashboard')
            ->widgets([
                TotalUsersStat::class,
                RevenueStat::class,
                UsersGrowthChart::class,
                LatestUsersTable::class,
            ])
            ->columns(['sm' => 1, 'md' => 2, 'lg' => 4]),
        
        Dashboard::make('analytics', 'Analytics')
            ->widgets([/* ... */])
            ->path('/admin/analytics'),
    ]);
```

**Critérios de aceite**

- [ ] Dashboard::make() factory
- [ ] Widgets registrados como classes OR instâncias
- [ ] Responsive columns serializam corretamente
- [ ] Widgets filtered por canSee
- [ ] Multiple dashboards por panel
- [ ] Teste: resolve retorna shape correto

---

### [WIDGETS-007] `DashboardController` + rotas

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [WIDGETS-006]

**Descrição técnica**

`src/Http/Controllers/DashboardController.php`:

```php
final class DashboardController
{
    public function show(Request $request, string $dashboardId): Response
    {
        $dashboard = $this->registry->find($dashboardId);
        abort_if(!$dashboard, 404);
        
        return Inertia::render('arqel::dashboard', [
            'dashboard' => $dashboard->resolve($request->user()),
            'filterValues' => $request->input('filters', []),
        ]);
    }
}
```

Rotas:

- `GET /admin` → DashboardController@show('main')
- `GET /admin/dashboards/{id}` → DashboardController@show($id)

**Critérios de aceite**

- [ ] `/admin` renderiza dashboard main
- [ ] Múltiplos dashboards acessíveis por URL
- [ ] Authorization check via widget.canSee
- [ ] Test Feature: render dashboard, filter values preservados

---

### [WIDGETS-008] `WidgetDataController` para deferred + polling

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [WIDGETS-007]

**Contexto**

Cobre RF-W-04 (Polling) e RF-W-05 (Deferred). Widgets pesados devem carregar lazy.

**Descrição técnica**

`src/Http/Controllers/WidgetDataController.php`:

```php
final class WidgetDataController
{
    public function show(Request $request, string $dashboardId, string $widgetId): JsonResponse
    {
        $dashboard = $this->registry->find($dashboardId);
        abort_if(!$dashboard, 404);
        
        $widget = $dashboard->findWidget($widgetId);
        abort_if(!$widget || !$widget->canBeSeenBy($request->user()), 403);
        
        // Apply filters from request
        if ($filters = $request->input('filters')) {
            $widget->applyFilters($filters);
        }
        
        return response()->json([
            'data' => $widget->data(),
        ]);
    }
}
```

Rota:

- `GET /admin/dashboards/{dashboardId}/widgets/{widgetId}/data`

Client-side (React) implementa:

- Deferred: inicial render com skeleton, então fetch data via Inertia `defer`
- Polling: setInterval baseado em `pollingInterval` serializado

**Critérios de aceite**

- [ ] Endpoint retorna widget data isolado
- [ ] Deferred widgets carregam após initial render
- [ ] Polling faz refetch na frequência correta
- [ ] Filter changes propagate
- [ ] Teste Feature: fetch widget data

**Notas de implementação**

- Preferir Inertia deferred props ao invés de fetch direto quando possível — mantém consistência.
- Polling só em dashboard ativo (pause quando tab inactive via Page Visibility API no React).

---

### [WIDGETS-009] Dashboard-level filters

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php + react • **Depende de:** [WIDGETS-006]

**Contexto**

Cobre RF-W-06. Um filtro (ex: date range) aplicado a todos os widgets do dashboard.

**Descrição técnica**

Dashboard filters similar a Table filters mas scope aplicado a widget data queries:

```php
Dashboard::make('analytics', 'Analytics')
    ->filters([
        Filter::dateRange('period')
            ->default(['from' => now()->subDays(30), 'to' => now()]),
        Filter::select('segment')
            ->options(['all' => 'All', 'active' => 'Active', 'trial' => 'Trial']),
    ])
    ->widgets([...]);
```

Widgets recebem filters applied via method:

```php
abstract class Widget
{
    protected array $currentFilters = [];
    
    public function applyFilters(array $filters): void
    {
        $this->currentFilters = $filters;
    }
    
    protected function filterValue(string $name, mixed $default = null): mixed
    {
        return $this->currentFilters[$name] ?? $default;
    }
}
```

Widget data method usa filters:

```php
final class RevenueChart extends ChartWidget
{
    protected function chartData(): array
    {
        $period = $this->filterValue('period', [
            'from' => now()->subDays(30),
            'to' => now(),
        ]);
        
        return [
            'labels' => $this->getLabels($period),
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => Order::whereBetween('created_at', [$period['from'], $period['to']])
                        ->selectRaw('DATE(created_at) as day, SUM(total) as revenue')
                        ->groupBy('day')
                        ->pluck('revenue')
                        ->toArray(),
                ],
            ],
        ];
    }
}
```

**Critérios de aceite**

- [ ] Dashboard filters renderizados em toolbar
- [ ] Mudança de filter dispara refresh de widgets
- [ ] Widgets aceitam filters via applyFilters()
- [ ] URL sync (filter state em query string)
- [ ] Teste: filter change refetcha widgets

---

### [WIDGETS-010] React components — StatWidget, ChartWidget, TableWidget

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** XL • **Camada:** react • **Depende de:** [UI-002] (Fase 1)

**Contexto**

Renderização React de cada widget type.

**Descrição técnica**

Em `@arqel-dev/ui/widgets/`:

- `StatCard.tsx`: número grande + description + icon + optional sparkline
- `ChartCard.tsx`: wrapper que renderiza LineChart, BarChart, AreaChart, PieChart, DonutChart via Recharts polymorphic
- `TableCard.tsx`: mini DataTable com columns + "See all" link
- `WidgetWrapper.tsx`: common chrome (heading, description, loading state, error boundary)

`@arqel-dev/widgets` npm package (separate from PHP arqel-dev/widgets):

- Registry de custom widgets
- `registerWidget(name, component)`
- `getWidgetComponent(name)`

**Critérios de aceite**

- [ ] 4 React components implementados
- [ ] StatCard renderiza sparkline opcional
- [ ] ChartCard renderiza 6 tipos de chart
- [ ] TableCard renderiza columns + see-all link
- [ ] Loading skeletons para deferred widgets
- [ ] Error boundary catch crashes de user-provided widgets
- [ ] A11y: aria-label em cards, keyboard nav
- [ ] Testes com Testing Library

**Notas de implementação**

- Recharts é 60KB gzipped — consider lazy loading via dynamic import.
- Skeleton during load previne CLS.

---

### [WIDGETS-011] DashboardGrid component + layout

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** react • **Depende de:** [WIDGETS-010]

**Descrição técnica**

`@arqel-dev/ui/dashboard/DashboardGrid.tsx`:

```tsx
import { ResponsiveGrid } from './ResponsiveGrid'
import { WidgetRenderer } from './WidgetRenderer'
import { DashboardFilters } from './DashboardFilters'

export function DashboardGrid({ dashboard, filterValues }: DashboardGridProps) {
    return (
        <div className="space-y-4">
            <header className="flex items-center justify-between">
                <h1>{dashboard.label}</h1>
                <DashboardFilters filters={dashboard.filters} values={filterValues} />
            </header>
            
            <ResponsiveGrid columns={dashboard.columns}>
                {dashboard.widgets.map(widget => (
                    <div key={widget.id} style={{ gridColumn: `span ${widget.columnSpan}` }}>
                        <WidgetRenderer widget={widget} filterValues={filterValues} />
                    </div>
                ))}
            </ResponsiveGrid>
        </div>
    )
}
```

`WidgetRenderer.tsx`: resolve component via `getWidgetComponent(widget.component)`, aplica polling, handles deferred loading.

**Critérios de aceite**

- [ ] Grid responsivo com breakpoints configuráveis
- [ ] Widgets renderizam polymorphic
- [ ] Deferred widgets mostram skeleton até data chegar
- [ ] Polling funciona (setInterval cleanup em unmount)
- [ ] Filter changes trigger refetch
- [ ] Testes E2E: dashboard com 10 widgets heterogêneos

---

### [WIDGETS-012] Schedule widget (FullCalendar wrapper) — stub para Fase 2

**Tipo:** feat • **Prioridade:** P2 • **Estimativa:** L • **Camada:** react • **Depende de:** [WIDGETS-010]

**Contexto**

Cobre RF-W-08. Schedule/calendar widget é comum em admin panels mas pesado.

**Descrição técnica**

- `@arqel-dev/widgets/schedule` subpackage (opt-in via npm install)
- Wrapper Base UI ou FullCalendar React binding
- ScheduleWidget PHP class que serializa eventos (start, end, title, color)
- Interactions: click event, click date, drag-drop events

**Critérios de aceite**

- [ ] ScheduleWidget PHP + React implementados
- [ ] Events renderizados corretamente
- [ ] Click handlers funcionam via Inertia
- [ ] Teste: render 50 events

**Notas de implementação**

- FullCalendar v6+ tem React adapter oficial.
- Lazy load — schedule é heavy (~200KB).

---

### [WIDGETS-013] Artisan commands: `arqel:widget` + `arqel:dashboard`

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php • **Depende de:** [WIDGETS-006]

**Descrição técnica**

Comandos:

- `php artisan arqel:widget TotalUsers` — gera StatWidget stub
- `php artisan arqel:widget RevenueChart --type=chart` — gera ChartWidget stub
- `php artisan arqel:widget LatestOrders --type=table` — gera TableWidget stub
- `php artisan arqel:widget OnboardingProgress --type=custom` — gera CustomWidget + React stub
- `php artisan arqel:dashboard Analytics` — gera Dashboard scaffolding + registro

Stubs em `packages/widgets/stubs/`.

**Critérios de aceite**

- [ ] 4 variants de widget scaffoldadas
- [ ] Dashboard gera estrutura + registro em ServiceProvider hint
- [ ] `--force` flag
- [ ] Teste: gerar + executar sem editar manualmente

---

### [WIDGETS-014] Testes completos do pacote WIDGETS

**Tipo:** test • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [WIDGETS-013]

**Descrição técnica**

- Unit tests: cada widget type, Dashboard, WidgetRegistry
- Feature tests: DashboardController, WidgetDataController, filter flow
- Coverage ≥ 90%
- E2E test: dashboard com 5+ widgets diversos

**Critérios de aceite**

- [ ] Pest passa
- [ ] Coverage ≥ 90%
- [ ] E2E em playground funciona

---

### [WIDGETS-015] SKILL.md do pacote widgets

**Tipo:** docs • **Prioridade:** P1 • **Estimativa:** S • **Camada:** docs • **Depende de:** [WIDGETS-014]

**Descrição técnica**

SKILL.md com exemplos dos 4 widget types, dashboard composition, filters, polling, deferred loading. Anti-patterns: heavy queries sem cache, polling agressivo em widgets pesados, N+1 queries em data().

**Critérios de aceite**

- [ ] SKILL.md completo

---

## 4. Advanced fields (FIELDS-ADV)

### [FIELDS-ADV-001] Setup do sub-pacote `arqel-dev/fields-advanced`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [FIELDS-001] (Fase 1)

**Contexto**

Cobre RF-F-10. 8 field types avançados separados em pacote opt-in para evitar bloat.

**Descrição técnica**

Estrutura `packages/fields-advanced/`:

- `composer.json` (deps: `arqel-dev/fields`)
- `src/Types/RichTextField.php`
- `src/Types/MarkdownField.php`
- `src/Types/CodeField.php`
- `src/Types/RepeaterField.php`
- `src/Types/BuilderField.php`
- `src/Types/KeyValueField.php`
- `src/Types/TagsField.php`
- `src/Types/WizardField.php` (wizard layout + field combo)
- Register macros em `FieldsAdvancedServiceProvider`
- SKILL.md, tests/

npm equivalent: `@arqel-dev/fields-advanced` com os React components correspondentes.

**Critérios de aceite**

- [ ] Pacote resolve via path
- [ ] ServiceProvider registra macros: `Field::richText()`, `Field::markdown()`, etc.
- [ ] SKILL.md esqueleto

---

### [FIELDS-ADV-002] `RichTextField` (Tiptap integration)

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** XL • **Camada:** php + react • **Depende de:** [FIELDS-ADV-001]

**Contexto**

RichText é feature requested frequentemente. Tiptap é state-of-the-art em 2026 para React rich text.

**Descrição técnica**

PHP side:

```php
<?php

declare(strict_types=1);

namespace Arqel\Fields\Advanced\Types;

use Arqel\Fields\Field;

final class RichTextField extends Field
{
    protected string $type = 'richText';
    protected string $component = 'RichTextInput';
    
    protected array $toolbar = ['bold', 'italic', 'link', 'bulletList', 'orderedList', 'heading', 'blockquote'];
    protected ?string $imageUploadDisk = null;
    protected ?string $imageUploadDirectory = null;
    protected int $maxLength = 65535;
    protected bool $fileAttachments = false;
    protected ?array $customMarks = null;
    
    public function toolbar(array $buttons): static;
    public function imageUploadDisk(string $disk): static;
    public function imageUploadDirectory(string $dir): static;
    public function maxLength(int $max): static;
    public function fileAttachments(bool $enable = true): static;
    public function customMarks(array $marks): static;
    public function mentionable(array $users): static; // @mentions
    
    public function getTypeSpecificProps(): array
    {
        return [
            'toolbar' => $this->toolbar,
            'imageUploadRoute' => $this->imageUploadDisk ? $this->buildImageUploadRoute() : null,
            'maxLength' => $this->maxLength,
            'fileAttachments' => $this->fileAttachments,
        ];
    }
}
```

Validação:
- Output sanitizado server-side via HTML Purifier ou similar (RNF-S-06)
- Max content size check

React side em `@arqel-dev/fields-advanced/RichTextInput.tsx`:

- Tiptap v2+ editor
- Toolbar dinâmica baseada em field config
- Image upload via presigned URL ou direct (mirroring FileField)
- Dark mode aware (CSS vars)
- Keyboard shortcuts (Cmd+B, Cmd+I, etc.)
- Paste handling (HTML cleaned)
- A11y: focus management, aria labels

**Critérios de aceite**

- [ ] Field serializa com toolbar config
- [ ] HTML Purifier sanitiza output server-side
- [ ] Tiptap editor renderiza com toolbar dinâmica
- [ ] Image upload funciona inline
- [ ] Paste HTML is cleaned
- [ ] Keyboard shortcuts funcionam
- [ ] Dark mode
- [ ] A11y: focus, aria
- [ ] XSS prevention test: payload comum é sanitized
- [ ] Teste E2E: compose complete post with images

**Notas de implementação**

- Tiptap 2.x é React 18+ — verificar compat React 19.2 (deve ser fine).
- HTML Purifier (PHP) ou bleach-like sanitizer — deps crítica de segurança.
- Paste from Google Docs é common source of dirty HTML.

---

### [FIELDS-ADV-003] `MarkdownField` (editor + preview)

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php + react • **Depende de:** [FIELDS-ADV-002]

**Contexto**

Markdown é alternative leve a RichText. Popular em devtool admin panels.

**Descrição técnica**

PHP:

```php
final class MarkdownField extends Field
{
    protected string $type = 'markdown';
    protected string $component = 'MarkdownInput';
    
    protected bool $preview = true;
    protected string $previewMode = 'side-by-side'; // side-by-side, tab, popup
    protected bool $toolbar = true;
    protected ?int $rows = 10;
    protected bool $fullscreen = true;
    
    public function preview(bool $enable = true): static;
    public function previewMode(string $mode): static;
    public function toolbar(bool $enable = true): static;
    public function rows(int $rows): static;
    public function fullscreen(bool $enable = true): static;
}
```

React: CodeMirror 6 + preview via remark/rehype. Sync scroll entre editor e preview.

**Critérios de aceite**

- [ ] Editor renderiza com syntax highlighting markdown
- [ ] Preview renderiza HTML em tempo real
- [ ] Side-by-side, tab, popup modes funcionam
- [ ] Toolbar com buttons Bold, Italic, Link, Heading, List, Code
- [ ] Fullscreen mode
- [ ] Keyboard shortcuts padrão markdown
- [ ] A11y

**Notas de implementação**

- `@codemirror/lang-markdown` para highlighting.
- `remark` + `rehype-sanitize` para preview safe.

---

### [FIELDS-ADV-004] `CodeField` (Shiki syntax highlighting)

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php + react • **Depende de:** [FIELDS-ADV-001]

**Contexto**

Editor de código com syntax highlighting. Shiki é o state-of-the-art 2026 (same engine que VS Code).

**Descrição técnica**

PHP:

```php
final class CodeField extends Field
{
    protected string $type = 'code';
    protected string $component = 'CodeInput';
    
    protected string $language = 'plaintext';
    protected ?string $theme = null; // Inherits from dark/light toggle
    protected bool $lineNumbers = true;
    protected bool $wordWrap = false;
    protected int $tabSize = 2;
    protected bool $readonly = false;
    protected ?int $minHeight = null;
    
    public function language(string $lang): static;
    public function theme(?string $theme): static;
    public function lineNumbers(bool $show = true): static;
    public function wordWrap(bool $wrap = true): static;
    public function tabSize(int $size): static;
    public function minHeight(int $px): static;
}
```

React: CodeMirror 6 com Shiki theme highlighter. Support languages comuns: js, ts, tsx, jsx, php, python, ruby, go, rust, sql, json, yaml, html, css, markdown, bash.

**Critérios de aceite**

- [ ] Code editor com syntax highlighting
- [ ] Language switching
- [ ] Theme matches dark/light mode
- [ ] Line numbers toggle
- [ ] Word wrap toggle
- [ ] Tab size custom
- [ ] Lazy load languages (importar só o necessário)
- [ ] Teste E2E: edit JS code, verify highlighting

**Notas de implementação**

- Shiki é accurate mas pesado. Carregar languages on-demand.
- Alternativa mais leve: Prism.js — menos bonito mas 10x menor.

---

### [FIELDS-ADV-005] `RepeaterField`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** XL • **Camada:** php + react • **Depende de:** [FIELDS-ADV-001, FORM-002] (Fase 1)

**Contexto**

Cobre RF-FM-07. Fields repetíveis em grupo (ex: múltiplos endereços, múltiplos contacts). Bind a Eloquent HasMany relationship.

**Descrição técnica**

PHP:

```php
final class RepeaterField extends Field
{
    protected string $type = 'repeater';
    protected string $component = 'RepeaterInput';
    
    protected array $schema = []; // Fields inside each repeat
    protected ?int $minItems = null;
    protected ?int $maxItems = null;
    protected bool $reorderable = true;
    protected bool $collapsible = false;
    protected bool $cloneable = true;
    protected ?string $itemLabel = null; // Template with {{fieldname}}
    protected ?string $relationship = null; // Eloquent HasMany name
    
    public function schema(array $fields): static;
    public function minItems(int $min): static;
    public function maxItems(int $max): static;
    public function reorderable(bool $enable = true): static;
    public function collapsible(bool $enable = true): static;
    public function cloneable(bool $enable = true): static;
    public function itemLabel(string $template): static;
    public function relationship(string $name): static;
    
    // Handle dehydration of nested data
    public function dehydrateState(mixed $value): array;
}
```

Hydration quando record tem HasMany:

```php
protected function hydrateFromRecord(Model $record): array
{
    if (!$this->relationship) return [];
    return $record->{$this->relationship}->map(fn ($item) => $item->toArray())->all();
}
```

Persistence:

```php
// In Resource afterCreate/afterUpdate
public function afterCreate(Model $record): void
{
    foreach ($repeaterData as $item) {
        $record->{$relationship}()->create($item);
    }
}
```

React side: `RepeaterInput.tsx` renderiza FormRenderer para cada item, com controls Add/Remove/Move.

**Critérios de aceite**

- [ ] Schema renderiza cada item como mini-form
- [ ] Add item expande array
- [ ] Remove item colapsa
- [ ] Drag-drop reorder (via dnd-kit ou similar)
- [ ] Min/max enforcement client + server
- [ ] Relationship binding: load from HasMany, save to HasMany
- [ ] Collapsed items mostram itemLabel template
- [ ] Teste E2E: create Resource with repeater, edit, delete items

**Notas de implementação**

- Dnd-kit é a lib moderna para drag-drop React 19.
- Performance: memoizar individual item render, evitar re-renders em massa.
- Nested repeaters são possíveis mas complexos — documentar trade-offs.

---

### [FIELDS-ADV-006] `BuilderField` (CMS-style blocks)

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** XL • **Camada:** php + react • **Depende de:** [FIELDS-ADV-005]

**Contexto**

Cobre RF-FM-08. Builder é Repeater com blocos heterogêneos (ex: block Text, block Image, block Video). Common em CMS.

**Descrição técnica**

PHP:

```php
final class BuilderField extends Field
{
    protected string $type = 'builder';
    protected string $component = 'BuilderInput';
    
    protected array $blocks = []; // ['text' => TextBlock::class, 'image' => ImageBlock::class]
    protected ?int $minItems = null;
    protected ?int $maxItems = null;
    protected bool $reorderable = true;
    protected bool $collapsible = true;
    
    public function blocks(array $blocks): static;
    public function minItems(int $min): static;
    public function maxItems(int $max): static;
    // ...
}
```

Cada Block é classe:

```php
abstract class Block
{
    abstract public static function type(): string;
    abstract public static function label(): string;
    abstract public static function icon(): ?string;
    abstract public function schema(): array; // Fields
}

class TextBlock extends Block
{
    public static function type(): string { return 'text'; }
    public static function label(): string { return 'Text'; }
    public static function icon(): ?string { return 'type'; }
    
    public function schema(): array
    {
        return [
            Field::richText('content'),
        ];
    }
}
```

Output é array:

```json
[
    { "type": "text", "data": { "content": "<p>Hello</p>" } },
    { "type": "image", "data": { "src": "...", "alt": "..." } }
]
```

**Critérios de aceite**

- [ ] Multiple block types registrados
- [ ] User pode adicionar bloco via menu (tipo → schema)
- [ ] Blocks reordenáveis via drag-drop
- [ ] Each block renderizado com seu schema
- [ ] Output preservado em JSON column
- [ ] Teste E2E: build page com 5 blocos heterogêneos

**Notas de implementação**

- Persist em JSON column (Eloquent cast 'array').
- Renderization pública (frontend do site) fica com user — fornecer helper para iterate blocks.

---

### [FIELDS-ADV-007] `KeyValueField`

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php + react • **Depende de:** [FIELDS-ADV-001]

**Contexto**

Cobre RF-F-10 (KeyValue). Útil para metadados, config flags, headers HTTP.

**Descrição técnica**

PHP:

```php
final class KeyValueField extends Field
{
    protected string $type = 'keyValue';
    protected string $component = 'KeyValueInput';
    
    protected string $keyLabel = 'Key';
    protected string $valueLabel = 'Value';
    protected string $keyPlaceholder = '';
    protected string $valuePlaceholder = '';
    protected bool $editableKeys = true;
    protected bool $addable = true;
    protected bool $deletable = true;
    protected bool $reorderable = false;
    
    public function keyLabel(string $label): static;
    public function valueLabel(string $label): static;
    public function keyPlaceholder(string $placeholder): static;
    public function valuePlaceholder(string $placeholder): static;
    public function editableKeys(bool $enable = true): static;
    public function addable(bool $enable = true): static;
    public function deletable(bool $enable = true): static;
    public function reorderable(bool $enable = true): static;
}
```

Output: `[{ "key": "lang", "value": "en" }, ...]` ou `{ "lang": "en", ... }` — user escolhe via `asObject(bool)`.

React: table with 2 columns (key + value) + Add row + delete per row. Drag-drop se reorderable.

**Critérios de aceite**

- [ ] Add/remove rows
- [ ] Editable keys toggle
- [ ] Placeholders customizáveis
- [ ] Output format configurável (array of objects OR assoc array)
- [ ] Cast Eloquent 'array' funciona
- [ ] Teste: CRUD key-value data

---

### [FIELDS-ADV-008] `TagsField`

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php + react • **Depende de:** [FIELDS-ADV-001]

**Contexto**

Cobre RF-F-10 (Tags). Popular para categorização flexível.

**Descrição técnica**

PHP:

```php
final class TagsField extends Field
{
    protected string $type = 'tags';
    protected string $component = 'TagsInput';
    
    protected array|Closure $suggestions = [];
    protected bool $creatable = true;
    protected ?int $maxTags = null;
    protected string $separator = ','; // Char to split when paste
    protected bool $unique = true;
    
    public function suggestions(array|Closure $tags): static;
    public function creatable(bool $enable = true): static;
    public function maxTags(int $max): static;
    public function separator(string $sep): static;
    public function unique(bool $enable = true): static;
    public function fromRelationship(string $relation, string $labelColumn): static; // Eloquent tags
}
```

Integration opcional com Spatie Tags package:

```php
Field::tags('tags')
    ->fromRelationship('tags', 'name'); // Uses Spatie\Tags\HasTags
```

React: combobox with chips for each tag. Backspace removes last, Enter adds.

**Critérios de aceite**

- [ ] Tags added via Enter
- [ ] Paste com separator adds multiple
- [ ] Backspace removes last
- [ ] Suggestions dropdown ao type
- [ ] Creatable flag
- [ ] Max tags enforced
- [ ] Unique dedupe
- [ ] Spatie Tags integration opcional

---

### [FIELDS-ADV-009] `WizardField` (multi-step form)

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** XL • **Camada:** php + react • **Depende de:** [FIELDS-ADV-001, FORM-005] (Fase 1)

**Contexto**

Cobre RF-FM-04. Multi-step form com state preservation via React 19.2 `<Activity>`.

**Descrição técnica**

Wizard é layout component mais que field individual. Coloca-se em Form schema:

```php
$form->schema([
    Wizard::make()
        ->steps([
            Step::make('details')
                ->label('Details')
                ->icon('info')
                ->schema([
                    Field::text('name')->required(),
                    Field::email('email')->required(),
                ]),
            Step::make('password')
                ->label('Password')
                ->schema([
                    Field::password('password')->required(),
                    Field::password('password_confirmation')->required(),
                ]),
            Step::make('preferences')
                ->label('Preferences')
                ->schema([
                    Field::toggle('notifications')->default(true),
                ]),
        ])
        ->startOnStep('details')
        ->showStepProgress()
        ->submitLabel('Create User'),
]);
```

Validação per-step antes de advance. Back/Next buttons. Progress indicator.

React: renders one step at a time. Uses `<Activity mode="visible|hidden">` (React 19.2) para preservar state de steps anteriores sem re-mount.

**Critérios de aceite**

- [ ] Wizard renderiza step-by-step
- [ ] Next avança após step validation
- [ ] Back preserva data
- [ ] Progress indicator mostra current step
- [ ] State preservation via `<Activity>` (React 19.2)
- [ ] Submit no último step envia full data
- [ ] Teste E2E: multi-step wizard completion

**Notas de implementação**

- `<Activity>` é feature React 19.2. Fallback para 19.0 usa CSS display:none (loses state em inputs não-controlled).
- Server-side validation per-step via partial Inertia reload para validar só step corrente.

---

### [FIELDS-ADV-010] React component `RichTextInput` (Tiptap)

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** XL • **Camada:** react • **Depende de:** [FIELDS-ADV-002]

**Contexto**

Componente React correspondente ao `RichTextField` PHP. Editor WYSIWYG feature-rich baseado em Tiptap v2+.

**Descrição técnica**

Criar `@arqel-dev/fields-advanced/src/rich-text/RichTextInput.tsx`:

```tsx
import { useEditor, EditorContent } from '@tiptap/react'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Image from '@tiptap/extension-image'
import Placeholder from '@tiptap/extension-placeholder'
import { Toolbar } from './Toolbar'
import type { FieldComponentProps } from '@arqel-dev/types'

interface RichTextProps extends FieldComponentProps<string> {
    field: FieldSchema & {
        props: {
            toolbar: string[]
            imageUploadRoute?: string
            maxLength: number
            fileAttachments: boolean
        }
    }
}

export function RichTextInput({ field, value, onChange, error, disabled }: RichTextProps) {
    const editor = useEditor({
        extensions: [
            StarterKit,
            Link.configure({ openOnClick: false }),
            Image.configure({ inline: false }),
            Placeholder.configure({ placeholder: field.placeholder ?? 'Start writing...' }),
        ],
        content: value ?? '',
        editable: !disabled,
        onUpdate: ({ editor }) => onChange(editor.getHTML()),
    })

    return (
        <div className="rich-text-editor" data-error={!!error}>
            <Toolbar editor={editor} config={field.props.toolbar} />
            <EditorContent editor={editor} className="prose prose-sm max-w-none" />
        </div>
    )
}
```

Toolbar dinâmica baseada em `field.props.toolbar` config. Cada button mapeia para Tiptap command.

Image upload: drag-drop OR paste OR toolbar button. Upload via `field.props.imageUploadRoute` endpoint, insere URL no editor.

Paste handling: sanitize HTML pasted (strip Google Docs styles, etc.).

**Critérios de aceite**

- [ ] Editor renderiza com toolbar configurável
- [ ] Bold, italic, link, lists, heading, blockquote funcionam
- [ ] Image upload drag-drop + paste + button
- [ ] Paste de Google Docs é limpo
- [ ] Keyboard shortcuts: Cmd+B, Cmd+I, Cmd+K (link)
- [ ] Dark mode via CSS vars
- [ ] A11y: focus management, aria-invalid em erro, toolbar com role="toolbar"
- [ ] Lazy load: chunk separado via dynamic import
- [ ] Max length enforced (warning quando close, block quando over)
- [ ] Testes Testing Library cobrem cada toolbar action

**Notas de implementação**

- Tiptap v2 tem React 19 support em versions recentes — verificar compat.
- Bundle size: ~120KB gzipped. Lazy load mandatório.
- Mentions (@users) é feature Fase 3.

---

### [FIELDS-ADV-011] React component `MarkdownInput` (CodeMirror + preview)

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** react • **Depende de:** [FIELDS-ADV-003]

**Contexto**

Componente React correspondente ao `MarkdownField` PHP. Editor side-by-side com preview renderizado.

**Descrição técnica**

Criar `@arqel-dev/fields-advanced/src/markdown/MarkdownInput.tsx`:

```tsx
import { useState, useMemo } from 'react'
import CodeMirror from '@uiw/react-codemirror'
import { markdown, markdownLanguage } from '@codemirror/lang-markdown'
import { unified } from 'unified'
import remarkParse from 'remark-parse'
import remarkRehype from 'remark-rehype'
import rehypeSanitize from 'rehype-sanitize'
import rehypeStringify from 'rehype-stringify'
import { MarkdownToolbar } from './MarkdownToolbar'

export function MarkdownInput({ field, value, onChange, disabled }: FieldComponentProps<string>) {
    const [mode, setMode] = useState<'edit' | 'preview' | 'split'>(field.props.previewMode ?? 'split')
    const [fullscreen, setFullscreen] = useState(false)

    const previewHtml = useMemo(() => {
        if (!value) return ''
        return unified()
            .use(remarkParse)
            .use(remarkRehype)
            .use(rehypeSanitize)
            .use(rehypeStringify)
            .processSync(value)
            .toString()
    }, [value])

    return (
        <div className={cn('markdown-editor', fullscreen && 'fullscreen')}>
            <MarkdownToolbar
                onAction={(action) => applyMarkdownAction(action, value, onChange)}
                mode={mode}
                onModeChange={setMode}
                onToggleFullscreen={() => setFullscreen(f => !f)}
            />
            <div className={cn('editor-panels', `mode-${mode}`)}>
                {(mode === 'edit' || mode === 'split') && (
                    <CodeMirror
                        value={value ?? ''}
                        onChange={onChange}
                        extensions={[markdown({ base: markdownLanguage })]}
                        editable={!disabled}
                        minHeight={`${field.props.rows * 24}px`}
                    />
                )}
                {(mode === 'preview' || mode === 'split') && (
                    <div
                        className="prose prose-sm max-w-none p-4"
                        dangerouslySetInnerHTML={{ __html: previewHtml }}
                    />
                )}
            </div>
        </div>
    )
}
```

Toolbar com buttons: Bold, Italic, Heading, Link, List, Code, Quote, Image. Cada button insere markdown syntax na posição do cursor.

Sync scroll entre editor e preview (optional, nice-to-have).

**Critérios de aceite**

- [ ] Editor com syntax highlighting markdown via CodeMirror
- [ ] Preview renderiza HTML em tempo real (debounced 200ms)
- [ ] 3 modes: edit, preview, split
- [ ] Toolbar com 8 actions funcionais
- [ ] Fullscreen toggle
- [ ] Preview sanitized (XSS prevention via rehype-sanitize)
- [ ] Keyboard shortcuts markdown padrão (Cmd+B = `**`, etc.)
- [ ] Dark mode
- [ ] A11y: toolbar com role, live region para preview
- [ ] Lazy load
- [ ] Testes cobrem mode switching e toolbar actions

**Notas de implementação**

- `@uiw/react-codemirror` é wrapper React moderno do CodeMirror 6.
- Bundle: ~80KB gzipped.
- Sync scroll pode ser tricky com different line heights — skip em v1.

---

### [FIELDS-ADV-012] React component `CodeInput` (CodeMirror + Shiki)

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** react • **Depende de:** [FIELDS-ADV-004]

**Contexto**

Componente React correspondente ao `CodeField` PHP. Editor de código com syntax highlighting via Shiki (same engine que VS Code).

**Descrição técnica**

Criar `@arqel-dev/fields-advanced/src/code/CodeInput.tsx`:

```tsx
import CodeMirror, { Extension } from '@uiw/react-codemirror'
import { useEffect, useState } from 'react'
import { createHighlighter, Highlighter } from 'shiki'
import { shikiToCodeMirror } from './shiki-cm-bridge'

const LANGUAGE_IMPORTS: Record<string, () => Promise<Extension>> = {
    javascript: () => import('@codemirror/lang-javascript').then(m => m.javascript()),
    typescript: () => import('@codemirror/lang-javascript').then(m => m.javascript({ typescript: true })),
    jsx: () => import('@codemirror/lang-javascript').then(m => m.javascript({ jsx: true })),
    tsx: () => import('@codemirror/lang-javascript').then(m => m.javascript({ typescript: true, jsx: true })),
    php: () => import('@codemirror/lang-php').then(m => m.php()),
    python: () => import('@codemirror/lang-python').then(m => m.python()),
    sql: () => import('@codemirror/lang-sql').then(m => m.sql()),
    json: () => import('@codemirror/lang-json').then(m => m.json()),
    yaml: () => import('@codemirror/lang-yaml').then(m => m.yaml()),
    html: () => import('@codemirror/lang-html').then(m => m.html()),
    css: () => import('@codemirror/lang-css').then(m => m.css()),
    markdown: () => import('@codemirror/lang-markdown').then(m => m.markdown()),
}

export function CodeInput({ field, value, onChange, disabled }: FieldComponentProps<string>) {
    const [languageExtension, setLanguageExtension] = useState<Extension | null>(null)
    const { isDark } = useTheme()

    useEffect(() => {
        const loader = LANGUAGE_IMPORTS[field.props.language]
        if (loader) {
            loader().then(setLanguageExtension)
        }
    }, [field.props.language])

    return (
        <div className="code-editor" data-language={field.props.language}>
            <CodeMirror
                value={value ?? ''}
                onChange={onChange}
                theme={isDark ? 'dark' : 'light'}
                extensions={languageExtension ? [languageExtension] : []}
                editable={!disabled && !field.props.readonly}
                basicSetup={{
                    lineNumbers: field.props.lineNumbers,
                    tabSize: field.props.tabSize,
                    lineWrapping: field.props.wordWrap,
                }}
                minHeight={field.props.minHeight ? `${field.props.minHeight}px` : undefined}
            />
        </div>
    )
}
```

Languages suportadas: js, ts, tsx, jsx, php, python, ruby, go, rust, sql, json, yaml, html, css, markdown, bash.

Cada language é dynamic import — só carrega o necessário.

Themes: light/dark via useTheme hook, sincroniza com tema global Arqel.

**Critérios de aceite**

- [ ] Code editor com syntax highlighting correto
- [ ] Language dynamic loading
- [ ] Theme matches dark/light mode
- [ ] Line numbers toggle funciona
- [ ] Word wrap toggle funciona
- [ ] Tab size respeitado
- [ ] Readonly mode
- [ ] Placeholder quando vazio
- [ ] A11y: textarea semantics, labels
- [ ] Bundle: core editor <50KB, languages lazy
- [ ] Testes cobrem language switching

**Notas de implementação**

- Alternativa considerada: Monaco (VS Code engine). Rejeitada por bundle size (~2MB).
- Shiki integration via CodeMirror extension — mais leve que full Monaco.

---

### [FIELDS-ADV-013] React component `RepeaterInput`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** XL • **Camada:** react • **Depende de:** [FIELDS-ADV-005, UI-004] (Fase 1)

**Contexto**

Componente React correspondente ao `RepeaterField` PHP. Lista drag-drop de sub-forms repetidos.

**Descrição técnica**

Criar `@arqel-dev/fields-advanced/src/repeater/RepeaterInput.tsx`:

```tsx
import { DndContext, closestCenter, PointerSensor, useSensor, useSensors } from '@dnd-kit/core'
import { SortableContext, verticalListSortingStrategy, useSortable, arrayMove } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { FormRenderer } from '@arqel-dev/ui'
import { RepeaterItem } from './RepeaterItem'
import { Button } from '@/components/ui/button'
import { Plus } from 'lucide-react'

export function RepeaterInput({ field, value, onChange, error }: FieldComponentProps<unknown[]>) {
    const items = Array.isArray(value) ? value : []
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 5 } }))

    const handleDragEnd = (event) => {
        const { active, over } = event
        if (active.id !== over?.id) {
            const oldIndex = items.findIndex(i => i.__id === active.id)
            const newIndex = items.findIndex(i => i.__id === over.id)
            onChange(arrayMove(items, oldIndex, newIndex))
        }
    }

    const addItem = () => {
        if (field.props.maxItems && items.length >= field.props.maxItems) return
        const newItem = createDefaultItem(field.props.schema)
        onChange([...items, { ...newItem, __id: crypto.randomUUID() }])
    }

    const removeItem = (id: string) => {
        if (field.props.minItems && items.length <= field.props.minItems) return
        onChange(items.filter(i => i.__id !== id))
    }

    const updateItem = (id: string, key: string, value: unknown) => {
        onChange(items.map(i => i.__id === id ? { ...i, [key]: value } : i))
    }

    return (
        <div className="repeater-field space-y-2">
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
                <SortableContext items={items.map(i => i.__id)} strategy={verticalListSortingStrategy}>
                    {items.map((item, index) => (
                        <RepeaterItem
                            key={item.__id}
                            id={item.__id}
                            item={item}
                            index={index}
                            schema={field.props.schema}
                            collapsible={field.props.collapsible}
                            itemLabel={field.props.itemLabel}
                            reorderable={field.props.reorderable}
                            cloneable={field.props.cloneable}
                            onChange={(key, value) => updateItem(item.__id, key, value)}
                            onRemove={() => removeItem(item.__id)}
                            onClone={() => cloneItem(item)}
                        />
                    ))}
                </SortableContext>
            </DndContext>

            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={addItem}
                disabled={field.props.maxItems && items.length >= field.props.maxItems}
            >
                <Plus className="mr-2 h-4 w-4" />
                Add Item
            </Button>

            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    )
}
```

`RepeaterItem`: card collapsible com sub-form (FormRenderer com schema items), drag handle, actions (collapse, clone, remove).

**Critérios de aceite**

- [ ] Items renderizados com sub-form
- [ ] Add button expande array
- [ ] Remove button colapsa (com confirmation se complex)
- [ ] Drag-drop reorder via dnd-kit
- [ ] Collapsed state mostra itemLabel template
- [ ] Clone duplicates item com new id
- [ ] Min/max items enforced em UI
- [ ] Performance: 50 items renderizam em <300ms
- [ ] A11y: drag via keyboard (Space to grab, arrows to move, Space to drop)
- [ ] Nested repeaters funcionam
- [ ] Testes cobrem add/remove/reorder/clone

**Notas de implementação**

- `__id` interno para React keys + dnd-kit — não persistido.
- Performance: memoizar `RepeaterItem` via `React.memo` para evitar re-render em massa.

---

### [FIELDS-ADV-014] React component `BuilderInput`

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** XL • **Camada:** react • **Depende de:** [FIELDS-ADV-006, FIELDS-ADV-013]

**Contexto**

Componente React correspondente ao `BuilderField` PHP. Similar a RepeaterInput mas com blocks heterogêneos — user escolhe tipo de bloco ao adicionar.

**Descrição técnica**

Criar `@arqel-dev/fields-advanced/src/builder/BuilderInput.tsx`:

```tsx
import { useState } from 'react'
import { DndContext, closestCenter } from '@dnd-kit/core'
import { SortableContext, verticalListSortingStrategy, arrayMove } from '@dnd-kit/sortable'
import { BlockPicker } from './BlockPicker'
import { BuilderBlock } from './BuilderBlock'

interface BuilderItem {
    __id: string
    type: string
    data: Record<string, unknown>
}

export function BuilderInput({ field, value, onChange, error }: FieldComponentProps<BuilderItem[]>) {
    const items = Array.isArray(value) ? value : []
    const [pickerOpen, setPickerOpen] = useState(false)

    const addBlock = (type: string) => {
        const blockSchema = field.props.blocks[type]
        const newBlock: BuilderItem = {
            __id: crypto.randomUUID(),
            type,
            data: createDefaultItem(blockSchema.schema),
        }
        onChange([...items, newBlock])
        setPickerOpen(false)
    }

    // Similar reorder/remove/update as Repeater, but per-block schema resolution
    
    return (
        <div className="builder-field space-y-2">
            <DndContext onDragEnd={handleDragEnd}>
                <SortableContext items={items.map(i => i.__id)} strategy={verticalListSortingStrategy}>
                    {items.map((item) => (
                        <BuilderBlock
                            key={item.__id}
                            item={item}
                            blockConfig={field.props.blocks[item.type]}
                            onChange={(data) => updateBlock(item.__id, data)}
                            onRemove={() => removeBlock(item.__id)}
                        />
                    ))}
                </SortableContext>
            </DndContext>

            <BlockPicker
                open={pickerOpen}
                onOpenChange={setPickerOpen}
                blocks={field.props.blocks}
                onSelect={addBlock}
            />

            <Button type="button" variant="outline" onClick={() => setPickerOpen(true)}>
                <Plus className="mr-2 h-4 w-4" />
                Add Block
            </Button>
        </div>
    )
}
```

`BlockPicker`: modal/popover showing available block types com icon + label + description. Click escolhe type.

`BuilderBlock`: card with block type indicator + sub-form baseado em schema do block type.

**Critérios de aceite**

- [ ] Block picker mostra todos tipos registrados
- [ ] Add block → renderiza com schema correto
- [ ] Blocks heterogêneos coexistem
- [ ] Reorder drag-drop
- [ ] Output JSON com structure `[{type, data}]`
- [ ] Performance: 30 blocks diversos <400ms
- [ ] A11y: block picker keyboard navigable
- [ ] Testes cobrem add + reorder + update per type

**Notas de implementação**

- BuilderInput reutiliza RepeaterItem internals via shared components.
- Block schemas vêm do PHP serializados — não reinventar.

---

### [FIELDS-ADV-015] React component `KeyValueInput`

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** react • **Depende de:** [FIELDS-ADV-007]

**Contexto**

Componente React correspondente ao `KeyValueField` PHP. Table-like editor com 2 colunas (key/value) + add/remove rows.

**Descrição técnica**

Criar `@arqel-dev/fields-advanced/src/key-value/KeyValueInput.tsx`:

```tsx
import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Plus, X, GripVertical } from 'lucide-react'
import { DndContext } from '@dnd-kit/core'
import { SortableContext } from '@dnd-kit/sortable'

interface KeyValueItem {
    __id: string
    key: string
    value: string
}

export function KeyValueInput({ field, value, onChange, disabled }: FieldComponentProps<KeyValueItem[]>) {
    const items = Array.isArray(value) ? value : []

    const addRow = () => {
        onChange([...items, { __id: crypto.randomUUID(), key: '', value: '' }])
    }

    const updateRow = (id: string, field: 'key' | 'value', newValue: string) => {
        onChange(items.map(i => i.__id === id ? { ...i, [field]: newValue } : i))
    }

    const removeRow = (id: string) => {
        onChange(items.filter(i => i.__id !== id))
    }

    return (
        <div className="key-value-field">
            <div className="grid grid-cols-[auto_1fr_1fr_auto] gap-2 items-center">
                <div />
                <Label className="text-sm text-muted-foreground">{field.props.keyLabel}</Label>
                <Label className="text-sm text-muted-foreground">{field.props.valueLabel}</Label>
                <div />

                {items.map((item, index) => (
                    <KeyValueRow
                        key={item.__id}
                        item={item}
                        reorderable={field.props.reorderable}
                        editableKeys={field.props.editableKeys}
                        keyPlaceholder={field.props.keyPlaceholder}
                        valuePlaceholder={field.props.valuePlaceholder}
                        onChange={(f, v) => updateRow(item.__id, f, v)}
                        onRemove={field.props.deletable ? () => removeRow(item.__id) : undefined}
                    />
                ))}
            </div>

            {field.props.addable && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={addRow}
                    disabled={disabled}
                    className="mt-2"
                >
                    <Plus className="mr-2 h-4 w-4" />
                    Add Row
                </Button>
            )}
        </div>
    )
}
```

Suporta output formats: array of objects (`[{key, value}]`) OU associative object (`{key: value}`). Config-driven via `field.props.asObject`.

**Critérios de aceite**

- [ ] Grid layout com 2 colunas editáveis
- [ ] Add row funciona
- [ ] Remove row (se deletable)
- [ ] Editable keys toggle respeitado
- [ ] Drag-drop reorder (opt-in)
- [ ] Placeholder custom
- [ ] Output format configurable
- [ ] A11y: labels associados, table semantics
- [ ] Testes cobrem CRUD rows

---

### [FIELDS-ADV-016] React component `TagsInput`

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** react • **Depende de:** [FIELDS-ADV-008]

**Contexto**

Componente React correspondente ao `TagsField` PHP. Combobox com chips para cada tag.

**Descrição técnica**

Criar `@arqel-dev/fields-advanced/src/tags/TagsInput.tsx`:

```tsx
import { useState, useRef, KeyboardEvent } from 'react'
import { X } from 'lucide-react'
import { Combobox } from '@base-ui-components/react/combobox'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'

export function TagsInput({ field, value, onChange, disabled }: FieldComponentProps<string[]>) {
    const tags = Array.isArray(value) ? value : []
    const [inputValue, setInputValue] = useState('')
    const [suggestions, setSuggestions] = useState<string[]>([])
    const inputRef = useRef<HTMLInputElement>(null)

    const addTag = (tag: string) => {
        const trimmed = tag.trim()
        if (!trimmed) return
        if (field.props.unique && tags.includes(trimmed)) return
        if (field.props.maxTags && tags.length >= field.props.maxTags) return
        onChange([...tags, trimmed])
        setInputValue('')
    }

    const removeTag = (index: number) => {
        onChange(tags.filter((_, i) => i !== index))
    }

    const handleKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter' || (field.props.separator && e.key === field.props.separator)) {
            e.preventDefault()
            addTag(inputValue)
        } else if (e.key === 'Backspace' && inputValue === '' && tags.length > 0) {
            removeTag(tags.length - 1)
        }
    }

    const handlePaste = (e: React.ClipboardEvent) => {
        const text = e.clipboardData.getData('text')
        if (field.props.separator && text.includes(field.props.separator)) {
            e.preventDefault()
            const newTags = text.split(field.props.separator).map(t => t.trim()).filter(Boolean)
            newTags.forEach(addTag)
        }
    }

    return (
        <div className="tags-field flex flex-wrap gap-1 p-2 border rounded-md focus-within:ring-2 focus-within:ring-ring">
            {tags.map((tag, index) => (
                <Badge key={`${tag}-${index}`} variant="secondary" className="gap-1">
                    {tag}
                    {!disabled && (
                        <button
                            type="button"
                            onClick={() => removeTag(index)}
                            aria-label={`Remove ${tag}`}
                        >
                            <X className="h-3 w-3" />
                        </button>
                    )}
                </Badge>
            ))}
            <Input
                ref={inputRef}
                value={inputValue}
                onChange={(e) => setInputValue(e.target.value)}
                onKeyDown={handleKeyDown}
                onPaste={handlePaste}
                placeholder={tags.length === 0 ? field.placeholder : ''}
                disabled={disabled}
                className="border-0 shadow-none h-auto p-0 flex-1 min-w-[100px]"
            />
        </div>
    )
}
```

Suggestions dropdown aparece quando user digita, filtra `field.props.suggestions`.

Creatable flag determina se novas tags (fora suggestions) são permitidas.

**Critérios de aceite**

- [ ] Enter adiciona tag
- [ ] Backspace remove última (quando input vazio)
- [ ] Paste com separator adiciona múltiplas
- [ ] Suggestions dropdown funcional
- [ ] Creatable toggle enforced
- [ ] Max tags enforced
- [ ] Unique dedupe
- [ ] X button remove individual
- [ ] A11y: aria-label em remove buttons, combobox pattern
- [ ] Spatie Tags integration (quando fromRelationship)
- [ ] Testes cobrem add/remove/paste/keyboard

---

### [FIELDS-ADV-017] React component `WizardInput` (multi-step)

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** XL • **Camada:** react • **Depende de:** [FIELDS-ADV-009, UI-004] (Fase 1)

**Contexto**

Componente React para Wizard layout. Multi-step com state preservation via React 19.2 `<Activity>`.

**Descrição técnica**

Criar `@arqel-dev/fields-advanced/src/wizard/WizardInput.tsx`:

```tsx
import { useState, Activity } from 'react'
import { FormRenderer } from '@arqel-dev/ui'
import { WizardProgress } from './WizardProgress'
import { Button } from '@/components/ui/button'
import { ChevronLeft, ChevronRight, Check } from 'lucide-react'

interface WizardStep {
    id: string
    label: string
    icon?: string
    badge?: number
    schema: LayoutComponent[]
}

interface WizardProps {
    wizard: {
        steps: WizardStep[]
        startOnStep?: string
        showStepProgress: boolean
        submitLabel: string
    }
    data: Record<string, unknown>
    errors: Record<string, string[]>
    onChange: (name: string, value: unknown) => void
    onSubmit: () => void
}

export function WizardInput({ wizard, data, errors, onChange, onSubmit }: WizardProps) {
    const [currentStepIndex, setCurrentStepIndex] = useState(() => {
        if (wizard.startOnStep) {
            const idx = wizard.steps.findIndex(s => s.id === wizard.startOnStep)
            return idx >= 0 ? idx : 0
        }
        return 0
    })

    const currentStep = wizard.steps[currentStepIndex]
    const isFirst = currentStepIndex === 0
    const isLast = currentStepIndex === wizard.steps.length - 1

    const validateCurrentStep = async (): Promise<boolean> => {
        // Trigger validation via Inertia partial reload for current step fields
        // Returns true if valid
        return true
    }

    const next = async () => {
        if (await validateCurrentStep()) {
            setCurrentStepIndex(i => Math.min(i + 1, wizard.steps.length - 1))
        }
    }

    const back = () => {
        setCurrentStepIndex(i => Math.max(i - 1, 0))
    }

    return (
        <div className="wizard space-y-6">
            {wizard.showStepProgress && (
                <WizardProgress
                    steps={wizard.steps}
                    currentIndex={currentStepIndex}
                    onStepClick={(idx) => idx < currentStepIndex && setCurrentStepIndex(idx)}
                />
            )}

            {wizard.steps.map((step, idx) => (
                <Activity key={step.id} mode={idx === currentStepIndex ? 'visible' : 'hidden'}>
                    <div className="wizard-step">
                        <FormRenderer
                            form={{ schema: step.schema }}
                            fields={flattenFields(step.schema)}
                            data={data}
                            errors={errors}
                            onChange={onChange}
                        />
                    </div>
                </Activity>
            ))}

            <div className="wizard-actions flex justify-between">
                <Button type="button" variant="outline" onClick={back} disabled={isFirst}>
                    <ChevronLeft className="mr-2 h-4 w-4" />
                    Back
                </Button>
                {isLast ? (
                    <Button type="button" onClick={onSubmit}>
                        <Check className="mr-2 h-4 w-4" />
                        {wizard.submitLabel}
                    </Button>
                ) : (
                    <Button type="button" onClick={next}>
                        Next
                        <ChevronRight className="ml-2 h-4 w-4" />
                    </Button>
                )}
            </div>
        </div>
    )
}
```

`<Activity>` é React 19.2 feature — preserva state de steps não-visíveis sem re-mount. Fallback para versões anteriores via `display: none` (perde state de inputs uncontrolled).

`WizardProgress`: horizontal stepper com step labels + icons + badges (ex: validation errors count). Completed steps são clickable para voltar.

**Critérios de aceite**

- [ ] Wizard renderiza step corrente
- [ ] Next valida current step antes de avançar
- [ ] Back preserva state via `<Activity>`
- [ ] Progress indicator mostra current step
- [ ] Badges dinâmicos (ex: erro count)
- [ ] Completed steps clickable
- [ ] Submit no último step envia full data
- [ ] Keyboard nav: Tab por fields, Enter no last field avança
- [ ] A11y: aria-current em step ativo, progress semantics
- [ ] Fallback para React <19.2 funciona (degrades gracefully)
- [ ] Testes cobrem navigation + validation

**Notas de implementação**

- `<Activity>` documentação: [react.dev/reference/react/Activity](https://react.dev/).
- Validation per-step via Inertia `router.reload` com `only: [currentStep.fields]`.

---

### [FIELDS-ADV-018] Registry + boot em `@arqel-dev/fields-advanced`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** react • **Depende de:** [FIELDS-ADV-017]

**Descrição técnica**

Registro lazy:

```typescript
// @arqel-dev/fields-advanced/index.ts
import { registerField } from '@arqel-dev/fields'

registerField('RichTextInput', () => import('./RichTextInput').then(m => m.RichTextInput))
registerField('MarkdownInput', () => import('./MarkdownInput').then(m => m.MarkdownInput))
// ... etc
```

Registry suporta factory functions (lazy) além de componentes sync.

**Critérios de aceite**

- [ ] Todos os 8 advanced field components registrados
- [ ] Lazy loading funciona (Network tab mostra chunks loaded on-demand)
- [ ] Registry resolve sync OR async corretamente

---

### [FIELDS-ADV-019] Testes de advanced fields

**Tipo:** test • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php + react • **Depende de:** [FIELDS-ADV-018]

**Descrição técnica**

- PHP Unit tests: cada field class
- PHP Feature tests: hydration/dehydration com relationships (Repeater)
- React: Testing Library para cada component
- Integration tests: form completo com advanced fields
- Coverage ≥ 85% (heavy UI, harder to hit 90%)

**Critérios de aceite**

- [ ] Pest passa
- [ ] Vitest passa
- [ ] Coverage ≥ 85%
- [ ] E2E: form com RichText + Repeater + Wizard

---

### [FIELDS-ADV-020] SKILL.md + docs dos advanced fields

**Tipo:** docs • **Prioridade:** P1 • **Estimativa:** M • **Camada:** docs • **Depende de:** [FIELDS-ADV-019]

**Descrição técnica**

SKILL.md + `docs/guide/advanced-fields.md` com:
- Cada field: propósito, API, exemplo de uso
- Security considerations (XSS em RichText, sanitization)
- Performance (lazy loading, bundle size)
- Custom blocks em Builder (extensibility)

**Critérios de aceite**

- [ ] SKILL.md completo
- [ ] Guide completo em docs site

---

## 5. MCP server (MCP)

### [MCP-001] Esqueleto do pacote `arqel-dev/mcp`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [CORE-008] (Fase 1)

**Contexto**

Cobre RF-DX-07 e ADR-013. MCP (Model Context Protocol) é standard 2025+ para expor ferramentas a LLMs. Arqel MCP expõe introspection e codegen para Claude Code, Cursor, etc.

**Descrição técnica**

Estrutura `packages/mcp/`:

- `composer.json` (deps: `arqel-dev/core`, um MCP PHP SDK — verificar disponibilidade)
- `src/McpServer.php` (main)
- `src/Tools/` (individual MCP tools)
- `src/Resources/` (MCP "resources" — different from Arqel Resources)
- `src/Prompts/` (MCP prompts)
- `src/Commands/ServeMcpCommand.php`
- `src/McpServiceProvider.php`
- SKILL.md, tests/

**Critérios de aceite**

- [ ] Pacote resolve
- [ ] ServiceProvider discovered
- [ ] SKILL.md esqueleto

**Notas de implementação**

- MCP PHP SDK — verificar se existe comunidade 2026; caso contrário, implementar spec MCP diretamente (JSON-RPC over stdio).
- Spec oficial em modelcontextprotocol.io.

---

### [MCP-002] `McpServer` core + JSON-RPC handling

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** XL • **Camada:** php • **Depende de:** [MCP-001]

**Contexto**

Core do server MCP. Implementa spec JSON-RPC 2.0 sobre stdio.

**Descrição técnica**

```php
<?php

declare(strict_types=1);

namespace Arqel\Mcp;

final class McpServer
{
    /** @var array<string, callable> */
    private array $tools = [];
    private array $resources = [];
    private array $prompts = [];
    
    public function registerTool(string $name, string $description, array $inputSchema, callable $handler): void;
    public function registerResource(string $uri, string $name, string $description, callable $fetcher): void;
    public function registerPrompt(string $name, string $description, array $arguments, callable $generator): void;
    
    public function serve(): void
    {
        while (($line = fgets(STDIN)) !== false) {
            $request = json_decode(trim($line), true);
            $response = $this->handleRequest($request);
            fwrite(STDOUT, json_encode($response) . "\n");
        }
    }
    
    private function handleRequest(array $request): array
    {
        return match ($request['method']) {
            'initialize' => $this->initialize($request),
            'tools/list' => $this->listTools($request),
            'tools/call' => $this->callTool($request),
            'resources/list' => $this->listResources($request),
            'resources/read' => $this->readResource($request),
            'prompts/list' => $this->listPrompts($request),
            'prompts/get' => $this->getPrompt($request),
            default => $this->methodNotFound($request),
        };
    }
}
```

Artisan command:

```php
final class ServeMcpCommand extends Command
{
    protected $signature = 'arqel:mcp';
    
    public function handle(McpServer $server): int
    {
        $server->serve();
        return self::SUCCESS;
    }
}
```

Usuário configura em seu cliente MCP (Claude Code, Cursor):

```json
{
  "mcpServers": {
    "arqel": {
      "command": "php",
      "args": ["artisan", "arqel:mcp"],
      "cwd": "/path/to/project"
    }
  }
}
```

**Critérios de aceite**

- [ ] JSON-RPC 2.0 handling correto
- [ ] Handshake initialize funciona
- [ ] List tools/resources/prompts retorna arrays corretos
- [ ] Tool call com params válidos executa handler
- [ ] Error responses seguem spec
- [ ] Teste: invocar server com input JSON, verificar output

**Notas de implementação**

- stdio loop é blocking — Arqel MCP server roda por-invocação, não long-running.
- HTTP SSE variant fica para Fase 3 (quando long-running makes sense).

---

### [MCP-003] Tool: `list_resources` (Arqel Resources)

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [MCP-002]

**Contexto**

Primeira tool: listar Arqel Resources disponíveis. Permite LLM entender estrutura do projeto.

**Descrição técnica**

```php
<?php

declare(strict_types=1);

namespace Arqel\Mcp\Tools;

final class ListResourcesTool
{
    public function schema(): array
    {
        return [
            'name' => 'list_resources',
            'description' => 'List all Arqel Resources registered in the application',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [],
            ],
        ];
    }

    public function __invoke(array $params): array
    {
        $registry = app(\Arqel\Core\Resources\ResourceRegistry::class);
        $resources = $registry->all();
        
        return [
            'resources' => array_map(fn ($r) => [
                'class' => $r,
                'model' => $r::getModel(),
                'slug' => $r::getSlug(),
                'label' => $r::getLabel(),
                'pluralLabel' => $r::getPluralLabel(),
                'navigationGroup' => $r::getNavigationGroup(),
            ], $resources),
        ];
    }
}
```

**Critérios de aceite**

- [ ] Tool registrada
- [ ] Invocação retorna todas Resources com metadata
- [ ] Teste: invocar via JSON-RPC mock

---

### [MCP-004] Tool: `describe_resource`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [MCP-003]

**Contexto**

Introspecção detalhada de uma Resource específica — fields, actions, policies.

**Descrição técnica**

```php
final class DescribeResourceTool
{
    public function schema(): array
    {
        return [
            'name' => 'describe_resource',
            'description' => 'Get detailed information about a specific Arqel Resource, including fields, table columns, actions, and policy',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'slug' => [
                        'type' => 'string',
                        'description' => 'The Resource slug (e.g., "users")',
                    ],
                ],
                'required' => ['slug'],
            ],
        ];
    }

    public function __invoke(array $params): array
    {
        $slug = $params['slug'] ?? throw new \InvalidArgumentException('slug required');
        
        $registry = app(ResourceRegistry::class);
        $class = $registry->findBySlug($slug) ?? throw new \RuntimeException("Resource '$slug' not found");
        
        $instance = app($class);
        
        return [
            'class' => $class,
            'model' => $class::getModel(),
            'slug' => $class::getSlug(),
            'label' => $class::getLabel(),
            'fields' => array_map(fn ($f) => [
                'name' => $f->getName(),
                'type' => $f->getType(),
                'label' => $f->getLabel(),
                'required' => $f->isRequired(),
                'rules' => $f->getValidationRules(),
            ], $instance->fields()),
            'policyClass' => $this->resolvePolicyClass($class::getModel()),
            'abilities' => $this->resolveAbilities($class::getModel()),
        ];
    }
}
```

**Critérios de aceite**

- [ ] Tool retorna estrutura completa de Resource
- [ ] Includes fields, validation rules, policy info
- [ ] Teste: describe UserResource

---

### [MCP-005] Tool: `generate_resource`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [MCP-002]

**Contexto**

LLM pode gerar Resource automatic baseado em model. Wrapper do `arqel:resource` command.

**Descrição técnica**

```php
final class GenerateResourceTool
{
    public function schema(): array
    {
        return [
            'name' => 'generate_resource',
            'description' => 'Generate a new Arqel Resource for an Eloquent model',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'model' => [
                        'type' => 'string',
                        'description' => 'Eloquent model class name (e.g., "User" or "App\\Models\\User")',
                    ],
                    'fromModel' => [
                        'type' => 'boolean',
                        'description' => 'Auto-generate fields from model attributes',
                        'default' => true,
                    ],
                    'withPolicy' => [
                        'type' => 'boolean',
                        'description' => 'Also generate Policy class',
                        'default' => true,
                    ],
                ],
                'required' => ['model'],
            ],
        ];
    }

    public function __invoke(array $params): array
    {
        // Invoke arqel:resource command programmatically
        \Artisan::call('arqel:resource', [
            'model' => $params['model'],
            '--from-model' => $params['fromModel'] ?? true,
            '--with-policy' => $params['withPolicy'] ?? true,
            '--force' => false,
        ]);
        
        return [
            'output' => \Artisan::output(),
            'files_created' => $this->detectCreatedFiles($params['model']),
        ];
    }
}
```

**Critérios de aceite**

- [ ] Invocação via MCP gera Resource
- [ ] Output retornado ao LLM
- [ ] Files created list retornado
- [ ] Error handling (model doesn't exist, etc.)
- [ ] Teste: generate via MCP mock

---

### [MCP-006] Tool: `run_test`

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php • **Depende de:** [MCP-002]

**Contexto**

LLM pode executar testes — útil para TDD workflow com Claude Code.

**Descrição técnica**

```php
final class RunTestTool
{
    public function schema(): array
    {
        return [
            'name' => 'run_test',
            'description' => 'Run Pest or PHPUnit tests with optional filter',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'filter' => ['type' => 'string', 'description' => 'Test name filter'],
                    'path' => ['type' => 'string', 'description' => 'Path to tests directory'],
                    'coverage' => ['type' => 'boolean', 'default' => false],
                ],
            ],
        ];
    }

    public function __invoke(array $params): array
    {
        $cmd = ['./vendor/bin/pest'];
        if ($filter = $params['filter'] ?? null) $cmd[] = "--filter={$filter}";
        if ($path = $params['path'] ?? null) $cmd[] = $path;
        if ($params['coverage'] ?? false) $cmd[] = '--coverage';
        
        $process = new Symfony\Component\Process\Process($cmd);
        $process->setTimeout(300);
        $process->run();
        
        return [
            'exitCode' => $process->getExitCode(),
            'output' => $process->getOutput(),
            'errorOutput' => $process->getErrorOutput(),
            'success' => $process->isSuccessful(),
        ];
    }
}
```

**Critérios de aceite**

- [ ] Executa Pest com filter
- [ ] Output streamed ou buffered
- [ ] Timeout 5 min default
- [ ] Teste: invocar com filter específico

---

### [MCP-007] Resource: `arqel_skill` (expose SKILL.md files)

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [MCP-002]

**Contexto**

MCP "Resources" (different concept) permitem LLM fetch documents. Cada pacote tem SKILL.md — expor todos.

**Descrição técnica**

```php
final class ArqelSkillResource
{
    public function list(): array
    {
        $packages = ['core', 'fields', 'table', 'form', 'actions', 'auth', 'nav', 'tenant', 'widgets', 'mcp'];
        
        return array_map(fn ($pkg) => [
            'uri' => "arqel-skill://{$pkg}",
            'name' => "SKILL.md for arqel/{$pkg}",
            'description' => "AI agent context for the {$pkg} package",
            'mimeType' => 'text/markdown',
        ], $packages);
    }

    public function read(string $uri): array
    {
        preg_match('#^arqel-skill://(.+)$#', $uri, $matches);
        $package = $matches[1] ?? throw new \RuntimeException('Invalid URI');
        
        $path = base_path("vendor/arqel/{$package}/SKILL.md");
        if (!file_exists($path)) {
            throw new \RuntimeException("SKILL.md not found for arqel/{$package}");
        }
        
        return [
            'contents' => [[
                'uri' => $uri,
                'mimeType' => 'text/markdown',
                'text' => file_get_contents($path),
            ]],
        ];
    }
}
```

**Critérios de aceite**

- [ ] List retorna todos SKILL.md
- [ ] Read retorna conteúdo do arquivo
- [ ] Invalid URI retorna erro
- [ ] Teste: list + read happy path

---

### [MCP-008] Prompts: migration, review_resource

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php • **Depende de:** [MCP-002]

**Contexto**

MCP Prompts são templates que user pode invocar. Forneceremos prompts úteis pre-built.

**Descrição técnica**

`MigrationPrompt`:

```php
class MigrationPrompt
{
    public function schema(): array
    {
        return [
            'name' => 'migrate_filament_resource',
            'description' => 'Help migrate a Filament Resource to Arqel',
            'arguments' => [
                ['name' => 'filament_file', 'description' => 'Path to Filament Resource file'],
            ],
        ];
    }

    public function generate(array $args): array
    {
        $file = $args['filament_file'];
        $content = file_get_contents(base_path($file));
        
        return [
            'description' => 'Migration guidance',
            'messages' => [[
                'role' => 'user',
                'content' => [[
                    'type' => 'text',
                    'text' => "Migrate this Filament Resource to Arqel:\n\n```php\n{$content}\n```\n\nKey differences:\n- Arqel uses Inertia+React (not Livewire)\n- Actions syntax is similar\n- Field::text equivalent, but customization differs\n\nProduce the equivalent ArqelResource.",
                ]],
            ]],
        ];
    }
}
```

`ReviewResourcePrompt`: prompt para LLM revisar Resource existente buscando issues.

**Critérios de aceite**

- [ ] 2 prompts implementados
- [ ] Invocation retorna prompt template estruturado
- [ ] Teste: invocar prompt, verificar output

---

### [MCP-009] Testes + integração com Claude Code/Cursor

**Tipo:** test • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php • **Depende de:** [MCP-008]

**Descrição técnica**

- Unit tests: cada Tool, Resource, Prompt em isolation
- Integration tests: simular full MCP conversation via fake stdio
- Manual integration test: configurar Claude Code real, verificar que tools aparecem e executam
- Documentar setup em docs

**Critérios de aceite**

- [ ] Pest passa
- [ ] Coverage ≥ 85%
- [ ] Manual test Claude Code: tools visíveis, invocáveis, retornam resultado
- [ ] Manual test Cursor: same
- [ ] Docs step-by-step setup

**Notas de implementação**

- Testing stdio loops precisa fake stream — `fopen('php://memory', 'rw')`.

---

### [MCP-010] SKILL.md do pacote MCP

**Tipo:** docs • **Prioridade:** P1 • **Estimativa:** S • **Camada:** docs • **Depende de:** [MCP-009]

**Descrição técnica**

SKILL.md + docs com setup para Claude Code, Cursor, Windsurf. Exemplos de cada tool invocation. Security considerations (MCP server executa código — cuidado em prod).

**Critérios de aceite**

- [ ] SKILL.md completo
- [ ] Setup guides em docs

---

---

## 6. Table enhancements (TABLE-V2)

### [TABLE-V2-001] Virtual scrolling para datasets grandes

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** react • **Depende de:** [UI-003] (Fase 1)

**Contexto**

Cobre RF-T-08. Tables com 10k+ linhas matam o browser sem virtualização. TanStack Virtual é solução standard 2026.

**Descrição técnica**

Adicionar dependência `@tanstack/react-virtual` ao `@arqel-dev/ui`.

Em `DataTable.tsx` implementar modo virtual:

```tsx
import { useVirtualizer } from '@tanstack/react-virtual'

export function DataTable({ data, virtualScrolling, ...props }: DataTableProps) {
    const parentRef = useRef<HTMLDivElement>(null)
    
    const virtualizer = useVirtualizer({
        count: data.length,
        getScrollElement: () => parentRef.current,
        estimateSize: () => 48, // Row height estimate
        overscan: 10,
    })
    
    if (virtualScrolling) {
        return (
            <div ref={parentRef} className="h-[600px] overflow-auto">
                <table style={{ height: virtualizer.getTotalSize() }}>
                    <thead>{/* sticky header */}</thead>
                    <tbody>
                        {virtualizer.getVirtualItems().map((virtualRow) => (
                            <tr
                                key={virtualRow.key}
                                style={{
                                    position: 'absolute',
                                    top: 0,
                                    transform: `translateY(${virtualRow.start}px)`,
                                }}
                            >
                                {/* render row */}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        )
    }
    
    // Regular rendering
}
```

Opt-in via PHP:

```php
Table::make()->virtualScrolling()
```

Serializa `virtualScrolling: true` em props.

**Critérios de aceite**

- [ ] Table com 10k rows renderiza sem freezes
- [ ] Scroll é smooth (60fps)
- [ ] Row height dinâmico funciona (variable heights)
- [ ] Sticky header preservado
- [ ] Row selection funciona em virtual mode
- [ ] A11y: keyboard nav funciona (requer scrollIntoView em focus)
- [ ] Teste performance: 100k rows em <500ms initial render
- [ ] Teste E2E: scroll through large dataset

**Notas de implementação**

- Variable row heights (ex: rows com long text) precisam measure via ResizeObserver.
- Selection via Set (já implementado) é O(1) — safe mesmo em 100k.

---

### [TABLE-V2-002] Inline editing (TextInputColumn, SelectColumn, ToggleColumn)

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** XL • **Camada:** php + react • **Depende de:** [TABLE-003] (Fase 1)

**Contexto**

Cobre RF-T-10. Edição inline poupa tempo — clique célula, edita, salva. Ficheiros bulk updates. Common em admin panels sheets-like.

**Descrição técnica**

Novo tipo de column que extende base Column com edit behavior:

```php
<?php

declare(strict_types=1);

namespace Arqel\Table\Columns;

final class TextInputColumn extends Column
{
    protected string $type = 'textInput';
    protected bool $editable = true;
    protected int $debounce = 500; // ms
    protected array $rules = [];
    
    public function rules(array $rules): static;
    public function debounce(int $ms): static;
    public function readonly(bool|Closure $readonly = true): static;
}
```

Similar classes: `SelectColumn`, `ToggleColumn`.

Endpoint: `POST /admin/{resource}/{id}/inline-update`

```php
final class InlineUpdateController
{
    public function __invoke(
        Request $request,
        string $resource,
        string $id,
    ): JsonResponse {
        $resourceClass = $this->registry->findBySlug($resource);
        abort_if(!$resourceClass, 404);
        
        $record = $resourceClass::$model::findOrFail($id);
        $this->authorize('update', $record);
        
        $column = $request->input('column');
        $value = $request->input('value');
        
        // Resolve column, validate
        $resourceInstance = app($resourceClass);
        $columnInstance = $this->resolveEditableColumn($resourceInstance, $column);
        abort_if(!$columnInstance?->isEditable(), 400);
        
        $validator = Validator::make(
            [$column => $value],
            [$column => $columnInstance->getRules()]
        );
        
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }
        
        $record->update([$column => $value]);
        
        return response()->json([
            'success' => true,
            'record' => $record->fresh()->toArray(),
        ]);
    }
}
```

React: inline edit ativa on double-click ou botão edit pequeno. Debounce save automático. Optimistic update.

**Critérios de aceite**

- [ ] Double-click célula abre edit mode
- [ ] Debounced save (500ms default)
- [ ] Validation errors aparecem inline
- [ ] Authorization check server-side
- [ ] Optimistic update (rollback em failure)
- [ ] Escape cancela edit
- [ ] Tab avança para próxima célula editável
- [ ] Teste Feature: inline update com valid/invalid data

**Notas de implementação**

- Optimistic update com rollback em error é complex — usar React 19.2 `useOptimistic`.
- Concurrency: se user A edita e user B também, last-write-wins por default. Opt-in para optimistic concurrency via version column em Fase 3.

---

### [TABLE-V2-003] Visual Query Builder (filter)

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** XL • **Camada:** php + react • **Depende de:** [TABLE-004] (Fase 1)

**Contexto**

Cobre RF-T-11. Power users querem AND/OR queries com operators variados. Filament tem; nós entregamos melhor.

**Descrição técnica**

PHP side — novo Filter type:

```php
<?php

declare(strict_types=1);

namespace Arqel\Table\Filters;

final class QueryBuilderFilter extends Filter
{
    protected string $type = 'queryBuilder';
    protected array $constraints = [];
    
    public function constraints(array $constraints): static;
    
    public function applyToQuery(Builder $query, mixed $value): Builder
    {
        if (!is_array($value) || empty($value['conditions'])) {
            return $query;
        }
        
        return $query->where(function (Builder $q) use ($value) {
            $this->applyConditions($q, $value['conditions'], $value['operator'] ?? 'AND');
        });
    }
    
    private function applyConditions(Builder $query, array $conditions, string $operator): void
    {
        foreach ($conditions as $condition) {
            $method = $operator === 'OR' ? 'orWhere' : 'where';
            
            if (isset($condition['group'])) {
                $query->{$method}(function ($subQuery) use ($condition) {
                    $this->applyConditions($subQuery, $condition['conditions'], $condition['operator']);
                });
                continue;
            }
            
            $constraint = $this->findConstraint($condition['field']);
            $constraint->apply($query, $condition['operator'], $condition['value'], $method);
        }
    }
}
```

Constraints declaram fields e operators suportados:

```php
use Arqel\Table\Filters\QueryBuilder;

QueryBuilderFilter::make('advanced_search')
    ->constraints([
        Constraint::text('name')->operators(['equals', 'contains', 'starts_with']),
        Constraint::number('age')->operators(['=', '!=', '>', '<', '>=', '<=', 'between']),
        Constraint::date('created_at')->operators(['=', 'before', 'after', 'between']),
        Constraint::boolean('is_active'),
        Constraint::select('role_id')->options(fn () => Role::pluck('name', 'id')),
    ])
```

React side: tree-structured UI com groups AND/OR, adicionar/remover conditions, each condition tem field picker + operator picker + value input polimórfico.

**Critérios de aceite**

- [ ] User pode criar query com múltiplas conditions
- [ ] AND/OR toggle entre groups
- [ ] Nested groups funcionam
- [ ] Operators corretos por field type (text, number, date, boolean)
- [ ] Serialização → SQL safe (sem injection)
- [ ] Save/load saved queries (localStorage Fase 2, DB Fase 3)
- [ ] Teste: query complex com AND + OR + nested
- [ ] Security test: tentativa de injection

**Notas de implementação**

- Query whitelisting crítico — não permitir field names arbitrary.
- UI pode ficar complexa — considerar "simple mode" vs "advanced mode" toggle.

---

### [TABLE-V2-004] Column visibility persistence per-user

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php + react • **Depende de:** [TABLE-003] (Fase 1)

**Contexto**

Cobre RF-T-07. Users customizam colunas visíveis; persiste entre sessões.

**Descrição técnica**

PHP:

- Nova coluna JSON `settings` no Users table (migration opcional ou trait `HasArqelSettings`)
- `Column::togglable()` marca coluna como toggleable

```php
Column::text('email')->togglable(),
Column::text('phone')->togglable()->hiddenByDefault(),
```

- Endpoint `POST /admin/user-settings/tables/{resource}` recebe `{visible_columns: ['name', 'email']}` e persiste

React:

- Column visibility dropdown (icon button no table header)
- Checkbox list de columns togglable
- Auto-save via debounced API call
- Shared props incluem `user.settings.tables[slug].visible_columns`

**Critérios de aceite**

- [ ] Dropdown visível quando há columns togglable
- [ ] Toggle column mostra/esconde imediatamente
- [ ] Persistence entre page reload
- [ ] Hidden by default funciona para power columns
- [ ] Mobile: some columns auto-hidden (`hiddenOnMobile` column flag)
- [ ] Teste: set visibility, reload, state preserved

**Notas de implementação**

- `hiddenOnMobile` é design decision — columns com muito texto (descriptions) beneficiam.
- Alternativa sem DB: localStorage. Menos ideal (device-specific).

---

### [TABLE-V2-005] Grouping com summaries

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** L • **Camada:** php + react • **Depende de:** [TABLE-003] (Fase 1)

**Contexto**

Cobre RF-T-12. Group rows por valor de column + summaries (sum, avg, count).

**Descrição técnica**

PHP:

```php
Table::make()
    ->groupBy('status', fn ($record) => ucfirst($record->status))
    ->groupSummaries([
        Summary::sum('amount')->label('Total'),
        Summary::avg('amount')->label('Average'),
        Summary::count()->label('Count'),
    ])
```

Query:

```php
// Group query via SQL grouping OR post-processing (for small datasets)
$records->groupBy('status');
```

React: agrupar rows visualmente com sticky group headers + summary rows.

**Critérios de aceite**

- [ ] Groups renderizados com headers
- [ ] Summaries calculadas corretamente (sum, avg, count, min, max)
- [ ] Expand/collapse groups
- [ ] Sort dentro de grupos
- [ ] Teste: group com 100 records em 5 grupos

**Notas de implementação**

- Performance: group via SQL é mais rápido. Post-processing para <1k records é OK.
- Group header row precisa data attribute para screen readers.

---

### [TABLE-V2-006] Reorderable rows (drag-drop)

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** L • **Camada:** php + react • **Depende de:** [TABLE-003] (Fase 1)

**Contexto**

Cobre RF-T-13. Drag-drop para reordenar rows. Útil para listas manualmente ordenadas (ex: menu items, priority queues).

**Descrição técnica**

PHP:

```php
Table::make()
    ->reorderable('position') // Column name
    ->defaultSort('position')
```

Model precisa coluna `position` (integer).

Endpoint: `POST /admin/{resource}/reorder`:

```php
public function reorder(Request $request, string $resource): JsonResponse
{
    $ids = $request->input('ids'); // Array of record IDs in new order
    
    $resourceClass = $this->registry->findBySlug($resource);
    $resourceClass::$model::query()
        ->whereIn('id', $ids)
        ->get()
        ->each(function ($record) use ($ids) {
            $record->update(['position' => array_search($record->id, $ids)]);
        });
    
    return response()->json(['success' => true]);
}
```

React: drag handle em cada row, usa dnd-kit. Optimistic update + revert on failure.

**Critérios de aceite**

- [ ] Drag handles visíveis
- [ ] Drag-drop smooth
- [ ] Position coluna atualizada no DB
- [ ] Authorization: update policy verificada
- [ ] Optimistic update com rollback
- [ ] Teste E2E: reorder 10 rows, verificar order persistido

**Notas de implementação**

- Não permitir reorder quando sort column != position (inconsistência visual).
- Scroll during drag: auto-scroll when near edges.

---

### [TABLE-V2-007] Stacked rows em mobile

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** react • **Depende de:** [TABLE-003] (Fase 1)

**Contexto**

Cobre RF-T-09. Table em mobile é difícil de ler. Opção: stacked cards ao invés de columns.

**Descrição técnica**

Em `DataTable.tsx`, detectar breakpoint via `useBreakpoint()`. Em `sm` ou menor, renderizar cards:

```tsx
{isMobile ? (
    <div className="space-y-2">
        {data.map(row => (
            <Card key={row.id}>
                {columns.filter(c => !c.hiddenOnMobile).map(col => (
                    <div key={col.name} className="flex justify-between py-1 border-b last:border-0">
                        <span className="text-muted-foreground text-sm">{col.label}</span>
                        <span className="font-medium">{formatCell(row, col)}</span>
                    </div>
                ))}
                <div className="mt-2 pt-2 border-t flex justify-end gap-2">
                    {/* Actions */}
                </div>
            </Card>
        ))}
    </div>
) : (
    // Regular table
)}
```

Opt-out via PHP:

```php
Table::make()->mobileMode('scroll') // Instead of 'stacked' default
```

**Critérios de aceite**

- [ ] Mobile renders stacked cards
- [ ] Actions visible per card
- [ ] Selection funciona (checkbox no topo de card)
- [ ] Performance OK (50 cards)
- [ ] Teste responsive em Chrome DevTools

**Notas de implementação**

- Landscape mobile pode ter espaço — threshold 640px default mas configurable.

---

### [TABLE-V2-008] Infinite scroll (Inertia 3 merge)

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php + react • **Depende de:** [TABLE-005] (Fase 1)

**Contexto**

Cobre RF-T-17. Alternative a pagination: carregar mais rows em scroll. Inertia 3 `merge` prop.

**Descrição técnica**

PHP:

```php
Table::make()->paginate('infinite')
```

Serializa flag `paginationType: 'infinite'` em props.

React: IntersectionObserver em último row, quando visível chama:

```typescript
router.reload({
    only: ['records'],
    merge: ['records.data'],
    data: { page: currentPage + 1 },
})
```

**Critérios de aceite**

- [ ] Scroll to bottom carrega mais rows
- [ ] Loading indicator durante fetch
- [ ] Sem duplicate rows
- [ ] Fim de dados: mostrar "No more results"
- [ ] Funciona com filters aplicados
- [ ] Teste: load 3 pages via scroll

**Notas de implementação**

- Combinar com virtual scrolling complica. Default: stops virtual quando infinite.

---

### [TABLE-V2-009] Testes de TABLE-V2 enhancements

**Tipo:** test • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php + react • **Depende de:** [TABLE-V2-008]

**Descrição técnica**

Tests para cada enhancement:

- Virtual scrolling: render 100k rows sem crash
- Inline editing: happy path + validation + authorization
- QueryBuilder: complex queries safe
- Column visibility: persistência
- Grouping: summaries corretas
- Reorderable: persistência position
- Stacked mobile: rendering
- Infinite scroll: merge sem duplicates

Coverage ≥ 85%.

**Critérios de aceite**

- [ ] Todos tests passam
- [ ] Coverage ≥ 85%
- [ ] E2E covers user flows principais

---

### [TABLE-V2-010] SKILL.md update + docs enhancements

**Tipo:** docs • **Prioridade:** P1 • **Estimativa:** M • **Camada:** docs • **Depende de:** [TABLE-V2-009]

**Descrição técnica**

Update `packages/table/SKILL.md` com nova features. Novas páginas em docs site:

- `guide/tables/virtual-scrolling.md`
- `guide/tables/inline-editing.md`
- `guide/tables/query-builder.md`
- `guide/tables/grouping.md`

**Critérios de aceite**

- [ ] SKILL.md atualizado
- [ ] 4 guides novos publicados

---

## 7. Export e import (EXPORT)

### [EXPORT-001] Esqueleto do pacote `arqel-dev/export`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** S • **Camada:** php • **Depende de:** [ACTIONS-002] (Fase 1)

**Contexto**

Cobre RF-T-14. Export para CSV, XLSX, PDF. Standard em admin panels.

**Descrição técnica**

Estrutura `packages/export/`:

- `composer.json` (deps: `arqel-dev/core`, `arqel-dev/actions`, suggest: spatie/simple-excel, dompdf/dompdf)
- `src/ExportFormat.php` (interface/enum: CSV, XLSX, PDF)
- `src/Exporters/CsvExporter.php`
- `src/Exporters/XlsxExporter.php`
- `src/Exporters/PdfExporter.php`
- `src/Actions/ExportAction.php` (pre-configured BulkAction)
- `src/Jobs/ProcessExportJob.php` (async)
- `src/Models/Export.php` (track exports em DB)
- `ExportServiceProvider.php`
- `SKILL.md`, tests/

**Critérios de aceite**

- [ ] Pacote resolve
- [ ] Migrations para Export model
- [ ] SKILL.md esqueleto

**Notas de implementação**

- spatie/simple-excel é leve (mais leve que Maatwebsite Excel).
- dompdf é suficiente para PDFs simples; Snappy/wkhtmltopdf para complexos.

---

### [EXPORT-002] `CsvExporter` com streaming

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [EXPORT-001]

**Contexto**

CSV é formato mais common. Streaming para não saturar memory em datasets grandes.

**Descrição técnica**

```php
<?php

declare(strict_types=1);

namespace Arqel\Export\Exporters;

use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Http\Response;

final class CsvExporter implements Exporter
{
    public function export(
        array $columns,
        iterable $records,
        string $filename,
    ): Response {
        return response()->streamDownload(function () use ($columns, $records) {
            $writer = SimpleExcelWriter::streamDownload(php://output)
                ->addHeader(array_column($columns, 'label'));
            
            foreach ($records as $record) {
                $row = [];
                foreach ($columns as $col) {
                    $row[] = $this->formatCell($record, $col);
                }
                $writer->addRow($row);
            }
            
            $writer->close();
        }, $filename);
    }
    
    private function formatCell($record, $column): string
    {
        $value = data_get($record, $column['name']);
        
        return match ($column['type']) {
            'date' => $value instanceof \DateTime ? $value->format('Y-m-d') : $value,
            'boolean' => $value ? 'Yes' : 'No',
            'relationship' => data_get($record, $column['display_path']),
            default => (string) $value,
        };
    }
}
```

**Critérios de aceite**

- [ ] CSV export stream para browser
- [ ] Headers corretos (Content-Type, Content-Disposition)
- [ ] Memory usage constante em dataset grande
- [ ] Format cells por column type
- [ ] UTF-8 com BOM (Excel compatibility)
- [ ] Teste: export 10k records, memory <100MB

**Notas de implementação**

- BOM UTF-8 necessário para Excel abrir corretamente em Windows.
- Escape de CSV quotes via SimpleExcel handling.

---

### [EXPORT-003] `XlsxExporter`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [EXPORT-001]

**Descrição técnica**

Similar a CsvExporter mas formato XLSX. Usa spatie/simple-excel ou PhpSpreadsheet.

Features adicionais:
- Cell formatting (dates, currency, boolean)
- Header row bold + freeze
- Column widths auto
- Múltiplas sheets via opt-in (Fase 3)

```php
$writer = SimpleExcelWriter::streamDownload($filename)
    ->addHeader($headers, [
        'style' => ['font' => ['bold' => true]],
    ])
    ->freezeRow(1);
```

**Critérios de aceite**

- [ ] XLSX export funciona
- [ ] Formatting: bold headers, frozen row
- [ ] Dates formatadas como Excel dates (não strings)
- [ ] Memory usage reasonable (streaming)
- [ ] Teste: open output em Excel, verify correctness

---

### [EXPORT-004] `PdfExporter`

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** L • **Camada:** php • **Depende de:** [EXPORT-001]

**Descrição técnica**

PDF é mais complex — layout matter. Usar dompdf (leve) ou Laravel Snappy (wkhtmltopdf wrapper, melhor quality).

Template Blade customizable:

```php
// Resource customization
public function pdfView(): string
{
    return 'admin.users.pdf-export';
}

public function pdfOrientation(): string
{
    return 'landscape';
}
```

Default view em pacote:

```blade
{{-- packages/export/resources/views/default.blade.php --}}
<html>
<body>
    <h1>{{ $title }}</h1>
    <table>
        <thead>
            <tr>@foreach ($columns as $col)<th>{{ $col['label'] }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @foreach ($records as $record)
                <tr>@foreach ($columns as $col)<td>{{ data_get($record, $col['name']) }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
```

**Critérios de aceite**

- [ ] PDF export funciona com default template
- [ ] Customizable via Resource override
- [ ] Orientation + margins configurável
- [ ] Table styling preservada
- [ ] Teste: export 100 records para PDF

**Notas de implementação**

- dompdf struggles com CSS complex. Snappy é mais fiel mas requer binary wkhtmltopdf installed.
- Suggest both em composer.json; user escolhe.

---

### [EXPORT-005] `ExportAction` como pre-configured BulkAction

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [EXPORT-002, EXPORT-003]

**Descrição técnica**

```php
<?php

declare(strict_types=1);

namespace Arqel\Export\Actions;

use Arqel\Actions\BulkAction;

final class ExportAction
{
    public static function make(string $name = 'export'): BulkAction
    {
        return BulkAction::make($name)
            ->label('Export')
            ->icon('download')
            ->form([
                Field::select('format')
                    ->options([
                        'csv' => 'CSV',
                        'xlsx' => 'Excel',
                        'pdf' => 'PDF',
                    ])
                    ->default('csv')
                    ->required(),
            ])
            ->action(function (Collection $records, array $data) {
                $exporter = app("Arqel\\Export\\Exporters\\" . ucfirst($data['format']) . 'Exporter');
                $columns = $this->resolveColumns(); // From context
                $filename = $this->generateFilename($data['format']);
                
                if ($records->count() > config('arqel.export.queue_threshold', 1000)) {
                    ProcessExportJob::dispatch(
                        userId: auth()->id(),
                        format: $data['format'],
                        recordIds: $records->pluck('id')->all(),
                        resource: static::class,
                    );
                    
                    session()->flash('info', 'Export queued. You will receive a notification when ready.');
                    return;
                }
                
                return $exporter->export($columns, $records, $filename);
            });
    }
}
```

Usage:

```php
Table::make()->bulkActions([
    ExportAction::make(),
])
```

**Critérios de aceite**

- [ ] ExportAction é incluível facilmente
- [ ] Form modal com format selection
- [ ] Small datasets: direct download
- [ ] Large datasets: queued com notification
- [ ] Teste Feature: export happy + queued

---

### [EXPORT-006] `ProcessExportJob` + notifications

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [EXPORT-005]

**Contexto**

Datasets grandes (>1k records) processados em queue. Notify user quando pronto.

**Descrição técnica**

```php
<?php

declare(strict_types=1);

namespace Arqel\Export\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $format,
        public readonly array $recordIds,
        public readonly string $resourceClass,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        $resource = app($this->resourceClass);
        $records = $this->resourceClass::$model::whereIn('id', $this->recordIds)->get();
        $exporter = app("Arqel\\Export\\Exporters\\" . ucfirst($this->format) . 'Exporter');
        
        $filename = $this->generateFilename();
        $path = storage_path("app/exports/{$filename}");
        
        $exporter->exportToFile($records, $resource->table()->getColumns(), $path);
        
        // Create Export record for tracking
        $export = Export::create([
            'user_id' => $user->id,
            'filename' => $filename,
            'path' => $path,
            'expires_at' => now()->addDays(7),
        ]);
        
        // Notify user
        $user->notify(new ExportReadyNotification($export));
    }
}
```

`ExportReadyNotification`: send email + in-app (database) notification.

Download endpoint: `GET /admin/exports/{id}/download` com policy que verifica ownership.

**Critérios de aceite**

- [ ] Job processa em background
- [ ] File saved em storage
- [ ] Notification criada
- [ ] Download endpoint com auth
- [ ] Cleanup job remove files after 7 days
- [ ] Teste: dispatch job, verify file created + notification

**Notas de implementação**

- Use `Redis` ou `database` queue em prod (NOT `sync`).
- Storage disk configurável — local, S3, etc.

---

### [EXPORT-007] Testes + SKILL.md do EXPORT

**Tipo:** test + docs • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php + docs • **Depende de:** [EXPORT-006]

**Descrição técnica**

- Tests CSV/XLSX/PDF output correctness
- Feature test: action trigger + queue + notification
- Memory profile test (10k records)
- SKILL.md: formats supported, queue config, custom templates PDF

**Critérios de aceite**

- [ ] Tests passam
- [ ] Coverage ≥ 85%
- [ ] SKILL.md completo

---

## 8. Command palette (CMDPAL)

### [CMDPAL-001] Backend: endpoint + command registry

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [CORE-005] (Fase 1)

**Contexto**

Cobre RF-N-08. Cmd+K palette que unifica navegação, create, search, actions.

**Descrição técnica**

Comandos do palette categorizados:

- **Navigation**: ir para qualquer resource (generated from nav)
- **Create**: iniciar criação de qualquer resource (if policy allows)
- **Search**: search global (by record title) across resources
- **Actions**: recent actions + frequently used
- **Settings**: toggle theme, logout, switch tenant

Registry:

```php
<?php

declare(strict_types=1);

namespace Arqel\Core\CommandPalette;

final class CommandRegistry
{
    /** @var array<Command> */
    private array $commands = [];
    /** @var array<Closure> */
    private array $providers = [];

    public function register(Command $command): void;
    public function registerProvider(Closure $provider): void;
    
    public function resolveFor(?Authenticatable $user, string $query): array
    {
        $allCommands = $this->commands;
        
        // Call providers (lazy commands)
        foreach ($this->providers as $provider) {
            $allCommands = array_merge($allCommands, $provider($user, $query));
        }
        
        // Fuzzy filter
        return $this->fuzzyFilter($allCommands, $query);
    }
}
```

Built-in providers:
- `NavigationCommandProvider`: all nav items
- `CreateCommandProvider`: "Create {Resource}" for each
- `RecordSearchProvider`: search records across resources
- `ThemeCommandProvider`: toggle theme

Endpoint `GET /admin/commands?q=search`:

```php
public function commands(Request $request): JsonResponse
{
    $query = $request->input('q', '');
    $commands = $this->registry->resolveFor($request->user(), $query);
    
    return response()->json([
        'commands' => array_map(fn ($c) => $c->toArray(), $commands),
    ]);
}
```

**Critérios de aceite**

- [ ] Registry suporta commands estáticos + providers lazy
- [ ] 4 built-in providers registrados
- [ ] Endpoint retorna commands filtered by user policies
- [ ] Fuzzy search básico funciona
- [ ] Teste: registry lookup happy path

---

### [CMDPAL-002] Fuzzy search server-side

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [CMDPAL-001]

**Contexto**

Fuzzy match sobre command labels + descriptions. Performance em 50+ commands.

**Descrição técnica**

Implementar fuzzy matching simples:

```php
private function fuzzyScore(string $query, string $text): int
{
    $query = strtolower($query);
    $text = strtolower($text);
    
    if ($query === '') return 100;
    if (str_contains($text, $query)) return 90;
    
    // Check if all chars of query appear in order
    $textIdx = 0;
    foreach (str_split($query) as $char) {
        $pos = strpos($text, $char, $textIdx);
        if ($pos === false) return 0;
        $textIdx = $pos + 1;
    }
    
    return 50;
}
```

Order by score desc. Limit 10 results.

**Critérios de aceite**

- [ ] "usr" matches "users"
- [ ] "crt ps" matches "Create Post"
- [ ] Exact matches rank highest
- [ ] Performance: 100 commands filtered em <5ms

**Notas de implementação**

- Mais sofisticado via Symfony/ElasticSearch Fase 3.

---

### [CMDPAL-003] React component `<CommandPalette>`

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** react • **Depende de:** [CMDPAL-002]

**Descrição técnica**

`@arqel-dev/ui/palette/CommandPalette.tsx`:

- Cmd+K (Ctrl+K) trigger
- Modal dialog com search input
- Debounced fetch to `/admin/commands`
- Results grouped by category
- Keyboard navigation: Arrow up/down, Enter to execute
- Recently used list when empty query
- Escape closes

Integrar em AppShell:

```tsx
<AppShell>
    <CommandPalette />
    {children}
</AppShell>
```

**Critérios de aceite**

- [ ] Cmd+K abre palette
- [ ] Search debounced 150ms
- [ ] Grouped results (Navigation, Create, etc.)
- [ ] Keyboard nav 100% functional
- [ ] Recent commands stored localStorage
- [ ] Close on escape / click outside
- [ ] A11y: aria-live para results count
- [ ] Teste E2E: trigger, search, execute

**Notas de implementação**

- Base UI Dialog + Combobox primitives.
- Recent commands: top 5 most used in last 30 days.

---

### [CMDPAL-004] Custom commands registration

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php • **Depende de:** [CMDPAL-001]

**Contexto**

Users devem poder registrar custom commands (ex: "Clear cache", "Reset password for X").

**Descrição técnica**

```php
// In ArqelServiceProvider::boot()
Arqel::command('clear-cache')
    ->label('Clear Cache')
    ->icon('refresh-cw')
    ->category('Settings')
    ->authorize(fn ($user) => $user->isAdmin())
    ->action(fn () => Artisan::call('cache:clear'));
```

Ou classe:

```php
final class ClearCacheCommand extends Command
{
    public static function label(): string { return 'Clear Cache'; }
    public static function icon(): string { return 'refresh-cw'; }
    public static function category(): string { return 'Settings'; }
    public function authorize(User $user): bool { return $user->isAdmin(); }
    public function execute(): void { Artisan::call('cache:clear'); }
}
```

**Critérios de aceite**

- [ ] Fluent API e class API funcionam
- [ ] Authorization per-command
- [ ] Custom commands aparecem no palette
- [ ] Teste: register + invoke custom command

---

### [CMDPAL-005] Testes + SKILL.md + docs CMDPAL

**Tipo:** test + docs • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php + docs • **Depende de:** [CMDPAL-004]

**Descrição técnica**

- Tests: registry, fuzzy search, authorization
- E2E: command palette workflows completos
- SKILL.md + guide de 20+ commands recomendados

**Critérios de aceite**

- [ ] Tests passam
- [ ] SKILL.md completo
- [ ] Guide publicado

---

## 9. Audit log (AUDIT)

### [AUDIT-001] Pacote `arqel-dev/audit` wraps spatie/laravel-activitylog

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php • **Depende de:** [CORE-008] (Fase 1)

**Contexto**

Cobre RF-IN-04. Audit log é feature crítica para enterprise. Spatie ActivityLog é standard — não reinventar, integrar bem.

**Descrição técnica**

Estrutura `packages/audit/`:

- `composer.json` (dep: spatie/laravel-activitylog ^4.10)
- `src/Concerns/LogsActivity.php` (trait wrapper)
- `src/Http/Controllers/ActivityLogController.php`
- `src/AuditServiceProvider.php`
- SKILL.md, tests/

Convenience trait que configura ActivityLog com Arqel defaults:

```php
<?php

declare(strict_types=1);

namespace Arqel\Audit\Concerns;

use Spatie\Activitylog\Traits\LogsActivity as SpatieLogsActivity;
use Spatie\Activitylog\LogOptions;

trait LogsActivity
{
    use SpatieLogsActivity {
        SpatieLogsActivity::getActivitylogOptions as _getSpatieOptions;
    }
    
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getAuditableAttributes())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName(class_basename($this));
    }

    protected function getAuditableAttributes(): array
    {
        return $this->fillable ?? ['*'];
    }
}
```

**Critérios de aceite**

- [ ] Trait aplicada em model loga CRUD
- [ ] Changes before/after registradas
- [ ] User_id automatic
- [ ] Teste: create/update/delete produce log entries

---

### [AUDIT-002] UI — Activity timeline em Resource detail

**Tipo:** feat • **Prioridade:** P0 • **Estimativa:** L • **Camada:** php + react • **Depende de:** [AUDIT-001]

**Contexto**

Activity log é inútil se não vê-lo. UI tab em Resource detail mostra timeline.

**Descrição técnica**

PHP: adicionar method em Resource base:

```php
public function showActivityLog(): bool
{
    return in_array(LogsActivity::class, class_uses_recursive($this::$model));
}

public function activityLogForRecord(Model $record): array
{
    return $record->activities()
        ->with('causer')
        ->latest()
        ->paginate(20)
        ->toArray();
}
```

Endpoint: `GET /admin/{resource}/{id}/activity`

React: `ActivityTimeline` component — render list of log entries:

- User (causer) + avatar
- Action (created, updated, deleted)
- Changes diff (before/after)
- Timestamp (relative: "2h ago")
- IP address (if available)

Renderizar como tab no detail page.

**Critérios de aceite**

- [ ] Activity tab appears em Resource detail (quando trait LogsActivity presente)
- [ ] Timeline renderizado bonitamente
- [ ] Diff viewer para campos mudados
- [ ] Paginação
- [ ] Filter by user / action type
- [ ] Teste E2E: edit record, verify activity aparece

---

### [AUDIT-003] Global activity log page

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php • **Depende de:** [AUDIT-002]

**Contexto**

Admin global view de todas activities (cross-resource).

**Descrição técnica**

`ActivityLogResource extends Resource`:

```php
final class ActivityLogResource extends Resource
{
    public static string $model = Activity::class;
    public static ?string $label = 'Activity Log';
    public static ?string $navigationIcon = 'activity';
    public static ?string $navigationGroup = 'Settings';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Column::date('created_at')->label('When'),
                Column::relationship('causer', 'name')->label('User'),
                Column::text('description')->searchable(),
                Column::text('subject_type')->label('On')->formatStateUsing(fn ($state) => class_basename($state)),
                Column::text('event')->badge(),
            ])
            ->filters([
                Filter::select('log_name')->options(fn () => Activity::distinct()->pluck('log_name', 'log_name')),
                Filter::select('event')->options(['created', 'updated', 'deleted', 'restored']),
                Filter::dateRange('created_at'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
```

Auto-register quando Audit pacote instalado.

**Critérios de aceite**

- [ ] Resource auto-registrada
- [ ] Nav entry "Activity Log" em Settings group
- [ ] Filters funcionam
- [ ] Teste: navegar para lista, filter por user

---

### [AUDIT-004] Testes + SKILL.md do AUDIT

**Tipo:** test + docs • **Prioridade:** P0 • **Estimativa:** M • **Camada:** php + docs • **Depende de:** [AUDIT-003]

**Descrição técnica**

- Feature tests: CRUD records, verify logs
- Timeline UI test
- SKILL.md: setup, customization, retention policy, exporting logs

**Critérios de aceite**

- [ ] Tests passam
- [ ] Coverage ≥ 85%
- [ ] SKILL.md completo

---

## 10. Docs e release (DOCS-V2, GOV-V2)

### [DOCS-V2-001] Docs atualização multi-tenancy + dashboards + advanced fields + MCP

**Tipo:** docs • **Prioridade:** P0 • **Estimativa:** L • **Camada:** docs • **Depende de:** [MCP-010, TENANT-015, WIDGETS-015]

**Descrição técnica**

Novos guides em docs site:

- `guide/tenancy/overview.md` + sub-pages (resolvers, scoped queries, stancl, spatie)
- `guide/dashboards/overview.md` + widget types
- `guide/advanced-fields/*` — um por field type
- `guide/mcp/overview.md` + setup Claude Code + Cursor
- Update `guide/tables/*` com V2 enhancements

Update API reference PHP + TS com novos classes.

**Critérios de aceite**

- [ ] 30+ new pages publicados
- [ ] API reference regenerada
- [ ] Changelog entry v0.6.0 → v0.8.0

---

### [DOCS-V2-002] Migration guides completos (Filament, Nova)

**Tipo:** docs • **Prioridade:** P1 • **Estimativa:** L • **Camada:** docs • **Depende de:** [DOCS-007] (Fase 1)

**Contexto**

Fase 1 teve migration guides básicos. Agora com features paridade (multi-tenancy, dashboards, MCP), podemos fazer migration claims fortes.

**Descrição técnica**

Expandir migration guides com:

- Side-by-side API comparison (50+ patterns)
- Migration scripts (PHP tool que converte Filament Resource → Arqel)
- Video walkthrough (5-10 min cada)
- Case study: migração de app real

**Critérios de aceite**

- [ ] Migration guides 3x mais completos
- [ ] Scripts funcionais para 80% dos cases
- [ ] Videos publicados

---

### [GOV-V2-001] Release v0.8.0 (RC) processo

**Tipo:** infra • **Prioridade:** P0 • **Estimativa:** M • **Camada:** infra • **Depende de:** [GOV-002] (Fase 1)

**Descrição técnica**

Preparação de RC:

- Bump versions para 0.8.0
- Comprehensive testing em playground app
- Performance benchmarks: documentar baseline
- Security audit passada
- CHANGELOG completo
- Blog post announcement em arqel.dev/blog
- Laravel News submission

**Critérios de aceite**

- [ ] v0.8.0-rc.1 tagged
- [ ] All critical/high severity bugs resolved
- [ ] Performance metrics documentados
- [ ] Blog post publicado
- [ ] Community announcement (Twitter, Discord, Reddit r/laravel)

---

### [GOV-V2-002] Community infrastructure — Discord + forum

**Tipo:** infra • **Prioridade:** P1 • **Estimativa:** M • **Camada:** infra • **Depende de:** [INFRA-001] (Fase 1)

**Descrição técnica**

- Setup Discord server oficial
- Canais: #announcements, #general, #help, #show-and-tell, #contributors
- Bot moderação + docs search integration
- GitHub Discussions habilitado
- Template issues: bug report, feature request, RFC (architectural proposals)

**Critérios de aceite**

- [ ] Discord server live
- [ ] Links prominent em README e docs
- [ ] Moderação basic configurada

---

## 11. Ordem sugerida de execução

### Sprint 1-2: Multi-tenancy + advanced fields foundation (semanas 1-6)

**1 dev PHP senior:**

1. TENANT-001 → TENANT-002 → TENANT-003 → TENANT-004 → TENANT-005
2. TENANT-006 → TENANT-007 → TENANT-008
3. TENANT-009, 010, 011 (paralelizável)

**1 dev PHP segundo:**

1. FIELDS-ADV-001 → FIELDS-ADV-002 (RichText é XL) → FIELDS-ADV-003 → FIELDS-ADV-004

**1 dev JS:**

1. FIELDS-ADV-010 → FIELDS-ADV-011 → FIELDS-ADV-012 (advanced field components)

### Sprint 3-4: Widgets + more advanced fields (semanas 7-12)

**1 dev PHP senior:**

1. WIDGETS-001 → WIDGETS-002 → WIDGETS-003 → WIDGETS-004 → WIDGETS-005
2. WIDGETS-006 → WIDGETS-007 → WIDGETS-008 → WIDGETS-009
3. WIDGETS-013 (scaffolder)

**1 dev PHP segundo:**

1. FIELDS-ADV-005 (Repeater - XL) → FIELDS-ADV-006 (Builder - XL)
2. FIELDS-ADV-007 → FIELDS-ADV-008 → FIELDS-ADV-009 (Wizard - XL)

**1 dev JS:**

1. WIDGETS-010 (XL) → WIDGETS-011
2. FIELDS-ADV-013 → FIELDS-ADV-014 → FIELDS-ADV-015 → FIELDS-ADV-016 → FIELDS-ADV-017

### Sprint 5-6: MCP + Table V2 (semanas 13-18)

**1 dev PHP senior:**

1. MCP-001 → MCP-002 (XL, JSON-RPC handling)
2. MCP-003 → MCP-004 → MCP-005 → MCP-006 → MCP-007 → MCP-008 → MCP-009 → MCP-010

**1 dev PHP segundo:**

1. TABLE-V2-001 (Virtual scrolling) → TABLE-V2-002 (Inline editing - XL) → TABLE-V2-003 (QueryBuilder - XL)
2. TABLE-V2-004 → TABLE-V2-005 → TABLE-V2-006 → TABLE-V2-007 → TABLE-V2-008

**1 dev JS:** support TABLE-V2 component work

### Sprint 7: Export + Command Palette + Audit (semanas 19-22)

**1 dev PHP:**

1. EXPORT-001 → EXPORT-002 → EXPORT-003 → EXPORT-004 → EXPORT-005 → EXPORT-006 → EXPORT-007

**1 dev PHP segundo:**

1. CMDPAL-001 → CMDPAL-002 → CMDPAL-004 → CMDPAL-005
2. AUDIT-001 → AUDIT-002 → AUDIT-003 → AUDIT-004

**1 dev JS:**

1. CMDPAL-003 (UI component palette)

### Sprint 8-9: Polish + RC release (semanas 23-28)

**Todos devs:**

1. Fix bugs
2. Performance tuning (advanced fields lazy loading, widget polling optimization)
3. Testes completos (TENANT-014, WIDGETS-014, FIELDS-ADV-019, MCP-009, TABLE-V2-009, EXPORT-007, CMDPAL-005, AUDIT-004)
4. DOCS-V2-001, DOCS-V2-002 (full migration guides)
5. GOV-V2-001 (v0.8.0-rc release)
6. GOV-V2-002 (Discord + community)
7. Pilot user onboarding sessions
8. Dogfood em projeto interno

### Critérios de saída Fase 2

- v0.8.0-rc.1 tagged em Packagist + npm
- ≥3 pilot apps com multi-tenancy em produção
- Dashboard demo com 5+ widgets funcionais
- MCP server testado com Claude Code + Cursor (manual test confirmations)
- Export de 10k records em <30s (benchmark CI)
- Virtual scrolling renderiza 100k rows smooth (perf test)
- Command palette com 20+ commands (Registry count)
- 100+ production users (analytics arqel.dev)
- 15+ third-party plugins Composer (Packagist search)
- 2.000+ GitHub stars
- Zero P0/P1 bugs abertos
- Cobertura testes PHP ≥ 85%, JS ≥ 80%
- Docs site atualizado com todas as novas features

---

## Resumo

**Fase 2 Essenciais:** ~90 tickets detalhados, 4-7 meses com 3-4 devs.

**Entregas principais:**
- Multi-tenancy production-ready (5 resolvers, 2 adapters opt-in)
- Dashboards com widgets (4 tipos + custom)
- 8 advanced field types (RichText, Markdown, Code, Repeater, Builder, KeyValue, Tags, Wizard)
- MCP server oficial com 5 tools + 1 resource + 2 prompts
- Table V2: virtual scrolling, inline editing, QueryBuilder, grouping, reorderable, stacked mobile, infinite scroll
- Export CSV/XLSX/PDF com async queue
- Command palette (Cmd+K) com fuzzy search
- Audit log (spatie wrapper + UI timeline)

**Próximo documento:** `10-fase-3-avancadas.md` — tickets Fase 3 (AI fields, real-time collaboration, workflow engine, semantic search, record versioning, OpenAPI gen).

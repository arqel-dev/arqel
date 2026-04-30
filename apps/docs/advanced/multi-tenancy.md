# Multi-tenancy

> Pacote: [`arqel/tenant`](../../packages/tenant/) · Tickets: TENANT-001..015

## Purpose

`arqel/tenant` fornece primitivas de multi-tenancy para o stack Arqel cobrindo dois grandes modos:

- **Single-DB scoped** (default) — todos tenants partilham o mesmo schema; isolamento via global scope `tenant_id` no Eloquent. 80% dos casos. Zero overhead operacional.
- **Multi-DB** (opt-in) — cada tenant tem o seu próprio database. Integra com `stancl/tenancy` ou `spatie/laravel-multitenancy` via adapters; não reinventa migrations/seeders isolados.

A escolha é **não reinventar**: o pacote oferece um `TenantManager` singleton + `TenantResolver` contract com 5 implementações concretas, e delega multi-DB às soluções já maduras.

## Quick start

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
Route::middleware(['web', 'auth', 'arqel.tenant'])->group(function () {
    Route::get('/admin', AdminController::class);
});
```

Cada model com coluna `tenant_id` adiciona o trait:

```php
use Arqel\Tenant\Concerns\BelongsToTenant;

final class Project extends Model
{
    use BelongsToTenant;
}

// Auto-scoped:
Project::all();
```

## Key concepts

### `TenantManager` (singleton)

Source of truth runtime. APIs principais:

- `resolve(Request)` — memoiza per-request; chama o resolver configurado.
- `set(?Model)` / `forget()` — despacha eventos `TenantResolved` / `TenantForgotten`.
- `runFor(Model, Closure)` — swap+restore via try/finally; usado para jobs e admin override.
- `current` / `currentOrFail` / `hasCurrent` / `id` / `identifier`.

### `TenantResolver` contract

Define como descobrir o tenant a partir do `Request`. Cinco resolvers prontos:

| Resolver | Estratégia |
|---|---|
| `SubdomainResolver` | `acme.app.com` → tenant `acme` |
| `PathResolver` | `app.com/acme/...` |
| `HeaderResolver` | `X-Tenant: acme` (APIs) |
| `SessionResolver` | escolha persistida em session |
| `AuthUserResolver` | Jetstream-style `currentTeam` |

Os resolvers em `src/Resolvers/` são `class` (não-final) propositalmente: apps customizam parsing de host, regex de subdomain, ou trocam `currentTeam` por `currentOrganization`.

### Eloquent integration

- `BelongsToTenant` trait — registra `TenantScope` global + auto-fill `tenant_id` no `creating`. Foreign key resolve por: `$tenantForeignKey` na model → `config('arqel.tenancy.foreign_key')` → `'tenant_id'`.
- `withoutTenant()` / `forTenant($id)` — escapes explícitos.
- `Rules\ScopedUnique` — substituto tenant-aware da rule `unique` Laravel; aplica `where(<tenant_fk>, <id>)` quando há current tenant.

### Adapters multi-DB

Sem hard dep — gate via `class_exists`:

- `Integrations\StanclAdapter` — lê `Stancl\Tenancy\Tenancy::tenant`; honra `getTenantKey()` com fallback `getKey()`.
- `Integrations\SpatieAdapter` — chama `current()` static do Spatie; `modelClass` vazio cai em `Spatie\Multitenancy\Models\Tenant`.

### Switching de tenant

Endpoint pronto:

- `POST /admin/tenants/{tenantId}/switch` — `TenantSwitcherController` chama `canSwitchTo` → `switchTo` → dispatcha `TenantSwitched`.
- `GET /admin/tenants/available` — devolve `{current, available[]}`.

Resolvers ganham o contract `SupportsTenantSwitching` (`availableFor` / `canSwitchTo` / `switchTo`).

### Theming

```php
use Arqel\Tenant\Theming\TenantThemeResolver;

public function share(Request $request): array
{
    $theme = app(TenantThemeResolver::class)->resolve();

    return [
        ...parent::share($request),
        'tenant' => [
            'theme' => $theme->isEmpty() ? null : $theme->toArray(),
        ],
    ];
}
```

`CssVarsRenderer::renderInlineStyle()` faz sanitização defensiva (drop de `<`, `>`, `"` + `htmlspecialchars`) — nunca concatenar atributos de tenant direto no HTML.

## Examples

### Cross-tenant query (admin override)

```php
app(TenantManager::class)->runFor($otherTenant, fn () => Project::all());
```

### Job hidratado

```php
public function handle(): void
{
    app(TenantManager::class)->runFor($this->tenant, function () {
        // Tudo aqui está scoped ao tenant correto, mesmo no queue worker.
        Order::pending()->each->process();
    });
}
```

### Feature gate

```php
Route::middleware('arqel.tenant.feature:analytics')->group(function () {
    Route::get('/analytics', AnalyticsController::class);
});
```

Tenant sem `analytics` em `features` array → 402 `{error: 'feature_not_available', feature, message}`.

## Anti-patterns

- ❌ **Setar `current` direto via singleton em userland** — use middleware/resolver chain.
- ❌ **Trait `BelongsToTenant` sem `tenant_id` na migration** — global scope quebra `where`.
- ❌ **Bypass do `TenantScope` com `withoutGlobalScope` no controller** — use `TenantManager::runFor(null, fn () => ...)` para preservar auditoria.
- ❌ **Renderizar CSS vars de tema sem `CssVarsRenderer`**.

## Cross-tenant leakage checklist

- [ ] Todo model com `tenant_id` usa `BelongsToTenant`.
- [ ] Migrations declaram `tenant_id` com FK + index composto onde fizer sentido.
- [ ] Validation `unique` substituída por `ScopedUnique` quando o constraint é por-tenant.
- [ ] Background jobs hidratados via `runFor($job->tenant, ...)`.
- [ ] Switcher endpoints chamam `canSwitchTo` antes de `switchTo`.
- [ ] CSS vars de tema sempre via `CssVarsRenderer::renderInlineStyle()`.

## Related

- [`packages/tenant/SKILL.md`](../../packages/tenant/SKILL.md) — fonte canónica
- [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §TENANT-001..015
- [`stancl/tenancy`](https://tenancyforlaravel.com), [`spatie/laravel-multitenancy`](https://spatie.be/docs/laravel-multitenancy)

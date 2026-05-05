# Multi-tenancy

> Paquete: [`arqel-dev/tenant`](../../packages/tenant/) · Tickets: TENANT-001..015

## Propósito

`arqel-dev/tenant` provee primitivas de multi-tenancy para el stack de Arqel cubriendo dos modos principales:

- **Single-DB scoped** (default) — todos los tenants comparten el mismo schema; aislamiento vía Eloquent global scope `tenant_id`. 80% de los casos. Cero overhead operacional.
- **Multi-DB** (opt-in) — cada tenant tiene su propia base de datos. Se integra con `stancl/tenancy` o `spatie/laravel-multitenancy` vía adapters; no reinventa migrations/seeders aislados.

La elección es **no reinventar**: el paquete ofrece un singleton `TenantManager` + el contrato `TenantResolver` con 5 implementaciones concretas, y delega multi-DB a soluciones ya maduras.

## Inicio rápido

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

Cada modelo con columna `tenant_id` añade el trait:

```php
use Arqel\Tenant\Concerns\BelongsToTenant;

final class Project extends Model
{
    use BelongsToTenant;
}

// Auto-scoped:
Project::all();
```

## Conceptos clave

### `TenantManager` (singleton)

Source of truth en runtime. APIs principales:

- `resolve(Request)` — memoiza por request; llama al resolver configurado.
- `set(?Model)` / `forget()` — dispara los eventos `TenantResolved` / `TenantForgotten`.
- `runFor(Model, Closure)` — swap+restore vía try/finally; usado para jobs y override de admin.
- `current` / `currentOrFail` / `hasCurrent` / `id` / `identifier`.

### Contrato `TenantResolver`

Define cómo descubrir el tenant a partir del `Request`. Cinco resolvers incluidos:

| Resolver | Estrategia |
|---|---|
| `SubdomainResolver` | `acme.app.com` → tenant `acme` |
| `PathResolver` | `app.com/acme/...` |
| `HeaderResolver` | `X-Tenant: acme` (APIs) |
| `SessionResolver` | elección persistida en sesión |
| `AuthUserResolver` | `currentTeam` estilo Jetstream |

Los resolvers en `src/Resolvers/` son intencionalmente `class` (no-final): las apps personalizan el parsing del host, regex de subdominio, o cambian `currentTeam` por `currentOrganization`.

### Integración Eloquent

- Trait `BelongsToTenant` — registra el `TenantScope` global + auto-rellena `tenant_id` en `creating`. La foreign key se resuelve por: `$tenantForeignKey` en el modelo → `config('arqel.tenancy.foreign_key')` → `'tenant_id'`.
- `withoutTenant()` / `forTenant($id)` — escapes explícitos.
- `Rules\ScopedUnique` — sustituto tenant-aware para la regla `unique` de Laravel; aplica `where(<tenant_fk>, <id>)` cuando hay tenant actual.

### Adapters multi-DB

Sin hard dep — gated vía `class_exists`:

- `Integrations\StanclAdapter` — lee `Stancl\Tenancy\Tenancy::tenant`; honra `getTenantKey()` con fallback a `getKey()`.
- `Integrations\SpatieAdapter` — llama al `current()` estático de Spatie; `modelClass` vacío hace fallback a `Spatie\Multitenancy\Models\Tenant`.

### Cambio de tenant

Endpoint incluido:

- `POST /admin/tenants/{tenantId}/switch` — `TenantSwitcherController` llama a `canSwitchTo` → `switchTo` → dispara `TenantSwitched`.
- `GET /admin/tenants/available` — devuelve `{current, available[]}`.

Los resolvers ganan el contrato `SupportsTenantSwitching` (`availableFor` / `canSwitchTo` / `switchTo`).

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

`CssVarsRenderer::renderInlineStyle()` realiza sanitización defensiva (descarta `<`, `>`, `"` + `htmlspecialchars`) — nunca concatenes atributos del tenant directamente en HTML.

## Ejemplos

### Query cross-tenant (override de admin)

```php
app(TenantManager::class)->runFor($otherTenant, fn () => Project::all());
```

### Job hidratado

```php
public function handle(): void
{
    app(TenantManager::class)->runFor($this->tenant, function () {
        // Everything here is scoped to the right tenant, even on the queue worker.
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

Un tenant sin `analytics` en el array `features` → 402 `{error: 'feature_not_available', feature, message}`.

## Anti-patrones

- ❌ **Setear `current` directamente vía el singleton en userland** — usa la cadena middleware/resolver.
- ❌ **Trait `BelongsToTenant` sin `tenant_id` en la migration** — el global scope rompe `where`.
- ❌ **Bypassear `TenantScope` con `withoutGlobalScope` en el controlador** — usa `TenantManager::runFor(null, fn () => ...)` para preservar auditoría.
- ❌ **Renderizar CSS vars de theme sin `CssVarsRenderer`**.

## Checklist de leakage cross-tenant

- [ ] Cada modelo con `tenant_id` usa `BelongsToTenant`.
- [ ] Las migrations declaran `tenant_id` con FK + índice compuesto donde tenga sentido.
- [ ] Validación `unique` reemplazada por `ScopedUnique` cuando la constraint es por tenant.
- [ ] Background jobs hidratados vía `runFor($job->tenant, ...)`.
- [ ] Endpoints del switcher llaman a `canSwitchTo` antes de `switchTo`.
- [ ] CSS vars del theme siempre pasan por `CssVarsRenderer::renderInlineStyle()`.

## Relacionado

- [`packages/tenant/SKILL.md`](../../packages/tenant/SKILL.md) — fuente canónica
- [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §TENANT-001..015
- [`stancl/tenancy`](https://tenancyforlaravel.com), [`spatie/laravel-multitenancy`](https://spatie.be/docs/laravel-multitenancy)

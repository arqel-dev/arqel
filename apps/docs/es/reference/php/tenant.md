# `arqel-dev/tenant` — Referencia de API

Namespace `Arqel\Tenant\`. Primitivas de multi-tenancy: un singleton `TenantManager`, un contrato `TenantResolver` con cinco implementaciones concretas, middleware de arranque, un trait de Eloquent más un global scope, una regla de unicidad consciente del tenant, adaptadores opcionales para `stancl/tenancy` y `spatie/laravel-multitenancy`, cambio de tenant, theming white-label y feature gates.

El paquete cubre de forma nativa el caso de base de datos única con tenant-por-fila, y delega el aislamiento multi-base de datos a soluciones de terceros maduras a través de adaptadores. Ni `stancl/tenancy` ni `spatie/laravel-multitenancy` son dependencias duras — cada adaptador se protege con `class_exists`.

## `Arqel\Tenant\TenantManager` (final, singleton)

El constructor recibe `(?TenantResolver $resolver = null, ?Dispatcher $events = null)` — una app sin tenancy sigue obteniendo un manager funcional.

| Método | Tipo | Descripción |
|---|---|---|
| `resolve(Request $request)` | `?Model` | Resuelve mediante el resolver configurado, memoizado por petición |
| `set(?Model $tenant)` | `void` | Define el tenant actual; despacha `TenantResolved` / `TenantForgotten` |
| `forget()` | `void` | Limpia el tenant actual y despacha `TenantForgotten` |
| `runFor(Model $tenant, Closure $callback)` | `mixed` | Intercambia y restaura vía `try/finally`. Requiere un `Model` — no se acepta `null` |
| `current()` | `?Model` | |
| `currentOrFail()` | `Model` | |
| `hasCurrent()` | `bool` | |
| `id()` | `int\|string\|null` | Clave primaria del tenant actual |
| `identifier()` | `string` | Valor de la columna identificadora, vía `identifierFor()` del resolver |
| `resolved()` | `bool` | Si la resolución ya se ejecutó para esta petición |
| `availableFor(Authenticatable $user)` | `array` | Delega en el resolver; lanza `LogicException` cuando este no implementa `SupportsTenantSwitching` |
| `canSwitchTo(Authenticatable $user, Model $tenant)` | `bool` | La misma delegación |
| `switchTo(Authenticatable $user, Model $tenant)` | `void` | La misma delegación; despacha `TenantSwitched` |

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

`Arqel\Tenant\Resolvers\AbstractTenantResolver` (abstract) implementa ambos contratos. Constructor `(string $modelClass, string $identifierColumn = 'id')` — lanza `InvalidArgumentException` cuando `$modelClass` no es una subclase de `Model` de Eloquent. `identifierFor()` lee la columna configurada y recae en `getKey()`.

Cinco resolvers concretos, todos en `Arqel\Tenant\Resolvers\` e intencionalmente **no** `final`, para que las apps puedan sobrescribir el parseo de host/cabecera:

| Clase | Fuente de resolución |
|---|---|
| `SubdomainResolver` | Primera etiqueta del host |
| `PathResolver` | Un segmento de la ruta URL |
| `HeaderResolver` | Una cabecera de la petición |
| `SessionResolver` | Clave de sesión. Sobrescribe `switchTo()` para persistir el valor de la columna identificadora en la **misma** clave de sesión que lee su `resolve()` |
| `AuthUserResolver` | `currentTeam` al estilo Jetstream sobre el usuario autenticado; acepta `availableRelation` + `foreignKeyColumn` |

## Middleware

### `Middleware\ResolveTenantMiddleware` (final, alias `arqel.tenant`)

`handle(Request $request, Closure $next, string $mode = self::MODE_REQUIRED)`. Dos constantes: `MODE_REQUIRED` (`'required'`) y `MODE_OPTIONAL` (`'optional'`). El parseo del modo no distingue mayúsculas y tolera espacios; un valor desconocido degrada a `required`. En modo required, un tenant sin resolver lanza `Exceptions\TenantNotFoundException`, cuyo `render()` devuelve un 404 JSON, la vista Inertia `arqel::errors.tenant-not-found`, o un 404 de Symfony como último recurso.

### `Middleware\RequireTenantFeature` (final, alias `arqel.tenant.feature`)

`handle(Request $request, Closure $next, string $feature)` — se usa como `'arqel.tenant.feature:analytics'`. Devuelve 404 sin tenant, 500 con un mensaje accionable cuando el modelo de tenant no tiene `hasFeature`, y `402` con JSON `{error: 'feature_not_available', feature, message}` cuando la feature está deshabilitada.

## Integración con Eloquent

### `Concerns\BelongsToTenant` (trait)

Registra `Scopes\TenantScope` y rellena automáticamente la clave foránea al crear. La clave se resuelve así: propiedad `$tenantForeignKey` → `config('arqel.tenancy.foreign_key')` → `'tenant_id'`.

| Método | Tipo | Descripción |
|---|---|---|
| `getTenantKeyName()` / `getQualifiedTenantKeyName()` | `string` | Clave foránea resuelta |
| `tenant()` | `BelongsTo` | |
| `scopeWithoutTenant(Builder $query)` | `Builder` | Elimina el global scope |
| `scopeForTenant(Builder $query, Model\|int\|string $tenant)` | `Builder` | Consulta cross-tenant explícita |

### `Scopes\TenantScope` (final, `implements Scope`)

`apply(Builder $builder, Model $model): void`. No hace nada, de forma elegante, cuando no hay tenant actual o el manager no está vinculado en el contenedor.

### `Rules\ScopedUnique` (final, `implements ValidationRule`)

Reemplazo consciente del tenant para la regla `unique` de Laravel. Constructor:

```php
new ScopedUnique(
    table: 'projects',
    column: 'slug',
    ignore: $project->id,        // por defecto null
    ignoreColumn: 'id',          // por defecto 'id'
    tenantForeignKey: null,      // por defecto: resuelto desde la config
    connection: null,
);
```

Aplica `where(<tenant_fk>, <id>)` cuando hay un tenant actual, y recae en una verificación de unicidad global en caso contrario. Antes de aplicar el filtro comprueba `hasColumn` sobre la tabla destino — si la columna de clave foránea del tenant no existe, el filtro se **omite** en lugar de producir un error "Unknown column". Cualquier fallo al inspeccionar el esquema trata la columna como presente (manteniendo el comportamiento acotado al tenant).

### `Concerns\HasFeatures` (trait)

`hasFeature(string): bool`, `enableFeature(string): void`, `disableFeature(string): void`, `getFeatures(): array`. Es defensivo ante un atributo `features` que no sea un array y deduplica; declara `$casts = ['features' => 'array']` en el modelo de tenant.

## Theming

### `Theming\TenantTheme` (final, value object readonly)

Cinco propiedades nulables: `primaryColor`, `logoUrl`, `fontFamily`, `secondaryColor`, `faviconUrl`. La factory `TenantTheme::fromTenant(?Model $tenant): self` lee los atributos canónicos de forma defensiva. `toArray(): array` e `isEmpty(): bool`.

### `Theming\TenantThemeResolver` (final, singleton)

`resolve(): TenantTheme` — construye el theme a partir de `TenantManager::current()`.

### `Theming\CssVarsRenderer` (final)

`CssVarsRenderer::renderInlineStyle(TenantTheme $theme): string` emite `<style>:root { --color-primary: …; }`. Cada slot se valida contra una allowlist según su contexto CSS (los colores aceptan hex / `rgb()` / `hsl()` / colores con nombre; `font_family` acepta letras, dígitos, espacio, coma, guion y comillas simples; las URLs deben ser `http(s)` o relativas a la raíz y se emiten como un `url('…')` escapado). Los valores que no pasan la allowlist se **omiten** — nunca se emiten sin escapar — lo que neutraliza payloads de inyección CSS que contengan `}`.

## Adaptadores

Ambos viven en `Arqel\Tenant\Integrations\` e implementan `TenantResolver`; cada uno está protegido por `class_exists`, de modo que ninguno de los paquetes de terceros se vuelve una dependencia dura.

| Clase | Comportamiento |
|---|---|
| `StanclAdapter` (final) | Lee `Stancl\Tenancy\Tenancy::tenant` (nombre del binding en la constante `TENANCY_BINDING`); respeta `getTenantKey()` con `getKey()` como fallback |
| `SpatieAdapter` (final) | Llama al `current()` estático de Spatie; un `modelClass` vacío recae en la constante `SPATIE_TENANT_CLASS` (`Spatie\Multitenancy\Models\Tenant`) |

## Eventos

Todos `final` con propiedades promovidas readonly:

| Evento | Payload |
|---|---|
| `Events\TenantResolved` | `Model $tenant` |
| `Events\TenantForgotten` | `Model $tenant` |
| `Events\TenantSwitched` | `?Model $from`, `Model $to`, `Authenticatable $user` |

## HTTP

Registradas bajo `web` + `auth` con el prefijo `admin/tenants`:

| Verbo | Ruta | Nombre | Acción |
|---|---|---|---|
| POST | `admin/tenants/{tenantId}/switch` | `arqel.tenant.switch` | `TenantSwitcherController::switch` — 404/403, luego dispatch + redirect |
| GET | `admin/tenants/available` | `arqel.tenant.available` | `TenantSwitcherController::list` — `{current, available[]}` |

## Comandos de Artisan

Cada scaffolder escribe tres stubs opcionales (controlador + snippet de rutas + página Inertia), es idempotente (omite con exit 0 salvo `--force`) y añade contenido a `routes/web.php` exactamente una vez, detrás de un marcador.

| Comando | Función |
|---|---|
| `arqel:tenant:scaffold-registration {--force}` | Flujo de alta de tenant |
| `arqel:tenant:scaffold-profile {--force}` | Ajustes del perfil del tenant |
| `arqel:tenant:scaffold-billing {--force}` | Esqueleto de la página de facturación |

## Ejemplo

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

Project::all();                     // acotado automáticamente al tenant actual
Project::withoutTenant()->get();    // vía de escape explícita
Project::forTenant($otherId)->get();

// Override de admin que preserva los lifecycle hooks / el rastro de auditoría:
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

## Relacionado

- SKILL: [`packages/tenant/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/tenant/SKILL.md)
- Código fuente: [`packages/tenant/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/tenant/src/)

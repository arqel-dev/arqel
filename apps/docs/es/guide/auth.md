# Auth

Arqel reutiliza **Policies, Gates y abilities de Laravel** sin un ACL paralelo. No hay un modelo `Role` propio ni tabla de permisos. Lo que sí existe es un `AbilityRegistry` para coordinar abilities globales entre PHP y React.

> El paquete `arqel-dev/auth` (que provee el middleware `EnsureUserCanAccessPanel`, el `AbilityRegistry`, etc.) ya está incluido en el meta-paquete `arqel-dev/framework` — basta con `composer require arqel-dev/framework` + `php artisan arqel:install`. No hace falta instalarlo por separado.

## Gate del panel

El middleware `EnsureUserCanAccessPanel` decide si el usuario puede acceder al panel:

```php
// app/Providers/ArqelServiceProvider.php
$panels->panel('admin')
    ->path('admin')
    ->middleware(['web', 'auth', EnsureUserCanAccessPanel::class]);
```

Por defecto el middleware verifica la ability `viewAdminPanel`. Si esa ability **no está registrada** con el Gate de Laravel, el middleware deja pasar la request — una instalación nueva arranca sin gating a nivel de panel. Para habilitarlo:

```php
// app/Providers/AuthServiceProvider.php
Gate::define('viewAdminPanel', fn (User $user) => $user->is_staff);
```

O usa una ability personalizada:

```php
->middleware([... EnsureUserCanAccessPanel::class . ':manageSettings']);
```

## Policies de Resource

`php artisan arqel:resource Post --with-policy` genera un `app/Policies/PostPolicy.php` vacío. Edítalo de la forma clásica de Laravel:

```php
final class PostPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Post $post): bool { return true; }
    public function create(User $user): bool { return $user->is_author; }
    public function update(User $user, Post $post): bool { return $user->id === $post->user_id; }
    public function delete(User $user, Post $post): bool { return $user->is_admin; }
}
```

`ResourceController` llama a `Gate::denies(viewAny|create|view|update|delete)` antes de cada acción y aborta con 403 al fallar. Si no tienes Policy registrada, el controller cae en "permitir silenciosamente" (las Policies de Resource son propiedad del usuario) — en producción, ten siempre una Policy.

## Auth a nivel de Field

```php
Field::text('salary')
    ->canSee(fn ($user, $record) => $user?->hasRole('hr'))
    ->canEdit(fn ($user, $record) => $user?->id === $record?->user_id);
```

`FieldSchemaSerializer` filtra el payload de Inertia: los fields que no pasan `canBeSeenBy` desaparecen del JSON enviado al cliente. `canBeEditedBy` cambia `readonly: true` — el input se renderiza pero no es editable.

::: warning Solo de UX
La auth a nivel de Field es **solo de UX** ([ADR-017](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md)). El servidor siempre re-valida vía `validated()` en el controller. Si necesitas auth fuerte, usa el `update()` de la Policy del Resource con un check fino.
:::

## Auth a nivel de Action

```php
RowAction::make('approve')
    ->authorize(fn ($user, $record) =>
        $user?->hasRole('manager') && $record->status === 'pending'
    );
```

`ActionController` llama a `Action::canBeExecutedBy($user, $record)` antes de ejecutar. True por defecto sin callback.

## `AbilityRegistry`

Registry global para abilities expuestas al cliente (vía la prop compartida `auth.can`):

```php
// app/Providers/AppServiceProvider.php
public function boot(AbilityRegistry $abilities): void
{
    $abilities->registerGlobals(['viewAdminPanel', 'manageBilling', 'exportData']);

    $abilities->registerComputed('canEditOrders', function (?User $user) {
        return $user?->hasRole('staff') && now()->isBusinessHours();
    });
}
```

Las globales se resuelven vía `Gate::forUser($user)->allows($ability)`. Las computed vía Closure.

En el cliente:

```tsx
import { useCanAccess } from '@arqel-dev/hooks';

function ExportButton() {
  const canExport = useCanAccess('exportData');
  if (!canExport) return null;
  return <button>Export CSV</button>;
}
```

`useCanAccess(ability, record?)` lee la prop compartida `auth.can` + chequea abilities a nivel de registro (que tienen precedencia sobre las globales).

## Helper `arqel_can`

En Blade, controllers o jobs:

```php
if (arqel_can('manageBilling')) {
    // ...
}
```

Orden de lookup: snapshot del `AbilityRegistry` (cuando está bound) primero, fallback a `Gate::forUser($user)->allows`.

## Componente `<CanAccess>`

Wrapper React idiomático:

```tsx
import { CanAccess } from '@arqel-dev/ui';

<CanAccess ability="manageBilling" fallback={<p>Nope.</p>}>
  <BillingSettings />
</CanAccess>
```

## Anti-patrones

- Re-implementar Spatie Permissions — Laravel-native (Gate + Policy) basta para el 95% de los casos
- Confiar en la auth a nivel de Field para ocultar datos de forma fuerte — solo UX; siempre Policy
- Hardcodear nombres de roles en un Resource — abstráelos en métodos del modelo User: `$user->canManagePosts()`

## Próximos pasos

- [Resources](/es/guide/resources) — declarar modelos como CRUDs
- Referencia API: [`packages/auth/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/auth/SKILL.md)
- ADR: [ADR-017 La autorización es solo de UX en el cliente](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md)

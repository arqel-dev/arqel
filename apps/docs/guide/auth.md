# Auth

Arqel reuses **Laravel Policies, Gates, and abilities** without a parallel ACL. There is no proprietary `Role` model or permissions table. What does exist is an `AbilityRegistry` to coordinate global abilities between PHP and React.

> The `arqel-dev/auth` package (which provides the `EnsureUserCanAccessPanel` middleware, the `AbilityRegistry`, etc.) is already included in the `arqel-dev/framework` meta-package — just `composer require arqel-dev/framework` + `php artisan arqel:install`. No need to install it separately.

## Panel gate

The `EnsureUserCanAccessPanel` middleware decides whether the user can access the panel:

```php
// app/Providers/ArqelServiceProvider.php
$panels->panel('admin')
    ->path('admin')
    ->middleware(['web', 'auth', EnsureUserCanAccessPanel::class]);
```

By default the middleware checks the `viewAdminPanel` ability. If that ability is **not registered** with the Laravel Gate, the middleware lets the request through — a fresh install boots without panel-level gating. To enable it:

```php
// app/Providers/AuthServiceProvider.php
Gate::define('viewAdminPanel', fn (User $user) => $user->is_staff);
```

Or use a custom ability:

```php
->middleware([... EnsureUserCanAccessPanel::class . ':manageSettings']);
```

## Resource Policies

`php artisan arqel:resource Post --with-policy` generates an empty `app/Policies/PostPolicy.php`. Edit it the classic Laravel way:

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

`ResourceController` calls `Gate::denies(viewAny|create|view|update|delete)` before each action and aborts with 403 on failure. If you have no Policy registered, the controller falls back to "silently allow" (Resource Policies are user-owned) — in production, always have a Policy.

## Field-level auth

```php
Field::text('salary')
    ->canSee(fn ($user, $record) => $user?->hasRole('hr'))
    ->canEdit(fn ($user, $record) => $user?->id === $record?->user_id);
```

`FieldSchemaSerializer` filters the Inertia payload: fields that don't pass `canBeSeenBy` disappear from the JSON sent to the client. `canBeEditedBy` flips `readonly: true` — the input renders but is not editable.

::: warning UX-only
Field-level auth is **UX-only** ([ADR-017](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md)). The server always re-validates via `validated()` in the controller. If you need hard auth, use the Resource Policy `update()` with a fine-grained check.
:::

## Action-level auth

```php
RowAction::make('approve')
    ->authorize(fn ($user, $record) =>
        $user?->hasRole('manager') && $record->status === 'pending'
    );
```

`ActionController` calls `Action::canBeExecutedBy($user, $record)` before executing. Default true with no callback.

## `AbilityRegistry`

Global registry for abilities exposed to the client (via the `auth.can` shared prop):

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

Globals are resolved via `Gate::forUser($user)->allows($ability)`. Computed via Closure.

On the client:

```tsx
import { useCanAccess } from '@arqel-dev/hooks';

function ExportButton() {
  const canExport = useCanAccess('exportData');
  if (!canExport) return null;
  return <button>Export CSV</button>;
}
```

`useCanAccess(ability, record?)` reads the `auth.can` shared prop + checks record-level abilities (which take precedence over globals).

## `arqel_can` helper

In Blade, controllers, or jobs:

```php
if (arqel_can('manageBilling')) {
    // ...
}
```

Lookup order: `AbilityRegistry` snapshot (when bound) first, fallback to `Gate::forUser($user)->allows`.

## `<CanAccess>` component

Idiomatic React wrapper:

```tsx
import { CanAccess } from '@arqel-dev/ui';

<CanAccess ability="manageBilling" fallback={<p>Nope.</p>}>
  <BillingSettings />
</CanAccess>
```

## Anti-patterns

- Re-implementing Spatie Permissions — Laravel-native (Gate + Policy) is enough for 95% of cases
- Trusting field-level auth for hard data hiding — UX-only; always Policy
- Hardcoding role names in a Resource — abstract them in User model methods: `$user->canManagePosts()`

## Next steps

- [Resources](/guide/resources) — declare models as CRUDs
- API reference: [`packages/auth/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/auth/SKILL.md)
- ADR: [ADR-017 Authorization is UX-only on the client](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md)

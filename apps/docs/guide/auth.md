# Auth

Arqel reusa **Policies, Gates e abilities do Laravel** sem ACL paralela. Não há `Role` model próprio, nem permissions table. O que existe é um `AbilityRegistry` para coordenar abilities globais entre PHP e React.

## Gate de panel

Middleware `EnsureUserCanAccessPanel` decide se o user pode acessar o panel:

```php
// app/Providers/ArqelServiceProvider.php
$panels->panel('admin')
    ->path('admin')
    ->middleware(['web', 'auth', EnsureUserCanAccessPanel::class]);
```

Por defeito, o middleware checa a ability `viewAdminPanel`. Se essa ability **não estiver registrada** com Laravel Gate, o middleware deixa passar — uma fresh install boota sem panel-level gating. Para activar:

```php
// app/Providers/AuthServiceProvider.php
Gate::define('viewAdminPanel', fn (User $user) => $user->is_staff);
```

Ou use uma ability custom:

```php
->middleware([... EnsureUserCanAccessPanel::class . ':manageSettings']);
```

## Resource Policies

`php artisan arqel:resource Post --with-policy` gera `app/Policies/PostPolicy.php` vazia. Edite-a com Laravel-classic:

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

`ResourceController` chama `Gate::denies(viewAny|create|view|update|delete)` antes de cada action e aborta 403 se falhar. Se você não tem Policy registrada, o controller faz "silently allow" (Resource Policies são user-owned) — em prod, sempre tenha Policy.

## Field-level auth

```php
Field::text('salary')
    ->canSee(fn ($user, $record) => $user?->hasRole('hr'))
    ->canEdit(fn ($user, $record) => $user?->id === $record?->user_id);
```

`FieldSchemaSerializer` filtra o payload Inertia: fields que não passam em `canBeSeenBy` somem do JSON enviado ao client. `canBeEditedBy` flipa `readonly: true` — o input renderiza, mas é não-editável.

::: warning UX-only
Field-level auth é **UX-only** ([ADR-017](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md)). O servidor sempre re-valida via `validated()` no controller. Se você precisa de hard auth, use Resource Policy `update()` com check fine-grained.
:::

## Action-level auth

```php
RowAction::make('approve')
    ->authorize(fn ($user, $record) =>
        $user?->hasRole('manager') && $record->status === 'pending'
    );
```

`ActionController` chama `Action::canBeExecutedBy($user, $record)` antes de executar. Default true sem callback.

## `AbilityRegistry`

Registry global para abilities expostas ao client (via `auth.can` shared prop):

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

Globals são resolvidas via `Gate::forUser($user)->allows($ability)`. Computed via Closure.

No client:

```tsx
import { useCanAccess } from '@arqel-dev/hooks';

function ExportButton() {
  const canExport = useCanAccess('exportData');
  if (!canExport) return null;
  return <button>Export CSV</button>;
}
```

`useCanAccess(ability, record?)` lê `auth.can` shared prop + checks record-level abilities (têm precedência sobre globals).

## Helper `arqel_can`

Em Blade, controllers ou jobs:

```php
if (arqel_can('manageBilling')) {
    // ...
}
```

Lookup order: `AbilityRegistry` snapshot (quando bound) primeiro, fallback para `Gate::forUser($user)->allows`.

## `<CanAccess>` component

Wrapper React idiomático:

```tsx
import { CanAccess } from '@arqel-dev/ui';

<CanAccess ability="manageBilling" fallback={<p>Nope.</p>}>
  <BillingSettings />
</CanAccess>
```

## Anti-patterns

- ❌ **Re-implementar Spatie Permissions** — Laravel-native (Gate + Policy) é suficiente para 95% dos casos
- ❌ **Confiar em field-level auth para hard data hiding** — UX-only; sempre Policy
- ❌ **Hardcode role names em Resource** — abstraia em métodos da User model: `$user->canManagePosts()`

## Próximos passos

- [Resources](/guide/resources) — declarar models como CRUDs
- API reference: [`packages/auth/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/auth/SKILL.md)
- ADR: [ADR-017 Authorization is UX-only on the client](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md)

# SKILL.md — arqel-dev/nav

> Contexto canónico para AI agents. Estrutura conforme `PLANNING/04-repo-structure.md` §11.

## Purpose

`arqel-dev/nav` declara o menu lateral do panel admin: **items** individuais (link, ícone, badge), **groups** colapsáveis e **dividers**, com auto-registo a partir das Resources do Panel. Também produz **breadcrumbs** a partir do nome de rota Inertia + parâmetros.

## Status

**Entregue (NAV-001..004):**

- `Arqel\Nav\NavServiceProvider` — auto-discovery, regista `Navigation` e `BreadcrumbsBuilder` como singletons
- `Arqel\Nav\NavigationItem` final — fluent API, `make(label)` + `resource(class)` factory, badge int/string/Closure, visibility per-user
- `Arqel\Nav\NavigationGroup` final — colapsável, items ordenados por sort, visibility per-user
- `Arqel\Nav\Navigation` final — `item()`/`group()`/`addGroup()`/`divider()`/`autoRegisterFromResources()`/`build($user)` ordenado e filtrado
- `Arqel\Nav\BreadcrumbsBuilder` final — `withResources(array)` + `buildFor(routeName, params)` resolvendo index/create/edit/show
- 24 testes Pest passando

**Por chegar:**

- Integração com `arqel-dev/core` `ArqelServiceProvider` para chamar `autoRegisterFromResources(ResourceRegistry::all())` por defeito (NAV-005)
- Customização de breadcrumbs via override em Resource (NAV-005)

## Key Contracts

### `NavigationItem`

```php
NavigationItem::make('Dashboard')
    ->icon('home')
    ->url('/admin')                       // ou ->route('arqel.admin.dashboard', [...])
    ->openInNewTab()
    ->badge(fn () => $unreadCount)        // int|string|Closure (closures resolvidas em serialize-time)
    ->badgeColor('warning')
    ->visible(fn ($user) => $user?->is_admin)
    ->sort(0);
```

A factory `NavigationItem::resource(UserResource::class)` extrai automaticamente label (`getPluralLabel`), icon (`getNavigationIcon`), URL (de `getSlug` → `/users`) e sort (`getNavigationSort`). Useful para eliminar boilerplate.

### `NavigationGroup`

```php
NavigationGroup::make('People')
    ->icon('users')
    ->collapsible(true)
    ->collapsed(false)
    ->sort(10)
    ->items([
        NavigationItem::resource(UserResource::class),
        NavigationItem::resource(RoleResource::class),
    ])
    ->visible(fn ($user) => $user?->can('manage-people'));
```

`collapsed()` activa automaticamente `collapsible()`. Itens não-`NavigationItem` passados a `items([...])` são descartados graciosamente.

### `Navigation`

```php
$nav = (new Navigation)
    ->item(NavigationItem::make('Dashboard')->url('/admin')->sort(0))
    ->group('Content', function (NavigationGroup $g): void {
        $g->items([
            NavigationItem::resource(PostResource::class),
            NavigationItem::resource(CategoryResource::class),
        ]);
    })
    ->divider()
    ->autoRegisterFromResources([UserResource::class, RoleResource::class]);

$payload = $nav->build(auth()->user());
// [
//   ['kind' => 'item', 'label' => 'Dashboard', 'url' => '/admin', ...],
//   ['kind' => 'group', 'label' => 'Content', 'items' => [...]],
//   ['kind' => 'divider'],
//   ['kind' => 'group', 'label' => 'People', 'items' => [...]],   // do auto-register
// ]
```

`autoRegister(false)` desactiva o auto-registo. Resources já registados manualmente (por classe) não são duplicados.

### `BreadcrumbsBuilder`

```php
$builder = (new BreadcrumbsBuilder)->withResources($panel->getResources());

$builder->buildFor('arqel.admin.users.index');
// [['label' => 'Users', 'url' => null]]

$builder->buildFor('arqel.admin.users.create');
// [['label' => 'Users', 'url' => '/users'], ['label' => 'Create', 'url' => null]]

$builder->buildFor('arqel.admin.users.edit', ['id' => 42, 'record' => $user]);
// [['label' => 'Users', 'url' => '/users'], ['label' => 'John Doe', 'url' => '/users/42'], ['label' => 'Edit', 'url' => null]]
```

Resolution rules:
- `parseRouteName` pega os dois últimos segmentos (`{resource}.{action}`)
- `record`/`model` em `params` que sejam `Model` interpolam `Resource::recordTitle($record)`
- Se não encontrar Resource correspondente, usa `ucfirst($slug)` como label

## Conventions

- `declare(strict_types=1)` obrigatório
- Classes finais — extensibilidade nasce em NAV-005+ se houver pedido
- `Navigation::build()` é stateless por chamada — pode ser chamado por user diferente sem efeitos colaterais; cache fica do lado da Inertia middleware
- Closures (visibility, badge) são resolvidas em serialize-time — devem ser puras (sem side effects DB pesados)

## Anti-patterns

- ❌ **Queries DB pesadas em `badge(Closure)`** — corre por user por request. Usa `cache()->remember()` ou eager-load no middleware
- ❌ **Definir items inline no Blade/React** — schema é PHP-side. Mantém declarativo
- ❌ **`Closure` em badge retornando array/object** — só `int|string` são serializáveis; outros são descartados como `null`
- ❌ **Resources auto-registados sem `getNavigationGroup()`** — vão para top-level. Usa override `null` se intencional, mas considera consistência
- ❌ **Passar `record` como string ID + Model** — escolhe um. `record` Model tem precedência sobre `id` scalar para resolveRecordTitle

## Related

- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §NAV-001..005
- ADRs: [ADR-001](../../PLANNING/03-adrs.md) Inertia-only · [ADR-008](../../PLANNING/03-adrs.md) Pest 3
- API: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md) §Navigation
- Source: [`packages/nav/src/`](src/)
- Tests: [`packages/nav/tests/`](tests/)

# `arqel-dev/core` — API Reference

Namespace `Arqel\Core\`. The root package: Resources, Panel, Inertia bridge, HTTP controller.

## Resources

### `Arqel\Core\Resources\Resource` (abstract)

Base class for user Resources. Subclasses only need to declare `protected static string $model` and `public function fields(): array`.

| Method | Type | Description |
|---|---|---|
| `getModel()` | `class-string<Model>` | Model FQN class. Throws `LogicException` if `$model` is not declared |
| `getSlug()` | `string` | Slug derived from the name (`UserResource` → `users`) or `$slug` override |
| `getLabel()` / `getPluralLabel()` | `string` | Auto-derived labels or override |
| `getNavigationIcon()` / `getNavigationGroup()` / `getNavigationSort()` | `?string` / `?string` / `?int` | Sidebar metadata |
| `fields()` | `array<Field>` | List of fields (abstract — declare it) |
| `table()` | `mixed` | Optional. Return `Arqel\Table\Table` when you need custom behavior |
| `form()` | `mixed` | Optional. Return `Arqel\Form\Form` when you need custom behavior |
| `recordTitle(Model)` / `recordSubtitle(Model)` | `string` / `?string` | Identifier shown in breadcrumbs/modals |
| `indexQuery(): mixed` | `?Builder` | Return a scoped query Builder, or null to fall back to `getModel()::query()` |

**Lifecycle hooks** (all `protected`, override on the subclass):

```php
beforeCreate(array $data): array                // mutate-and-return $data
afterCreate(Model $record): void
beforeUpdate(Model $record, array $data): array // mutate-and-return $data
afterUpdate(Model $record): void
beforeSave(Model $record, array $data): array   // create OR update; mutate-and-return $data
afterSave(Model $record): void
beforeDelete(Model $record): void
afterDelete(Model $record): void                // only fires if delete() returned truthy
```

**Orchestrators** (public, called by `ResourceController`):

```php
runCreate(array $data): Model
runUpdate(Model $record, array $data): Model
runDelete(Model $record): bool
```

### `Arqel\Core\Resources\ResourceRegistry` (final)

Singleton. Bound automatically in `ArqelServiceProvider`.

| Method | Description |
|---|---|
| `register(class-string)` | Registers a Resource. Validates `is_subclass_of HasResource` |
| `registerMany(array<class-string>)` | Bulk |
| `discover(string $path, string $namespace)` | Auto-discover via PSR-4 + Symfony Finder |
| `findByModel(class-string<Model>)` | `?class-string<Resource>` |
| `findBySlug(string)` | `?class-string<Resource>` |
| `has(class-string)` / `clear()` / `all()` | Utilities |

## Panel

### `Arqel\Core\Panel\Panel` (final)

Fluent builder. Created via `PanelRegistry::panel($id)`.

```php
$panels->panel('admin')
    ->path('admin')
    ->brand('Acme')
    ->theme('default')
    ->primaryColor('#6366f1')
    ->darkMode(true)
    ->middleware(['web', 'auth'])
    ->resources([UserResource::class])
    ->widgets([])
    ->navigationGroups([])
    ->authGuard('web')
    ->tenant(null);   // Phase 2
```

Each setter returns `$this`. Typed getters exist for all of them: `getPath(): string`, `getBrand(): ?string`, etc.

### `Arqel\Core\Panel\PanelRegistry` (final)

Singleton. Methods: `panel(id): Panel` (create-or-get), `setCurrent(id)`, `getCurrent(): ?Panel`, `all()`, `has(id)`, `clear()`. `setCurrent` on an unknown ID throws `PanelNotFoundException`.

## Contracts

| Interface | Implementers | Methods |
|---|---|---|
| `HasResource` | `Resource` | 7 statics: `getModel`, `getSlug`, `getLabel`, `getPluralLabel`, `getNavigationIcon`, `getNavigationGroup`, `getNavigationSort` |
| `HasFields` | `Resource` | `fields(): array` |
| `HasActions` | `Resource` | marker (no method) |
| `HasPolicies` | (optional) | `getPolicy(): ?class-string` for Policy override |

## HTTP

### `Arqel\Core\Http\Controllers\ResourceController` (final)

7 polymorphic endpoints: `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`. Resolves Resource by `{resource}` slug, authorizes via `Gate::denies(viewAny|create|view|update|delete)`, delegates to `InertiaDataBuilder` to serialize the payload.

### `Arqel\Core\Http\Middleware\HandleArqelInertiaRequests` (final)

Extends `Inertia\Middleware`. Shared props: `auth.user` (`only(['id','name','email'])`), `auth.can` (via `AbilityRegistry`), `panel`, `panel.navigation`, `tenant`, `flash`, `translations`, `arqel.version`.

The `buildNavigation()` method populates the `panel.navigation` shared prop from registered Resources (`ResourceRegistry::all()`), grouping by `getNavigationGroup()` and ordering by `getNavigationSort()`. Each item carries `{ label, url, icon, group, sort, active }` — consumed on the client by `useNavigation()`.

This middleware is published to `app/Http/Middleware/HandleArqelInertiaRequests.php` during `arqel:install` to allow user override.

## Support

### `Arqel\Core\Support\InertiaDataBuilder` (final)

Assembler for the index/create/edit/show payloads. Methods: `buildIndexData`, `buildCreateData`, `buildEditData`, `buildShowData`. Detects `Resource::table()` returning `Arqel\Table\Table` via duck-typing and routes to `buildTableIndexData` (delegates to `TableQueryBuilder` via Reflection — no hard dep).

### `Arqel\Core\Support\FieldSchemaSerializer` (final)

Central serializer for Fields into the Inertia payload (canonical shape in [`06-api-react.md` §4](https://github.com/arqel-dev/arqel/blob/main/PLANNING/06-api-react.md)). Duck-typed against `Arqel\Fields\Field` — no hard dep. Filters fields via `canBeSeenBy(user, record)`.

## Artisan commands

| Command | Function |
|---|---|
| `arqel:install` | Initial scaffold (see detail below) |
| `arqel:make-user {--name=} {--email=} {--password=}` | Creates an admin user interactively (`filament:make-user` style) |
| `arqel:resource {model} {--with-policy}` | Generates a Resource from a short model FQN |

### `arqel:install` — detail

Run once per app. Operations:

- Auto-registers `ArqelServiceProvider` in `bootstrap/providers.php` (Laravel 11+ structure)
- Publishes `HandleArqelInertiaRequests` to `app/Http/Middleware/` and registers it in the HTTP kernel
- Publishes `vite.config.ts` with Arqel aliases + React/Tailwind v4 plugins
- Scaffolds `app/Arqel/Resources/UserResource.php` pointing to the app's `User` model
- Publishes auth assets (`public/arqel/login-hero.svg`)
- Creates `config/arqel.php`, `resources/js/Pages/Arqel/`, the root layout, and `AGENTS.md`
- Adds npm/composer scripts to `package.json` and `composer.json`

### `arqel:make-user`

Interactive command (or via flags) that creates a `User` with `email_verified_at` filled and password hashed via `Hash::make`. For panel-level gates (e.g. `viewAdminPanel`), the operator must register the ability separately — `make-user` only creates the record.

## Relation Managers

Manages a parent record's Eloquent relation from a tab on the parent's edit page (`hasMany`/`morphMany`/`belongsToMany`).

### `Arqel\Core\Relations\RelationManager` (abstract)

| Member | Type | Description |
|---|---|---|
| `$relationship` | `static string` | Eloquent relation method name on the parent model |
| `table()` | `mixed` (abstract) | Returns `Arqel\Table\Table`-shaped object listing the related records |
| `fields()` | `array<Field>` | Default `[]`. Validation + form source, mirrors `Resource::fields()` |
| `form()` | `mixed` | Default `null`. Optional `Arqel\Form\Form`-shaped object for create/edit |
| `relatedResource()` | `?class-string` | Default `null`. Optional FQCN of the related model's Resource |
| `slug()` | `string` | `Str::snake($relationship)` |
| `label()` | `string` | `Str::headline(slug())` |
| `relationType(Model $parent)` | `'hasMany'\|'morphMany'\|'belongsToMany'` | Detected at runtime from `$parent->{$relationship}()`. Throws `InvalidArgumentException` for unsupported types (`MorphTo`/`HasManyThrough` out of scope) |
| `supportsAttach(Model $parent)` | `bool` | `true` only for `belongsToMany` |
| `pivotFields()` | `array<string>` | Default `[]`. Allowlist of pivot columns a client may set on `attach` — anything not listed is dropped |
| `abilities(Model $parent, ?Authenticatable $user)` | `array{create,update,delete,attach,detach: bool}` | Fail-open only when neither a Gate rule nor a Policy exists for the related model |
| `toArray(Model $parent, ?Authenticatable $user = null)` | `array` | `{ slug, label, type, table, fields, abilities }` — the Inertia payload shape |

`table()`/`form()` return `mixed` for the same dependency-inversion reason as `Resource::table()`/`form()`: `arqel-dev/core` does not depend on `arqel-dev/table`/`arqel-dev/form`.

Declare relation managers on a `Resource`:

```php
// UserResource.php
public function relations(): array
{
    return [CommentsRelationManager::class];
}
```

`Resource::getRelations(): array<string, RelationManager>` instantiates the declared classes and keys them by `slug()`; it throws `InvalidArgumentException` if a listed class doesn't extend `RelationManager`.

### `Arqel\Core\Http\Controllers\RelationController` (final)

8 endpoints registered under `arqel.resources.relations.*`, all relation-scoped:

| Verb | Route | Name | Action |
|---|---|---|---|
| GET | `{resource}/{parent}/relations/{relation}` | `relations.index` | List related records |
| GET | `{resource}/{parent}/relations/{relation}/create` | `relations.create` | Form schema for the create modal |
| POST | `{resource}/{parent}/relations/{relation}` | `relations.store` | Create a child record |
| GET | `{resource}/{parent}/relations/{relation}/{related}/edit` | `relations.edit` | Form schema + data for the edit modal |
| PUT | `{resource}/{parent}/relations/{relation}/{related}` | `relations.update` | Update a child record |
| DELETE | `{resource}/{parent}/relations/{relation}/{related}` | `relations.destroy` | Delete a child record |
| POST | `{resource}/{parent}/relations/{relation}/attach` | `relations.attach` | `belongsToMany`: associate an existing record via pivot |
| DELETE | `{resource}/{parent}/relations/{relation}/{related}/detach` | `relations.detach` | `belongsToMany`: disassociate (does not delete) |

`{relation}` is validated against the allowlist of `Resource::getRelations()` (404 otherwise). `{related}` is always scoped by `{parent}` (anti-IDOR). `attach`/`detach` on a `hasMany`/`morphMany` relation return `405`. Authorization is two-tier fail-open against the **related** model's Policy (same semantics as `ResourceController::authorize()`); `attach`/`detach` fall back to `create`/`delete` when no bespoke ability is registered.

## Plugin API

Lets a package or app inject content into a `Panel` programmatically — resources, navigation groups, middleware — without editing the panel definition directly.

### `Arqel\Core\Contracts\Plugin` (interface)

| Method | Description |
|---|---|
| `getId(): string` | Stable, unique id per panel. Registering the same id twice replaces the previous plugin |
| `register(Panel $panel): void` | Mutates the Panel. Runs eagerly, at the moment `Panel::plugin()` is called |
| `boot(Panel $panel): void` | Runs after all plugins have registered, before the resource sync — resources added here still become routes |

### `Arqel\Core\Panel\Concerns\CreatesPlugin` (trait)

Optional sugar: `use CreatesPlugin;` adds a static `make(): static` factory (`new static`) for the fluent `Panel::plugin(MyPlugin::make())` call — not required to satisfy the `Plugin` contract.

### `Arqel\Core\Panel\Panel` — plugin methods

| Method | Description |
|---|---|
| `plugin(Plugin $plugin): self` | Registers a single plugin (keyed by `getId()`) and immediately calls `register($this)` |
| `plugins(array<Plugin> $plugins): self` | Bulk `plugin()` |
| `getPlugins(): array<string, Plugin>` | All registered plugins, keyed by id |
| `getPlugin(string $id): ?Plugin` | Lookup by id |

## Database Notifications

Native Laravel `database` notification channel, with a bell + history UI on top of the standard `notifications` table.

- Shared Inertia prop `notifications` (`NotificationPayload | null`) — **public and stable**, built by `HandleArqelInertiaRequests::notificationsPayload()`. `null` when no user is authenticated or the user model doesn't expose `notifications()` (`Notifiable` trait). Shape: `{ unread_count: int, recent: array }`, where each `recent` entry's `data` field follows the stable key convention `title`/`body`/`action_url`/`icon`.
- `Arqel\Core\Http\Controllers\NotificationController` is `@internal` (may change in any minor) — `index`/`markAsRead`/`markAllAsRead`/`destroy`, always scoped to `$user->notifications()` (anti-IDOR: another owner's id resolves 404 via `findOrFail`).
- Routes: `GET /admin/notifications`, `POST /admin/notifications/read-all`, `POST /admin/notifications/{notification}/read`, `DELETE /admin/notifications/{notification}`.

## Global Search

### `Resource::globallySearchable(): array<string>` (static, optional)

Opt-in: a Resource declares which model column names are searchable from the command palette. Resources that don't override it (default `[]`) never appear in global search results.

```php
public static function globallySearchable(): array
{
    return ['title', 'slug'];
}
```

Consumed by `Arqel\Core\CommandPalette\Providers\RecordSearchCommandProvider`, which runs a scoped `LIKE` query (SQL-bound, `%`/`_`/`\` escaped) against the declared columns, filters results by each resource's `viewAny` Policy, and emits one `Command` per matched record (category `'Records'`, linking to the record's edit page). Label uses the optional `Resource::globalSearchResultTitle(Model $record): string` override, falling back to `'#' . $record->getKey()`.

## Related

- SKILL: [`packages/core/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/core/SKILL.md)
- Source: [`packages/core/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/core/src/)
- Next: [`arqel-dev/fields`](/reference/php/fields)

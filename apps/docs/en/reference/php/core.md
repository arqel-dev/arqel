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
| `actions()` | `array<Action>` | Optional. Empty default |
| `recordTitle(Model)` / `recordSubtitle(Model)` | `string` / `?string` | Identifier shown in breadcrumbs/modals |
| `indexQuery(Builder)` | `Builder` | Hook to scope the listing |

**Lifecycle hooks** (all `protected`, override on the subclass):

```php
beforeCreate(Model $record, array $data): void
afterCreate(Model $record): void
beforeUpdate(Model $record, array $data): void
afterUpdate(Model $record): void
beforeSave(Model $record, array $data): void   // create OR update
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
| `discover(string $namespace, string $path)` | Auto-discover via PSR-4 + Symfony Finder |
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

## Related

- SKILL: [`packages/core/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/core/SKILL.md)
- Source: [`packages/core/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/core/src/)
- Next: [`arqel-dev/fields`](/reference/php/fields)

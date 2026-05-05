# `arqel-dev/core` — Referencia de API

Namespace `Arqel\Core\`. El paquete raíz: Resources, Panel, puente Inertia, controlador HTTP.

## Resources

### `Arqel\Core\Resources\Resource` (abstract)

Clase base para los Resources del usuario. Las subclases solo necesitan declarar `protected static string $model` y `public function fields(): array`.

| Método | Tipo | Descripción |
|---|---|---|
| `getModel()` | `class-string<Model>` | Clase FQN del modelo. Lanza `LogicException` si `$model` no está declarado |
| `getSlug()` | `string` | Slug derivado del nombre (`UserResource` → `users`) o sobrescrito vía `$slug` |
| `getLabel()` / `getPluralLabel()` | `string` | Labels auto-derivados o sobrescritos |
| `getNavigationIcon()` / `getNavigationGroup()` / `getNavigationSort()` | `?string` / `?string` / `?int` | Metadata del Sidebar |
| `fields()` | `array<Field>` | Lista de fields (abstract — debes declararlo) |
| `table()` | `mixed` | Opcional. Retorna `Arqel\Table\Table` cuando necesitas comportamiento personalizado |
| `form()` | `mixed` | Opcional. Retorna `Arqel\Form\Form` cuando necesitas comportamiento personalizado |
| `actions()` | `array<Action>` | Opcional. Vacío por defecto |
| `recordTitle(Model)` / `recordSubtitle(Model)` | `string` / `?string` | Identificador mostrado en breadcrumbs/modales |
| `indexQuery(Builder)` | `Builder` | Hook para acotar el listado |

**Lifecycle hooks** (todos `protected`, sobrescribe en la subclase):

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

**Orquestadores** (públicos, llamados por `ResourceController`):

```php
runCreate(array $data): Model
runUpdate(Model $record, array $data): Model
runDelete(Model $record): bool
```

### `Arqel\Core\Resources\ResourceRegistry` (final)

Singleton. Vinculado automáticamente en `ArqelServiceProvider`.

| Método | Descripción |
|---|---|
| `register(class-string)` | Registra un Resource. Valida `is_subclass_of HasResource` |
| `registerMany(array<class-string>)` | En lote |
| `discover(string $namespace, string $path)` | Auto-descubrir vía PSR-4 + Symfony Finder |
| `findByModel(class-string<Model>)` | `?class-string<Resource>` |
| `findBySlug(string)` | `?class-string<Resource>` |
| `has(class-string)` / `clear()` / `all()` | Utilidades |

## Panel

### `Arqel\Core\Panel\Panel` (final)

Builder fluido. Creado vía `PanelRegistry::panel($id)`.

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

Cada setter retorna `$this`. Existen getters tipados para todos: `getPath(): string`, `getBrand(): ?string`, etc.

### `Arqel\Core\Panel\PanelRegistry` (final)

Singleton. Métodos: `panel(id): Panel` (create-or-get), `setCurrent(id)`, `getCurrent(): ?Panel`, `all()`, `has(id)`, `clear()`. `setCurrent` con un ID desconocido lanza `PanelNotFoundException`.

## Contracts

| Interface | Implementadores | Métodos |
|---|---|---|
| `HasResource` | `Resource` | 7 statics: `getModel`, `getSlug`, `getLabel`, `getPluralLabel`, `getNavigationIcon`, `getNavigationGroup`, `getNavigationSort` |
| `HasFields` | `Resource` | `fields(): array` |
| `HasActions` | `Resource` | marker (sin método) |
| `HasPolicies` | (opcional) | `getPolicy(): ?class-string` para sobrescribir Policy |

## HTTP

### `Arqel\Core\Http\Controllers\ResourceController` (final)

7 endpoints polimórficos: `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`. Resuelve el Resource por slug `{resource}`, autoriza vía `Gate::denies(viewAny|create|view|update|delete)`, delega a `InertiaDataBuilder` para serializar el payload.

### `Arqel\Core\Http\Middleware\HandleArqelInertiaRequests` (final)

Extiende `Inertia\Middleware`. Props compartidos: `auth.user` (`only(['id','name','email'])`), `auth.can` (vía `AbilityRegistry`), `panel`, `panel.navigation`, `tenant`, `flash`, `translations`, `arqel.version`.

El método `buildNavigation()` puebla el prop compartido `panel.navigation` a partir de los Resources registrados (`ResourceRegistry::all()`), agrupando por `getNavigationGroup()` y ordenando por `getNavigationSort()`. Cada item lleva `{ label, url, icon, group, sort, active }` — consumido en el cliente por `useNavigation()`.

Este Middleware se publica en `app/Http/Middleware/HandleArqelInertiaRequests.php` durante `arqel:install` para permitir override del usuario.

## Support

### `Arqel\Core\Support\InertiaDataBuilder` (final)

Ensamblador para los payloads de index/create/edit/show. Métodos: `buildIndexData`, `buildCreateData`, `buildEditData`, `buildShowData`. Detecta `Resource::table()` retornando `Arqel\Table\Table` vía duck-typing y enruta a `buildTableIndexData` (delega a `TableQueryBuilder` vía Reflection — sin dependencia dura).

### `Arqel\Core\Support\FieldSchemaSerializer` (final)

Serializador central para los Fields hacia el payload de Inertia (forma canónica en [`06-api-react.md` §4](https://github.com/arqel-dev/arqel/blob/main/PLANNING/06-api-react.md)). Duck-typed contra `Arqel\Fields\Field` — sin dependencia dura. Filtra fields vía `canBeSeenBy(user, record)`.

## Comandos Artisan

| Comando | Función |
|---|---|
| `arqel:install` | Scaffold inicial (ver detalle abajo) |
| `arqel:make-user {--name=} {--email=} {--password=}` | Crea un usuario admin interactivamente (estilo `filament:make-user`) |
| `arqel:resource {model} {--with-policy}` | Genera un Resource desde un FQN corto del modelo |

### `arqel:install` — detalle

Ejecutar una vez por app. Operaciones:

- Auto-registra `ArqelServiceProvider` en `bootstrap/providers.php` (estructura Laravel 11+)
- Publica `HandleArqelInertiaRequests` en `app/Http/Middleware/` y lo registra en el HTTP kernel
- Publica `vite.config.ts` con aliases de Arqel + plugins React/Tailwind v4
- Genera el scaffold de `app/Arqel/Resources/UserResource.php` apuntando al modelo `User` de la app
- Publica los assets de auth (`public/arqel/login-hero.svg`)
- Crea `config/arqel.php`, `resources/js/Pages/Arqel/`, el layout raíz y `AGENTS.md`
- Añade scripts de npm/composer en `package.json` y `composer.json`

### `arqel:make-user`

Comando interactivo (o vía flags) que crea un `User` con `email_verified_at` rellenado y password hasheado vía `Hash::make`. Para gates a nivel de Panel (e.g. `viewAdminPanel`), el operador debe registrar la ability por separado — `make-user` solo crea el registro.

## Relacionado

- SKILL: [`packages/core/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/core/SKILL.md)
- Source: [`packages/core/src/`](https://github.com/arqel-dev/arqel/blob/main/packages/core/src/)
- Siguiente: [`arqel-dev/fields`](/es/reference/php/fields)

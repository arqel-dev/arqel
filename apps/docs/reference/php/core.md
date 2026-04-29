# `arqel/core` — API Reference

Namespace `Arqel\Core\`. Pacote raiz: Resources, Panel, Inertia bridge, controller HTTP.

## Resources

### `Arqel\Core\Resources\Resource` (abstract)

Base para Resources do utilizador. Subclasses só precisam declarar `protected static string $model` e `public function fields(): array`.

| Método | Tipo | Descrição |
|---|---|---|
| `getModel()` | `class-string<Model>` | Class FQN do model. Lança `LogicException` se `$model` não declarado |
| `getSlug()` | `string` | Slug derivado do nome (`UserResource` → `users`) ou override `$slug` |
| `getLabel()` / `getPluralLabel()` | `string` | Labels auto-derivados ou override |
| `getNavigationIcon()` / `getNavigationGroup()` / `getNavigationSort()` | `?string` / `?string` / `?int` | Sidebar metadata |
| `fields()` | `array<Field>` | Lista de fields (abstract — declarar) |
| `table()` | `mixed` | Opcional. Retornar `Arqel\Table\Table` quando precisar custom |
| `form()` | `mixed` | Opcional. Retornar `Arqel\Form\Form` quando precisar custom |
| `actions()` | `array<Action>` | Opcional. Default vazio |
| `recordTitle(Model)` / `recordSubtitle(Model)` | `string` / `?string` | Identificador exibido em breadcrumbs/modais |
| `indexQuery(Builder)` | `Builder` | Hook para escopar listagem |

**Lifecycle hooks** (todos `protected`, override na subclass):

```php
beforeCreate(Model $record, array $data): void
afterCreate(Model $record): void
beforeUpdate(Model $record, array $data): void
afterUpdate(Model $record): void
beforeSave(Model $record, array $data): void   // create OR update
afterSave(Model $record): void
beforeDelete(Model $record): void
afterDelete(Model $record): void                // só dispara se delete() retornou truthy
```

**Orchestrators** (públicos, chamados pelo `ResourceController`):

```php
runCreate(array $data): Model
runUpdate(Model $record, array $data): Model
runDelete(Model $record): bool
```

### `Arqel\Core\Resources\ResourceRegistry` (final)

Singleton. Bound automaticamente em `ArqelServiceProvider`.

| Método | Descrição |
|---|---|
| `register(class-string)` | Registra Resource. Valida `is_subclass_of HasResource` |
| `registerMany(array<class-string>)` | Bulk |
| `discover(string $namespace, string $path)` | Auto-discover via PSR-4 + Symfony Finder |
| `findByModel(class-string<Model>)` | `?class-string<Resource>` |
| `findBySlug(string)` | `?class-string<Resource>` |
| `has(class-string)` / `clear()` / `all()` | Utilitários |

## Panel

### `Arqel\Core\Panel\Panel` (final)

Builder fluente. Cria via `PanelRegistry::panel($id)`.

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

Cada setter retorna `$this`. Getters tipados existem para todos: `getPath(): string`, `getBrand(): ?string`, etc.

### `Arqel\Core\Panel\PanelRegistry` (final)

Singleton. Métodos: `panel(id): Panel` (create-or-get), `setCurrent(id)`, `getCurrent(): ?Panel`, `all()`, `has(id)`, `clear()`. `setCurrent` em ID desconhecido lança `PanelNotFoundException`.

## Contracts

| Interface | Implementadores | Métodos |
|---|---|---|
| `HasResource` | `Resource` | 7 estáticos: `getModel`, `getSlug`, `getLabel`, `getPluralLabel`, `getNavigationIcon`, `getNavigationGroup`, `getNavigationSort` |
| `HasFields` | `Resource` | `fields(): array` |
| `HasActions` | `Resource` | marker (sem método) |
| `HasPolicies` | (opcional) | `getPolicy(): ?class-string` para override de Policy |

## HTTP

### `Arqel\Core\Http\Controllers\ResourceController` (final)

7 endpoints polimórficos: `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`. Resolve Resource por `{resource}` slug, autoriza via `Gate::denies(viewAny|create|view|update|delete)`, delega ao `InertiaDataBuilder` para serializar payload.

### `Arqel\Core\Http\Middleware\HandleArqelInertiaRequests` (final)

Estende `Inertia\Middleware`. Shared props: `auth.user` (`only(['id','name','email'])`), `auth.can` (via `AbilityRegistry`), `panel`, `tenant`, `flash`, `translations`, `arqel.version`.

## Support

### `Arqel\Core\Support\InertiaDataBuilder` (final)

Assembler dos payloads index/create/edit/show. Métodos: `buildIndexData`, `buildCreateData`, `buildEditData`, `buildShowData`. Detecta `Resource::table()` retornando `Arqel\Table\Table` via duck-typing e roteia para `buildTableIndexData` (delega ao `TableQueryBuilder` via Reflection — sem hard dep).

### `Arqel\Core\Support\FieldSchemaSerializer` (final)

Serializador central de Fields para o payload Inertia (shape canónico em [`06-api-react.md` §4](https://github.com/arqel/arqel/blob/main/PLANNING/06-api-react.md)). Duck-typed contra `Arqel\Fields\Field` — sem hard dep. Filtra fields por `canBeSeenBy(user, record)`.

## Comandos Artisan

| Comando | Função |
|---|---|
| `arqel:install` | Scaffold inicial (config, dirs, providers, layout, AGENTS.md) |
| `arqel:resource {model} {--with-policy}` | Gera Resource a partir de model FQN curto |

## Related

- SKILL: [`packages/core/SKILL.md`](https://github.com/arqel/arqel/blob/main/packages/core/SKILL.md)
- Source: [`packages/core/src/`](https://github.com/arqel/arqel/blob/main/packages/core/src/)
- Próximo: [`arqel/fields`](/reference/php/fields)

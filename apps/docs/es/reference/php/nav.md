# `arqel-dev/nav` — Referencia de API

Namespace `Arqel\Nav\`. NavigationItem + NavigationGroup + Navigation builder + BreadcrumbsBuilder.

## `Arqel\Nav\NavigationItem` (final)

| Método | Tipo | Descripción |
|---|---|---|
| `NavigationItem::make(string $label)` | `self` | Factory |
| `NavigationItem::resource(class-string<Resource>)` | `self` | Extrae label/icon/sort/URL automáticamente |
| `label(string)` | `self` | |
| `icon(string)` | `self` | ID de `lucide-react` |
| `url(string)` | `self` | XOR con `route` |
| `route(string $name, array $params = [])` | `self` | XOR con `url` |
| `openInNewTab(bool=true)` | `self` | |
| `visible(Closure)` | `self` | Predicado `(?Authenticatable) => bool` |
| `badge(int\|string\|Closure)` | `self` | Closures no-int\|string se descartan |
| `badgeColor(string)` | `self` | |
| `sort(int)` | `self` | |
| `isVisibleFor(?Authenticatable)` | `bool` | Oráculo |
| `resolveBadge(): int\|string\|null` | | |
| `toArray()` | `array` | Filtra claves null |

## `Arqel\Nav\NavigationGroup` (final)

Grupo colapsable.

| Método | Descripción |
|---|---|
| `NavigationGroup::make(string $label)` | Factory |
| `label(string)`, `icon(string)`, `sort(int)` | Setters base |
| `collapsible(bool=true)`, `collapsed(bool=true)` | `collapsed()` implica `collapsible()` |
| `items(array<NavigationItem>)` | Rechaza no-NavigationItem |
| `addItem(NavigationItem)` | Acumulación dinámica |
| `toArray()` | Ordena items por sort + filtra por visibilidad |

## `Arqel\Nav\Navigation` (final)

Builder principal.

| Método | Función |
|---|---|
| `item(NavigationItem)` | Añade un item de nivel superior |
| `group(string $label, Closure)` | Crea un grupo y lo puebla vía callback |
| `addGroup(NavigationGroup)` | Añade un grupo construido externamente |
| `divider()` | Inserta un divider |
| `autoRegister(bool=true)` | Opt-out del auto-registro |
| `autoRegisterFromResources(array<class-string>)` | Agrupa Resources por `getNavigationGroup()` (o nivel superior si null), idempotente |
| `build(?Authenticatable)` | `array<{kind: 'item'\|'group'\|'divider', ...}>` ordenado |

## `Arqel\Nav\BreadcrumbsBuilder` (final)

| Método | Descripción |
|---|---|
| `withResources(array<class-string>)` | |
| `buildFor(string $routeName, array $params)` | Resuelve breadcrumbs desde `arqel.{panel}.{resource}.{action}` |

Soporta acciones `index`/`create`/`edit`/`show`. Cuando `params['record']` es un `Model`, interpola `Resource::recordTitle($record)`. Hace fallback a `ucfirst($slug)` cuando el Resource no está registrado.

Constantes: `ACTION_INDEX = 'index'`, `ACTION_CREATE = 'create'`, `ACTION_EDIT = 'edit'`, `ACTION_SHOW = 'show'`.

## Relacionado

- SKILL: [`packages/nav/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/nav/SKILL.md)
- Volver: [`arqel-dev/core`](/es/reference/php/core)

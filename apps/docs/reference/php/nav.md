# `arqel-dev/nav` — API Reference

Namespace `Arqel\Nav\`. NavigationItem + NavigationGroup + Navigation builder + BreadcrumbsBuilder.

## `Arqel\Nav\NavigationItem` (final)

| Method | Type | Description |
|---|---|---|
| `NavigationItem::make(string $label)` | `self` | Factory |
| `NavigationItem::resource(class-string<Resource>)` | `self` | Extracts label/icon/sort/URL automatically |
| `label(string)` | `self` | |
| `icon(string)` | `self` | `lucide-react` ID |
| `url(string)` | `self` | XOR with `route` |
| `route(string $name, array $params = [])` | `self` | XOR with `url` |
| `openInNewTab(bool=true)` | `self` | |
| `visible(Closure)` | `self` | Predicate `(?Authenticatable) => bool` |
| `badge(int\|string\|Closure)` | `self` | Non-int\|string Closures dropped |
| `badgeColor(string)` | `self` | |
| `sort(int)` | `self` | |
| `isVisibleFor(?Authenticatable)` | `bool` | Oracle |
| `resolveBadge(): int\|string\|null` | | |
| `toArray()` | `array` | Filters null keys |

## `Arqel\Nav\NavigationGroup` (final)

Collapsible group.

| Method | Description |
|---|---|
| `NavigationGroup::make(string $label)` | Factory |
| `label(string)`, `icon(string)`, `sort(int)` | Base setters |
| `collapsible(bool=true)`, `collapsed(bool=true)` | `collapsed()` implies `collapsible()` |
| `items(array<NavigationItem>)` | Rejects non-NavigationItem |
| `addItem(NavigationItem)` | Dynamic accumulation |
| `toArray()` | Sorts items by sort + filters by visibility |

## `Arqel\Nav\Navigation` (final)

Main builder.

| Method | Function |
|---|---|
| `item(NavigationItem)` | Adds a top-level item |
| `group(string $label, Closure)` | Creates a group and populates it via callback |
| `addGroup(NavigationGroup)` | Adds a group built externally |
| `divider()` | Inserts a divider |
| `autoRegister(bool=true)` | Opt-out of auto-registration |
| `autoRegisterFromResources(array<class-string>)` | Groups Resources by `getNavigationGroup()` (or top-level if null), idempotent |
| `build(?Authenticatable)` | `array<{kind: 'item'\|'group'\|'divider', ...}>` ordered |

## `Arqel\Nav\BreadcrumbsBuilder` (final)

| Method | Description |
|---|---|
| `withResources(array<class-string>)` | |
| `buildFor(string $routeName, array $params)` | Resolves breadcrumbs from `arqel.{panel}.{resource}.{action}` |

Supports actions `index`/`create`/`edit`/`show`. When `params['record']` is a `Model`, interpolates `Resource::recordTitle($record)`. Falls back to `ucfirst($slug)` when the Resource is not registered.

Constants: `ACTION_INDEX = 'index'`, `ACTION_CREATE = 'create'`, `ACTION_EDIT = 'edit'`, `ACTION_SHOW = 'show'`.

## Related

- SKILL: [`packages/nav/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/nav/SKILL.md)
- Back: [`arqel-dev/core`](/reference/php/core)

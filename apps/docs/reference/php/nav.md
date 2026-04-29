# `arqel/nav` — API Reference

Namespace `Arqel\Nav\`. NavigationItem + NavigationGroup + Navigation builder + BreadcrumbsBuilder.

## `Arqel\Nav\NavigationItem` (final)

| Método | Tipo | Descrição |
|---|---|---|
| `NavigationItem::make(string $label)` | `self` | Factory |
| `NavigationItem::resource(class-string<Resource>)` | `self` | Extrai label/icon/sort/URL automaticamente |
| `label(string)` | `self` | |
| `icon(string)` | `self` | ID `lucide-react` |
| `url(string)` | `self` | XOR com `route` |
| `route(string $name, array $params = [])` | `self` | XOR com `url` |
| `openInNewTab(bool=true)` | `self` | |
| `visible(Closure)` | `self` | Predicate `(?Authenticatable) => bool` |
| `badge(int\|string\|Closure)` | `self` | Closures não-int\|string descartadas |
| `badgeColor(string)` | `self` | |
| `sort(int)` | `self` | |
| `isVisibleFor(?Authenticatable)` | `bool` | Oracle |
| `resolveBadge(): int\|string\|null` | | |
| `toArray()` | `array` | Filtra chaves null |

## `Arqel\Nav\NavigationGroup` (final)

Group colapsável.

| Método | Descrição |
|---|---|
| `NavigationGroup::make(string $label)` | Factory |
| `label(string)`, `icon(string)`, `sort(int)` | Setters base |
| `collapsible(bool=true)`, `collapsed(bool=true)` | `collapsed()` implica `collapsible()` |
| `items(array<NavigationItem>)` | Rejeita não-NavigationItem |
| `addItem(NavigationItem)` | Acumulação dinâmica |
| `toArray()` | Ordena items por sort + filtra por visibility |

## `Arqel\Nav\Navigation` (final)

Builder principal.

| Método | Função |
|---|---|
| `item(NavigationItem)` | Adiciona top-level item |
| `group(string $label, Closure)` | Cria group e popula via callback |
| `addGroup(NavigationGroup)` | Adiciona group construído externamente |
| `divider()` | Insere divisor |
| `autoRegister(bool=true)` | Opt-out de auto-registo |
| `autoRegisterFromResources(array<class-string>)` | Agrupa Resources por `getNavigationGroup()` (ou top-level se null), idempotente |
| `build(?Authenticatable)` | `array<{kind: 'item'\|'group'\|'divider', ...}>` ordenado |

## `Arqel\Nav\BreadcrumbsBuilder` (final)

| Método | Descrição |
|---|---|
| `withResources(array<class-string>)` | |
| `buildFor(string $routeName, array $params)` | Resolve breadcrumbs a partir de `arqel.{panel}.{resource}.{action}` |

Suporta actions `index`/`create`/`edit`/`show`. Quando `params['record']` é `Model`, interpola `Resource::recordTitle($record)`. Fallback para `ucfirst($slug)` quando Resource não está registado.

Constantes: `ACTION_INDEX = 'index'`, `ACTION_CREATE = 'create'`, `ACTION_EDIT = 'edit'`, `ACTION_SHOW = 'show'`.

## Related

- SKILL: [`packages/nav/SKILL.md`](https://github.com/arqel/arqel/blob/main/packages/nav/SKILL.md)
- Volta: [`arqel/core`](/reference/php/core)

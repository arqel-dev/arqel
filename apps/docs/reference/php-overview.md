# API Reference — PHP

A curated per-package reference (PHP 8.3+). Each page documents the public classes, fluent setters, oracles, and HTTP endpoints.

## One-line install

```bash
composer require arqel-dev/framework
php artisan arqel:install
```

`arqel-dev/framework` is the **meta-package** that aggregates every core package via `composer.json` and exposes the root `ArqelServiceProvider` with the `arqel:install` command. Consumer apps don't need to declare individual dependencies.

## Packages

| Package | Contents | Page |
|---|---|---|
| `arqel-dev/framework` | Meta-package + `InstallCommand` + `MakeUserCommand` | [Core →](/reference/php/core) |
| `arqel-dev/core` | Resource, ResourceRegistry, Panel, PanelRegistry, controller, middleware | [Core →](/reference/php/core) |
| `arqel-dev/fields` | Field abstract, FieldFactory, 21 field types, ValidationBridge | [Fields →](/reference/php/fields) |
| `arqel-dev/table` | Table builder, 9 column types, 6 filter types, TableQueryBuilder | [Table →](/reference/php/table) |
| `arqel-dev/form` | Form builder, 7 layout components, FieldRulesExtractor, FormRequestGenerator | [Form →](/reference/php/form) |
| `arqel-dev/actions` | Action abstract, 4 variants, Confirmable + HasForm + HasAuthorization, ActionController | [Actions →](/reference/php/actions) |
| `arqel-dev/auth` | AbilityRegistry, PolicyDiscovery, ArqelGate, EnsureUserCanAccessPanel, helpers | [Auth →](/reference/php/auth) |
| `arqel-dev/nav` | NavigationItem, NavigationGroup, Navigation builder, BreadcrumbsBuilder | [Nav →](/reference/php/nav) |

Total: **8 PHP packages** (1 meta + 7 core).

## General conventions

- Every package declares `declare(strict_types=1)` at the top of every source file
- Classes are `final` by default; abstract ones (`Resource`, `Field`, `Action`, `Column`, `Filter`, `Component`) have a final `__construct` to prevent override
- Setters return `$this` for chaining; getters are typed
- Service Providers are auto-discovered via `composer.json` → `extra.laravel.providers`
- Singletons (`ResourceRegistry`, `PanelRegistry`, `AbilityRegistry`) are bound in `Provider::register`

## Auto-generation (TODO)

This reference is still **manually curated** — the canonical source for each package is the `SKILL.md` file at `packages/{pkg}/`. Auto-generation via [phpDocumentor](https://www.phpdoc.org/) or [Doctum](https://github.com/code-lts/doctum) ships as a follow-up:

```yaml
# .github/workflows/docs-deploy.yml (future)
- name: Generate phpDocumentor
  run: phpdoc -d packages/ -t apps/docs/reference/php/_generated
```

The DOCS-005 criteria ("CI regenerates on every push", "every public class documented via docblock") covered by auto-generation remain pending until that PR.

## Related

- TypeScript: [API Reference TS](/reference/typescript-overview)
- ADRs: [`PLANNING/03-adrs.md`](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md)
- Roadmap: [`PLANNING/07-roadmap-fases.md`](https://github.com/arqel-dev/arqel/blob/main/PLANNING/07-roadmap-fases.md)

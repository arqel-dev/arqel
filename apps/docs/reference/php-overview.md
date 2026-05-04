# API Reference — PHP

Referência curada por pacote (PHP 8.3+). Cada página documenta as classes públicas, setters fluentes, oracles e endpoints HTTP.

## Pacotes

| Pacote | Conteúdo | Página |
|---|---|---|
| `arqel-dev/core` | Resource, ResourceRegistry, Panel, PanelRegistry, controller, middleware | [Core →](/reference/php/core) |
| `arqel-dev/fields` | Field abstract, FieldFactory, 21 field types, ValidationBridge | [Fields →](/reference/php/fields) |
| `arqel-dev/table` | Table builder, 9 column types, 6 filter types, TableQueryBuilder | [Table →](/reference/php/table) |
| `arqel-dev/form` | Form builder, 7 layout components, FieldRulesExtractor, FormRequestGenerator | [Form →](/reference/php/form) |
| `arqel-dev/actions` | Action abstract, 4 variantes, Confirmable + HasForm + HasAuthorization, ActionController | [Actions →](/reference/php/actions) |
| `arqel-dev/auth` | AbilityRegistry, PolicyDiscovery, ArqelGate, EnsureUserCanAccessPanel, helpers | [Auth →](/reference/php/auth) |
| `arqel-dev/nav` | NavigationItem, NavigationGroup, Navigation builder, BreadcrumbsBuilder | [Nav →](/reference/php/nav) |

## Convenções gerais

- Todos os pacotes declaram `declare(strict_types=1)` no topo de cada source file
- Classes são `final` por defeito; abstratas (`Resource`, `Field`, `Action`, `Column`, `Filter`, `Component`) têm `__construct` final para impedir override
- Setters retornam `$this` para encadeamento; getters são tipados
- Service Providers são auto-discovered via `composer.json` → `extra.laravel.providers`
- Singletons (`ResourceRegistry`, `PanelRegistry`, `AbilityRegistry`) são bound em `Provider::register`

## Geração automática (TODO)

Esta referência ainda é **curada manualmente** — fonte canónica para cada pacote são os ficheiros `SKILL.md` em `packages/{pkg}/`. Auto-geração via [phpDocumentor](https://www.phpdoc.org/) ou [Doctum](https://github.com/code-lts/doctum) chega como follow-up:

```yaml
# .github/workflows/docs-deploy.yml (futuro)
- name: Generate phpDocumentor
  run: phpdoc -d packages/ -t apps/docs/reference/php/_generated
```

Os critérios de DOCS-005 ("CI regenera em cada push", "todas as classes públicas documentadas via docblock") cobertos pela auto-geração ficam pendentes até esse PR.

## Related

- TypeScript: [API Reference TS](/reference/typescript-overview)
- ADRs: [`PLANNING/03-adrs.md`](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md)
- Roadmap: [`PLANNING/07-roadmap-fases.md`](https://github.com/arqel-dev/arqel/blob/main/PLANNING/07-roadmap-fases.md)

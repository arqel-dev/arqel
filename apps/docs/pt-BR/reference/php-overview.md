# API Reference — PHP

Referência curada por pacote (PHP 8.3+). Cada página documenta as classes públicas, setters fluentes, oracles e endpoints HTTP.

## Instalação one-line

```bash
composer require arqel-dev/arqel
php artisan arqel:install
```

`arqel-dev/arqel` é o **meta-package** que agrega todos os pacotes core via `composer.json` e expõe o `ArqelServiceProvider` raiz com o comando `arqel:install`. Apps consumidoras não precisam declarar dependências individuais.

## Pacotes

| Pacote | Conteúdo | Página |
|---|---|---|
| `arqel-dev/arqel` | Meta-package + `InstallCommand` + `MakeUserCommand` | [Core →](/pt-BR/reference/php/core) |
| `arqel-dev/core` | Resource, ResourceRegistry, Panel, PanelRegistry, controller, middleware | [Core →](/pt-BR/reference/php/core) |
| `arqel-dev/fields` | Field abstract, FieldFactory, 21 field types, ValidationBridge | [Fields →](/pt-BR/reference/php/fields) |
| `arqel-dev/table` | Table builder, 9 column types, 6 filter types, TableQueryBuilder | [Table →](/pt-BR/reference/php/table) |
| `arqel-dev/form` | Form builder, 7 layout components, FieldRulesExtractor, FormRequestGenerator | [Form →](/pt-BR/reference/php/form) |
| `arqel-dev/actions` | Action abstract, 4 variantes, Confirmable + HasForm + HasAuthorization, ActionController | [Actions →](/pt-BR/reference/php/actions) |
| `arqel-dev/auth` | AbilityRegistry, PolicyDiscovery, ArqelGate, EnsureUserCanAccessPanel, helpers | [Auth →](/pt-BR/reference/php/auth) |
| `arqel-dev/nav` | NavigationItem, NavigationGroup, Navigation builder, BreadcrumbsBuilder | [Nav →](/pt-BR/reference/php/nav) |

Total: **8 pacotes PHP** (1 meta + 7 core).

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

- TypeScript: [API Reference TS](/pt-BR/reference/typescript-overview)
- ADRs: [`PLANNING/03-adrs.md`](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md)
- Roadmap: [`PLANNING/07-roadmap-fases.md`](https://github.com/arqel-dev/arqel/blob/main/PLANNING/07-roadmap-fases.md)

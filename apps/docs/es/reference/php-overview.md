# Referencia de API — PHP

Una referencia curada por paquete (PHP 8.3+). Cada página documenta las clases públicas, setters fluidos, oráculos y endpoints HTTP.

## Instalación en una línea

```bash
composer require arqel-dev/framework
php artisan arqel:install
```

`arqel-dev/framework` es el **meta-paquete** que agrega cada paquete core vía `composer.json` y expone el `ArqelServiceProvider` raíz con el comando `arqel:install`. Las apps consumidoras no necesitan declarar dependencias individuales.

## Paquetes

| Paquete | Contenido | Página |
|---|---|---|
| `arqel-dev/framework` | Meta-paquete + `InstallCommand` + `MakeUserCommand` | [Core →](/es/reference/php/core) |
| `arqel-dev/core` | Resource, ResourceRegistry, Panel, PanelRegistry, controller, middleware | [Core →](/es/reference/php/core) |
| `arqel-dev/fields` | Field abstracto, FieldFactory, 21 tipos de Field, ValidationBridge | [Fields →](/es/reference/php/fields) |
| `arqel-dev/table` | Table builder, 9 tipos de columna, 6 tipos de filtro, TableQueryBuilder | [Table →](/es/reference/php/table) |
| `arqel-dev/form` | Form builder, 7 componentes de layout, FieldRulesExtractor, FormRequestGenerator | [Form →](/es/reference/php/form) |
| `arqel-dev/actions` | Action abstracto, 4 variantes, Confirmable + HasForm + HasAuthorization, ActionController | [Actions →](/es/reference/php/actions) |
| `arqel-dev/auth` | AbilityRegistry, PolicyDiscovery, ArqelGate, EnsureUserCanAccessPanel, helpers | [Auth →](/es/reference/php/auth) |
| `arqel-dev/nav` | NavigationItem, NavigationGroup, Navigation builder, BreadcrumbsBuilder | [Nav →](/es/reference/php/nav) |

Total: **8 paquetes PHP** (1 meta + 7 core).

## Convenciones generales

- Cada paquete declara `declare(strict_types=1)` al inicio de cada archivo de origen
- Las clases son `final` por defecto; las abstractas (`Resource`, `Field`, `Action`, `Column`, `Filter`, `Component`) tienen `__construct` final para evitar override
- Los setters retornan `$this` para encadenamiento; los getters son tipados
- Los Service Providers se autodescubren vía `composer.json` → `extra.laravel.providers`
- Los Singletons (`ResourceRegistry`, `PanelRegistry`, `AbilityRegistry`) se vinculan en `Provider::register`

## Auto-generación (TODO)

Esta referencia todavía se mantiene **curada manualmente** — la fuente canónica para cada paquete es el archivo `SKILL.md` en `packages/{pkg}/`. La auto-generación vía [phpDocumentor](https://www.phpdoc.org/) o [Doctum](https://github.com/code-lts/doctum) llegará como un follow-up:

```yaml
# .github/workflows/docs-deploy.yml (future)
- name: Generate phpDocumentor
  run: phpdoc -d packages/ -t apps/docs/reference/php/_generated
```

Los criterios de DOCS-005 ("CI regenera en cada push", "cada clase pública documentada vía docblock") cubiertos por la auto-generación quedan pendientes hasta ese PR.

## Relacionado

- TypeScript: [Referencia de API TS](/es/reference/typescript-overview)
- ADRs: [`PLANNING/03-adrs.md`](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md)
- Roadmap: [`PLANNING/07-roadmap-fases.md`](https://github.com/arqel-dev/arqel/blob/main/PLANNING/07-roadmap-fases.md)

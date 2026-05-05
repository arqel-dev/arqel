# `arqel-dev/form` — Referencia de API

Namespace `Arqel\Form\`. Builder fluido + 7 componentes de layout + generador de FormRequest.

## `Arqel\Form\Form` (final)

Builder.

| Método | Tipo | Descripción |
|---|---|---|
| `Form::make()` | `self` | Factory |
| `schema(array)` | `self` | Lista heterogénea de Fields + componentes de layout |
| `columns(int)` | `self` | Columnas de la grid raíz (clamp ≥ 1) |
| `model(class-string<Model>)` | `self` | Hint de modelo |
| `inline(bool)` | `self` | Renderiza inline en lugar de modal |
| `disabled(bool)` | `self` | Disable global |
| `getFields(): array<Field>` | flatten recursivo | desciende en componentes de layout |
| `toArray(): array` | Schema serializado | discriminator `kind: 'field'\|'layout'` |

## Componentes de Layout

`Arqel\Form\Layout\Component` (abstract). Estado compartido: `$schema`, `$columnSpan`, `$visibleIf`, `$canSee`. El oráculo `isVisibleFor(?Model)` evalúa `canSee` antes que `visibleIf`.

| Clase | Setters extra |
|---|---|
| `Section` | `heading`, `description`, `icon`, `collapsible`, `collapsed`, `columns`, `compact`, `aside` (`collapsed()` implica `collapsible()`) |
| `Fieldset` | `legend`, `columns` |
| `Grid` | `columns(int\|array<string,int>)`, `gap` |
| `Columns` | atajo semántico para `Grid::columns(2)` |
| `Group` | `orientation('horizontal'\|'vertical')` |
| `Tabs` | `tabs(array<Tab>)`, `defaultTab(id)`, `vertical()`/`horizontal()` |
| `Tab` | `id`, `label`, `icon`, `badge(int\|Closure)` (Closures no-int se descartan) |

## `Arqel\Form\FieldRulesExtractor` (final)

Agrega rules/messages/attributes a través de una lista plana de Fields.

| Método | Retorna |
|---|---|
| `extract(array<Field>)` | `array<name, rules>` |
| `extractMessages(array<Field>)` | `array<{name}.{rule}, message>` |
| `extractAttributes(array<Field>)` | `array<name, attribute>` |

Las entradas que no son Field se descartan en silencio.

## `Arqel\Form\FormRequestGenerator` (final)

Genera `Store{Model}Request`/`Update{Model}Request` desde un class-string de Resource.

```php
$generator->generate(PostResource::class, 'create', 'App\\Http\\Requests'): string  // PHP source
$generator->write(PostResource::class, base_path('app/Http/Requests'), force: false): array<string>
```

Skip silencioso cuando el archivo existe y `force=false`. Las clases generadas delegan `rules()/messages()/attributes()` a `FieldRulesExtractor`, resolviendo el Resource vía el container.

## Relacionado

- SKILL: [`packages/form/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/form/SKILL.md)
- Conceptos: [`/es/guide/tables-forms`](/es/guide/tables-forms)
- Siguiente: [`arqel-dev/actions`](/es/reference/php/actions)

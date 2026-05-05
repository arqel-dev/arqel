# `arqel-dev/form` — API Reference

Namespace `Arqel\Form\`. Builder fluente + 7 layout components + FormRequest generator.

## `Arqel\Form\Form` (final)

Builder.

| Método | Tipo | Descrição |
|---|---|---|
| `Form::make()` | `self` | Factory |
| `schema(array)` | `self` | Lista heterogénea de Fields + Layout components |
| `columns(int)` | `self` | Colunas do grid raiz (clamp ≥ 1) |
| `model(class-string<Model>)` | `self` | Hint de model |
| `inline(bool)` | `self` | Renderiza inline em vez de modal |
| `disabled(bool)` | `self` | Disable global |
| `getFields(): array<Field>` | flatten recursivo | desce em layout components |
| `toArray(): array` | Schema serializado | discriminator `kind: 'field'\|'layout'` |

## Layout Components

`Arqel\Form\Layout\Component` (abstract). Estado partilhado: `$schema`, `$columnSpan`, `$visibleIf`, `$canSee`. Oracle `isVisibleFor(?Model)` avalia `canSee` antes de `visibleIf`.

| Class | Setters extra |
|---|---|
| `Section` | `heading`, `description`, `icon`, `collapsible`, `collapsed`, `columns`, `compact`, `aside` (`collapsed()` implica `collapsible()`) |
| `Fieldset` | `legend`, `columns` |
| `Grid` | `columns(int\|array<string,int>)`, `gap` |
| `Columns` | atalho semântico para `Grid::columns(2)` |
| `Group` | `orientation('horizontal'\|'vertical')` |
| `Tabs` | `tabs(array<Tab>)`, `defaultTab(id)`, `vertical()`/`horizontal()` |
| `Tab` | `id`, `label`, `icon`, `badge(int\|Closure)` (Closures não-int descartadas) |

## `Arqel\Form\FieldRulesExtractor` (final)

Agrega rules/messages/attributes através de uma lista flat de Fields.

| Método | Retorna |
|---|---|
| `extract(array<Field>)` | `array<name, rules>` |
| `extractMessages(array<Field>)` | `array<{name}.{rule}, message>` |
| `extractAttributes(array<Field>)` | `array<name, attribute>` |

Entries não-Field são silenciosamente descartados.

## `Arqel\Form\FormRequestGenerator` (final)

Gera `Store{Model}Request`/`Update{Model}Request` a partir de uma class-string de Resource.

```php
$generator->generate(PostResource::class, 'create', 'App\\Http\\Requests'): string  // PHP source
$generator->write(PostResource::class, base_path('app/Http/Requests'), force: false): array<string>
```

Skip silente quando ficheiro existe e `force=false`. As classes geradas delegam `rules()/messages()/attributes()` ao `FieldRulesExtractor` resolvendo o Resource via container.

## Related

- SKILL: [`packages/form/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/form/SKILL.md)
- Conceitos: [`/pt-BR/guide/tables-forms`](/pt-BR/guide/tables-forms)
- Próximo: [`arqel-dev/actions`](/pt-BR/reference/php/actions)

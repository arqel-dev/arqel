# `arqel-dev/form` — API Reference

Namespace `Arqel\Form\`. Fluent builder + 7 layout components + FormRequest generator.

## `Arqel\Form\Form` (final)

Builder.

| Method | Type | Description |
|---|---|---|
| `Form::make()` | `self` | Factory |
| `schema(array)` | `self` | Heterogeneous list of Fields + Layout components |
| `columns(int)` | `self` | Root grid columns (clamp ≥ 1) |
| `model(class-string<Model>)` | `self` | Model hint |
| `inline(bool)` | `self` | Renders inline instead of modal |
| `disabled(bool)` | `self` | Global disable |
| `getFields(): array<Field>` | recursive flatten | descends into layout components |
| `toArray(): array` | Serialized schema | discriminator `kind: 'field'\|'layout'` |

## Layout Components

`Arqel\Form\Layout\Component` (abstract). Shared state: `$schema`, `$columnSpan`, `$visibleIf`, `$canSee`. Oracle `isVisibleFor(?Model)` evaluates `canSee` before `visibleIf`.

| Class | Extra setters |
|---|---|
| `Section` | `heading`, `description`, `icon`, `collapsible`, `collapsed`, `columns`, `compact`, `aside` (`collapsed()` implies `collapsible()`) |
| `Fieldset` | `legend`, `columns` |
| `Grid` | `columns(int\|array<string,int>)`, `gap` |
| `Columns` | semantic shortcut for `Grid::columns(2)` |
| `Group` | `orientation('horizontal'\|'vertical')` |
| `Tabs` | `tabs(array<Tab>)`, `defaultTab(id)`, `vertical()`/`horizontal()` |
| `Tab` | `id`, `label`, `icon`, `badge(int\|Closure)` (non-int Closures dropped) |

## `Arqel\Form\FieldRulesExtractor` (final)

Aggregates rules/messages/attributes across a flat list of Fields.

| Method | Returns |
|---|---|
| `extract(array<Field>)` | `array<name, rules>` |
| `extractMessages(array<Field>)` | `array<{name}.{rule}, message>` |
| `extractAttributes(array<Field>)` | `array<name, attribute>` |

Non-Field entries are silently dropped.

## `Arqel\Form\FormRequestGenerator` (final)

Generates `Store{Model}Request`/`Update{Model}Request` from a Resource class-string.

```php
$generator->generate(PostResource::class, 'create', 'App\\Http\\Requests'): string  // PHP source
$generator->write(PostResource::class, base_path('app/Http/Requests'), force: false): array<string>
```

Silent skip when the file exists and `force=false`. The generated classes delegate `rules()/messages()/attributes()` to `FieldRulesExtractor`, resolving the Resource via the container.

## Related

- SKILL: [`packages/form/SKILL.md`](https://github.com/arqel-dev/arqel/blob/main/packages/form/SKILL.md)
- Concepts: [`/guide/tables-forms`](/guide/tables-forms)
- Next: [`arqel-dev/actions`](/reference/php/actions)

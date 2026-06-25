# arqel-dev/fields-advanced

Advanced field types for Arqel — RichText (Tiptap), Markdown, Code, Repeater, Builder, KeyValue, Tags, Wizard.

## Status

Shipped. All eight advanced field types are implemented: `RichTextField`, `MarkdownField`, `CodeField`, `RepeaterField`, `BuilderField`, `KeyValueField`, `TagsField`, `WizardField`. See [`SKILL.md`](./SKILL.md) for the full contract surface (133 Pest tests).

## Install

In a Laravel app already running `arqel-dev/core` and `arqel-dev/fields`:

```bash
composer require arqel-dev/fields-advanced
```

The service provider is auto-discovered.

## Tests

```bash
composer test
```

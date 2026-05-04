# arqel-dev/fields-advanced

Advanced field types for Arqel — RichText (Tiptap), Markdown, Code, Repeater, Builder, KeyValue, Tags, Wizard.

## Status

Phase 2 scaffold (FIELDS-ADV-001). The first concrete type, `RichTextField`, ships with FIELDS-ADV-002. Remaining types land in FIELDS-ADV-003+. See [`SKILL.md`](./SKILL.md) for the full contract surface and roadmap.

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

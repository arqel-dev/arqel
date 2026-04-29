# arqel/widgets

Dashboard widgets for Arqel — Stat/Chart/Table/Custom widgets with deferred loading and polling.

## Status

Phase 2 scaffold (WIDGETS-001). Concrete widget types (StatWidget, ChartWidget, TableWidget, CustomWidget) and the dashboard controllers land in WIDGETS-002..006. See [`SKILL.md`](./SKILL.md).

## Install

In a Laravel app already running `arqel/core`:

```bash
composer require arqel/widgets
```

The service provider is auto-discovered.

## Tests

```bash
composer test
```

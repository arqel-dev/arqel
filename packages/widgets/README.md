# arqel-dev/widgets

Dashboard widgets for Arqel — Stat/Chart/Table/Custom widgets with deferred loading and polling.

## Status

Shipped (WIDGETS-001..009). The four concrete widget types (`StatWidget`, `ChartWidget`, `TableWidget`, `CustomWidget`) and the dashboard/widget-data controllers (`DashboardController`, `WidgetDataController`) are implemented. See [`SKILL.md`](./SKILL.md).

## Install

In a Laravel app already running `arqel-dev/core`:

```bash
composer require arqel-dev/widgets
```

The service provider is auto-discovered.

## Tests

```bash
composer test
```

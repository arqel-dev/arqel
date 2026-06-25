# arqel-dev/actions

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.3-777bb4.svg)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/laravel-%5E12.0%20%7C%20%5E13.0-ff2d20.svg)](https://laravel.com)
[![Status](https://img.shields.io/badge/status-alpha-yellow.svg)](#)

Pacote de **Actions** para o ecossistema [Arqel](https://arqel.dev) — row, bulk, toolbar e header actions com confirmação, formulário inline e authorization.

## Status

**Alpha** — a classe base `Action`, os tipos concretos em `src/Types/` (`RowAction`, `BulkAction`, `ToolbarAction`, `HeaderAction`), os concerns e o `ActionController` entregues.

## Convenções

- `declare(strict_types=1)` em todos os ficheiros PHP
- Classes `final` por default; `Action` é `abstract`
- Tipos concretos em `src/Types/`, traits em `src/Concerns/`

## Links

- [Documentação](https://arqel.dev/docs/actions) — em construção
- [PLANNING](../../PLANNING/08-fase-1-mvp.md) — tickets `ACTIONS-*`

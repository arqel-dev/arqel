# arqel-dev/table

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.3-777bb4.svg)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/laravel-%5E12.0%20%7C%20%5E13.0-ff2d20.svg)](https://laravel.com)
[![Status](https://img.shields.io/badge/status-pre--alpha-orange.svg)](#)

Pacote de **Tables** para o ecossistema [Arqel](https://arqel.dev) — sort/filter/search/pagination declarativos contra queries Eloquent.

## Status

🚧 **Pre-alpha** — esqueleto criado em `TABLE-001`. As classes `Table`, `Column`, `Filter`, `TableQueryBuilder`, `TablePaginator` chegam em `TABLE-002+`.

## Convenções

- `declare(strict_types=1)` em todos os ficheiros PHP
- Classes `final` por default; abstractas só onde a extensão é design intent
- Cada tipo de Column vive em `src/Columns/`; Filter em `src/Filters/`
- Concerns reutilizáveis em `src/Concerns/`

## Links

- [Documentação](https://arqel.dev/docs/table) — em construção
- [Source](./src/)
- [Testes](./tests/)
- [PLANNING](../../PLANNING/08-fase-1-mvp.md) — tickets `TABLE-*`

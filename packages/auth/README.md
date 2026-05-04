# arqel-dev/auth

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.3-777bb4.svg)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/laravel-%5E12.0%20%7C%20%5E13.0-ff2d20.svg)](https://laravel.com)
[![Status](https://img.shields.io/badge/status-pre--alpha-orange.svg)](#)

Pacote de **Auth** para o ecossistema [Arqel](https://arqel.dev) — wraps Laravel Policies + Gate com conveniences para Resources e panels.

## Status

🚧 **Pre-alpha** — `AbilityRegistry`, `PolicyDiscovery`, `ArqelGate` entregues em `AUTH-001..003`. `<CanAccess>` middleware helpers chegam em `AUTH-004`.

## Convenções

- `declare(strict_types=1)` em todos os ficheiros PHP
- Classes `final` por default
- User escreve as Policies; Arqel apenas verifica existência e auto-registra

## Links

- [Documentação](https://arqel.dev/docs/auth) — em construção
- [PLANNING](../../PLANNING/08-fase-1-mvp.md) — tickets `AUTH-*`

# arqel/fields

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.3-777bb4.svg)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/laravel-%5E12.0%20%7C%20%5E13.0-ff2d20.svg)](https://laravel.com)
[![Status](https://img.shields.io/badge/status-pre--alpha-orange.svg)](#)

Pacote de **Fields** declarativos para o ecossistema [Arqel](https://arqel.dev) — admin panels para Laravel forjados em PHP, renderizados em React via Inertia.

## Visão

`arqel/fields` define a abstracção `Field` que descreve um único campo de formulário/coluna de tabela: tipo, label, validação Laravel, schema Zod inferido para o cliente, autorização per-field, visibilidade contextual, dependências reactivas, e tudo o que precisa para tornar a definição PHP renderizável em React sem duplicar lógica.

## Status

🚧 **Pre-alpha** — esqueleto criado em `FIELDS-001`. As classes `Field`, `FieldFactory` e os tipos concretos (`TextField`, `SelectField`, `BelongsToField`, etc.) chegam em `FIELDS-002+`.

## Convenções

- `declare(strict_types=1)` em todos os ficheiros PHP
- Classes `final` por default; abstractas só onde a extensão é design intent
- Cada tipo de Field é um ficheiro em `src/Types/`
- Concerns (`HasValidation`, `HasVisibility`, `HasDependencies`, `HasAuthorization`) ficam em `src/Concerns/`

Ver [SKILL.md](./SKILL.md) para o contexto completo orientado a agentes de IA.

## Links

- [Documentação](https://arqel.dev/docs/fields) — em construção
- [Source](./src/)
- [Testes](./tests/)
- [PLANNING](../../PLANNING/08-fase-1-mvp.md) — tickets `FIELDS-*`

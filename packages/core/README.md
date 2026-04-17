# arqel/core

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.3-8892BF.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/laravel-%5E12%20%7C%7C%20%5E13-FF2D20.svg)](https://laravel.com)
[![Status](https://img.shields.io/badge/status-early%20development-orange.svg)]()

> Core contracts, service provider e primitivas para o framework **Arqel** — admin panels para Laravel declarados em PHP e renderizados em React.

## Posição no ecossistema

Este pacote é a **fundação** de todos os pacotes `arqel/*`. É onde vivem:

- O `ArqelServiceProvider` que faz auto-discovery na app Laravel consumidora
- Contracts (`HasResource`, `HasFields`, `HasActions`, `HasPolicies`, `Renderable`)
- Classe abstracta base `Resource` e `ResourceRegistry`
- Sistema de `Panel` e `PanelRegistry`
- Middleware Inertia (`HandleArqelInertia`)
- Comandos Artisan: `arqel:install`, `arqel:resource`, `arqel:field`, `arqel:action`
- Facade `Arqel`
- Suporte para serialização de schemas de Fields para Inertia props

Os pacotes específicos (`arqel/fields`, `arqel/table`, `arqel/form`, ...) dependem de `arqel/core` e estendem os contracts/classes base daqui.

## Instalação

```bash
composer require arqel/core
```

> Normalmente instala-se via o meta-pacote `arqel/arqel`, que puxa `arqel/core` e companheiros obrigatórios.

## Convenções

- **Namespace:** `Arqel\Core\`
- **`declare(strict_types=1)`** em todos os ficheiros PHP
- **Classes `final` por default** — só sem `final` quando extensibilidade é design intent documentado
- **ADR-018:** auto-discovery via `extra.laravel.providers`

## Links

- Source: [`packages/core/`](.)
- Skill para AI agents: [`SKILL.md`](SKILL.md)
- Docs (public): [arqel.dev/docs](https://arqel.dev/docs) (em breve)
- Contracts e APIs detalhadas: [`PLANNING/05-api-php.md`](../../PLANNING/05-api-php.md)
- Planning tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §3

## Licença

MIT — ver [`LICENSE`](../../LICENSE) na raiz do monorepo.

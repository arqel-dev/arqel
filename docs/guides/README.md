# Arqel guides — Phase 2

> Skeleton de documentação dos seis principais blocos de funcionalidades entregues na Phase 2 do Arqel. Conteúdo extraído dos `SKILL.md` canónicos de cada pacote.
>
> Este diretório é **markdown puro**, pensado para ingestão futura por uma instância Astro Starlight (`docs.arqel.dev`). Não há build step neste estágio.

## Guias disponíveis

| Guia | Descrição |
|---|---|
| [tenancy.md](./tenancy.md) | Multi-tenancy single-DB (scoped) e multi-DB via adapters `stancl`/`spatie`. |
| [dashboards.md](./dashboards.md) | Sistema de widgets (Stat/Chart/Table/Custom) + composição de Dashboards e filtros. |
| [advanced-fields.md](./advanced-fields.md) | Field types ricos: RichText, Markdown, Code, Repeater, Builder, KeyValue, Tags, Wizard. |
| [mcp.md](./mcp.md) | Servidor MCP (Model Context Protocol) sobre Resources/Actions Arqel + setup Claude Desktop. |
| [tables-v2.md](./tables-v2.md) | Inline editing, query builder visual, column visibility, grouping, reorder, mobile, pagination types. |
| [command-palette.md](./command-palette.md) | Cmd+K palette com providers de navegação, tema e custom commands. |

## Sobre este skeleton

A spec completa de DOCS-V2-001 prevê **30+ páginas** profundas (uma por field type, uma por resolver, etc.). Este commit entrega apenas os **6 overview guides** — um por área. Os deep-dives ficam para v0.9/v1.0.

Cada guide segue um esqueleto consistente:

- **Purpose** — o problema que o pacote resolve
- **Quick start** — snippet mínimo copy-paste
- **Key concepts / Sections** — APIs principais
- **Examples** — 2–4 blocos PHP
- **FAQ / Anti-patterns** — gotchas + decisões

## Fonte canónica

Quando este markdown e o `SKILL.md` de um pacote divergirem, **o `SKILL.md` vence** — atualizar este guide para refletir.

## Related

- [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §10 — DOCS-V2-001
- [`PLANNING/00-index.md`](../../PLANNING/00-index.md) — convenções e estrutura SKILL.md

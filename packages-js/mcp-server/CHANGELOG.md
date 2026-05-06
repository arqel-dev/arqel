# Changelog — @arqel-dev/mcp-server

Todas as alterações notáveis a este pacote são documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/),
e este projeto adere ao [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [0.9.0] - 2026-05-06

Primeira release pública do servidor MCP oficial do Arqel.

### Added

- **MCP-001** — Bootstrap do pacote (`@arqel-dev/mcp-server`) com binário stdio `arqel-mcp`, baseado em `@modelcontextprotocol/sdk`.
- **MCP-002** — Loader de docs/ADRs/API reference embebidos no tarball (cópia idempotente de `apps/docs/` + `PLANNING/03-adrs.md`, `05-api-php.md`, `06-api-react.md` no `prebuild`).
- **MCP-003** — Tool `search_docs(query, limit?)` com indexação BM25 sobre o corpus de documentação.
- **MCP-004** — Tool `get_adr(id)` com lookup canónico de `ADR-001` … `ADR-018`.
- **MCP-005** — Tool `get_api_reference(symbol, language?)` com ranqueamento sobre os ficheiros de API PHP + TypeScript.
- **MCP-006** — Tools `list_resources(projectPath?)` e `describe_resource(class, projectPath?)` com bridge `php artisan arqel:introspect --json` e resolução do projeto Laravel via argumento → `ARQEL_PROJECT_PATH` → walk-up de `cwd`.
- **MCP-007** — Tools `generate_resource(model, fields[], …)` e `generate_field(name, type, options?)` usando o stub canónico `packages/core/stubs/resource.stub`.
- **MCP-008** — Página de documentação no site oficial em três locales (EN/PT-BR/ES), smoke test stdio (`pnpm smoke`) e este CHANGELOG.

### Notes

- Total de 7 tools registadas (`search_docs`, `get_adr`, `get_api_reference`, `list_resources`, `describe_resource`, `generate_resource`, `generate_field`).
- O tarball inclui `dist/`, `docs/`, `planning/{03-adrs,05-api-php,06-api-react}.md`, `stubs/resource.stub`, `README.md`, `SKILL.md`.

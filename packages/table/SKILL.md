# SKILL.md — arqel/table

> Contexto canónico para AI agents (Claude Code, Cursor via MCP, etc.) a trabalhar no pacote `arqel/table`. Estrutura conforme `PLANNING/04-repo-structure.md` §11.

## Purpose

`arqel/table` constrói tabelas declarativas para Resources Arqel: sorting, filtering, search, pagination, bulk actions. Recebe colunas declaradas em PHP, aplica-as a uma query Eloquent, e devolve o resultado serializado para o lado React.

## Status (TABLE-001)

Apenas o esqueleto:

- `composer.json` com dep em `arqel/core: @dev`
- `TableServiceProvider` registado via auto-discovery
- PSR-4 `Arqel\Table\` → `src/`
- Pest + Orchestra Testbench

Ainda **NÃO existem**:

- `Arqel\Table\Table` builder (TABLE-002)
- `Arqel\Table\Column` abstract + concrete columns (TABLE-003)
- `Arqel\Table\Filters\*` (TABLE-004)
- `Arqel\Table\TableQueryBuilder` (TABLE-005)
- Integração com `ResourceController` (TABLE-006, depende de CORE-006)
- Row actions (TABLE-007), bulk actions (TABLE-008)

## Conventions

- `declare(strict_types=1)` obrigatório
- Coordenação com `arqel/fields` para columns que reusem renderers
- **Sem dependência inversa para `arqel/core`**: core não depende de table

## Anti-patterns

- ❌ Aplicar lógica de query no Field — eager loading vive em `EagerLoadingResolver`/`indexQuery`, não no Column
- ❌ Side-effects em colunas (logging, eventos) — colunas são definição declarativa
- ❌ Stringly-typed columns — sempre via factory ou subclasse

## Related

- Source: [`packages/table/src/`](./src/)
- Testes: [`packages/table/tests/`](./tests/)
- Tickets: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §TABLE-001..013
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3

# SKILL.md — @arqel-dev/mcp-server

## Purpose

Servidor MCP (Model Context Protocol) oficial do Arqel. Expõe tools que permitem a assistentes de IA (Claude Code, Cursor, Copilot CLI, Gemini CLI) consultar a documentação do framework, fazer introspection de projetos Laravel que usam Arqel, e gerar scaffolding consistente com as convenções do projeto.

Distribuído como pacote npm com binário `arqel-mcp`, executado via stdio. Não há servidor HTTP nesta iniciativa.

## Key Contracts

- **Transport:** stdio único (sem HTTP no MVP).
- **SDK:** `@modelcontextprotocol/sdk` (oficial).
- **Bridge PHP:** as tools de introspection invocam `php artisan arqel:introspect --json` no projeto-alvo via subprocess. O servidor MCP nunca lê estado interno do Laravel diretamente.
- **Bundle de docs:** o tarball npm inclui cópia de `PLANNING/03-adrs.md`, `05-api-php.md`, `06-api-react.md` e da pasta `apps/docs/` para que tools de docs funcionem fora do monorepo.

## Conventions

- Cada tool vive em `src/tools/<nome>.ts` e exporta `{ definition, handler }`.
- Tools são registadas em bloco no `createServer` para ser fácil ver o catálogo completo.
- Inputs validados com Zod (a adicionar nos tickets MCP-003+).
- Erros retornam estruturados com `code` (e.g., `PROJECT_NOT_FOUND`, `ADR_NOT_FOUND`).

## Examples

```bash
# Build local
pnpm --filter @arqel-dev/mcp-server build

# Registar em Claude Code (após publicação npm)
claude mcp add arqel npx -- -y @arqel-dev/mcp-server
```

## Scaffolding source of truth

As tools `generate_resource` e `generate_field` (MCP-007) emitem **código PHP** via templating. A fonte canónica das convenções é o gerador PHP:

- `packages/core/src/Generators/ResourceGenerator.php` — HEREDOC para o ficheiro Resource + métodos auxiliares para Policy / FormRequest / teste Pest.
- `packages/core/stubs/resource.stub` — stub usado pelo `MakeResourceCommand` quando não há fields.
- `packages/fields/src/Types/*.php` — lista canónica de tipos de Field (`TextField`, `EmailField`, etc.).

Implementação JS:

- `packages-js/mcp-server/stubs/resource.stub` — versão estendida do stub upstream com um token `{{fields}}` adicional. Esta divergência existe porque o stub PHP não suporta interpolação de campos (o `ResourceGenerator` em vez disso usa HEREDOC). Manter a mesma estrutura — só a área dentro de `fields(): array` difere.
- `packages-js/mcp-server/src/scaffolding/field-types.ts` — espelha `packages/fields/src/Types/`. Adicionar uma row sempre que um Field type novo entrar.
- O prebuild (`scripts/copy-resources.mjs`) copia o stub upstream para `stubs/resource.upstream.stub` para deteção de drift — se o ficheiro mudar em git, é sinal para reavaliar o template JS.

Snapshots Vitest em `tests/scaffolding/__snapshots__/` capturam o output PHP exato — qualquer diff precisa de revisão manual.

## Anti-patterns

- Não reimplementar lógica de Resource/Panel registry em TS — sempre passar pelo Artisan command.
- Não escrever no disco do projeto-alvo a partir das tools de scaffolding — retornar código e deixar o assistente decidir.
- Não adicionar transporte HTTP a este pacote; se vier, será pacote separado.

## Related

- `PLANNING/13-pos-mvp-mcp-server.md` — plano da iniciativa (8 tickets MCP-001..MCP-008).
- `packages/core/src/Console/IntrospectCommand.php` — bridge PHP (criada em MCP-002).

# SKILL.md — arqel/mcp

> Contexto canônico para AI agents.

## Purpose

`arqel/mcp` expõe um servidor [Model Context Protocol](https://modelcontextprotocol.io) sobre os panels Arqel: tools (executar Actions, mutar Resources), resources (ler dados de Resource/Table/Widget), prompts (templates pré-construídos para fluxos comuns de admin). Permite que clientes MCP — Claude Desktop, Cursor, Zed, agents customizados — operem o painel via JSON-RPC com a mesma autorização e validação que os usuários humanos vêem na UI Inertia.

A escolha é **aderir ao spec do protocol**: nenhum desvio de `modelcontextprotocol.io`; o pacote é só a tradução PHP/Laravel das primitivas (Tool, Resource, Prompt) e o glue que descobre Resources/Actions já registrados em `arqel/core`.

## Status

**MCP-001 — Pacote scaffold:**

- PSR-4 `Arqel\Mcp\` → `src/`, dep em `arqel/core` via path repo
- `McpServiceProvider` com auto-discovery (`extra.laravel.providers`)
- Pest 3 + Orchestra Testbench, SQLite in-memory

**MCP-002 — `McpServer` (final):**

- JSON-RPC 2.0 handler aderente ao spec MCP `2024-11-05`
- API: `registerTool/Resource/Prompt`, `getTools/Resources/Prompts` (sem callables), `handleRequest` (público para testes), `serve()` (stdio loop newline-delimited)
- Códigos de erro: `-32600` (envelope inválido), `-32601` (method not found), `-32602` (params/lookup inválido), `-32603` (handler throw); notifications (sem `id`) → `[]`
- Wrapping: tool result → `{content: [{type:'text', text}]}`; resource → `{contents: [{uri, text, mimeType?}]}`; prompt → `{description, messages}`

**MCP-003 — `Tools\ListResourcesTool` (final):**

- `name=list_resources`; lista `Arqel\Core\Resources\ResourceRegistry` mapeado para `{class, model, slug, label, pluralLabel, navigationGroup}`
- Resolver Closure injetável no construtor (testes contornam `final ResourceRegistry`)
- Resource quebrada em metadata é silenciosamente ignorada (try/catch por entry)

**MCP-004 — `Tools\DescribeResourceTool` (final):**

- `name=describe_resource`, input `{slug: string (required)}`; payload com 8 chaves estáticas
- `class/slug/model` propagam exceção; `label/pluralLabel` degradam para `getMessage()`; `navigationIcon/Group/Sort` degradam para `null`
- Slug desconhecido → `RuntimeException`

**MCP-005 — `Tools\GenerateResourceTool` (final):**

- `name=generate_resource`, wrapper de `arqel:resource` Artisan; input `{model: string, fromModel: bool, withPolicy: bool}`
- Closure runner injetável; default delega para `Kernel::call`
- Default runner remove `--from-model` antes de chamar Artisan (forward-compat com o flag no schema)

**MCP-006 — `Tools\RunTestTool` (final):**

- `name=run_test`, wrapper Pest/PHPUnit para TDD-loop; input `{filter, path, coverage, timeout}` — todos opcionais
- Path traversal guard: rejeita `..` e paths absolutos com `InvalidArgumentException`
- Timeout clampado em `[1, 600]` segundos (default 300); runner Symfony Process via Closure injetável

**MCP-007 — `Resources\SkillResource` (final):**

- URI scheme `arqel-skill://<package>` (regex `[a-z0-9-]+`); descobre `packages/*/SKILL.md` no monorepo
- `list()` + `read(uri)`; uri inválida → `RuntimeException`; reader Closure injetável
- Auto-registro pre-flattened: `packageBooted()` chama `list()` uma vez e registra cada entry no `McpServer`

**MCP-008 — `Prompts\MigrateFilamentResourcePrompt` + `Prompts\ReviewResourcePrompt` (final):**

- Templates que inlinam o conteúdo de um PHP source dentro de fenced code block + diretrizes
- `schema()` declara argumento required (`filament_file` ou `resource_file`); `generate()` devolve `{description, messages}`
- Path traversal guard (`..` → `InvalidArgumentException`) ANTES do reader; reader Closure injetável

### Setup

Configuração para Claude Desktop (`~/Library/Application Support/Claude/claude_desktop_config.json` em macOS, `%APPDATA%\Claude\claude_desktop_config.json` em Windows):

```json
{
  "mcpServers": {
    "arqel": {
      "command": "php",
      "args": ["artisan", "arqel:mcp:serve"],
      "cwd": "/path/to/your/laravel/app"
    }
  }
}
```

> **Nota:** o comando Artisan `arqel:mcp:serve` ainda não existe — é entregue em ticket de follow-up (envolve `McpServer::serve()`). Por enquanto, integradores podem instanciar `McpServer` em um script PHP custom e chamar `serve()` direto.

Setup guides para Cursor e Windsurf (e exemplos por tool) ficam em DOCS-V2-001 (cross-package).

### Coverage

- **Total: 87 testes Pest, 267 asserções**
- `tests/Unit/McpServerTest.php` — 22 (envelope JSON-RPC, dispatch, wrapping, erros, notifications, coerção de params/arguments)
- `tests/Unit/Tools/` — 26 (ListResources 5 + DescribeResource 6 + GenerateResource 5 + RunTest 10)
- `tests/Unit/Resources/SkillResourceTest.php` — 9
- `tests/Unit/Prompts/` — 13 (Migrate 7 + Review 6)
- `tests/Feature/` — 17 (auto-registro de cada tool/resource/prompt + dispatch via `handleRequest`)

## Conventions

- `declare(strict_types=1)` obrigatório
- Classes `final` por padrão; quando MCP-009 trouxer drivers de transport, abstract bases ficam não-final em `src/Transport/`
- **Spec-first**: nenhum método/payload diverge do JSON Schema oficial em `modelcontextprotocol.io`. Quando o spec evolui, este pacote acompanha — não estende
- **Sem hard dep** em SDKs MCP de terceiros: o JSON-RPC handler é implementação própria minimal
- **Closure injection para testabilidade**: tools/resources/prompts aceitam `?Closure` no construtor para swap de behavior (filesystem reader, ResourceRegistry resolver, Process runner) sem subclassar a `final` class

## Anti-patterns

- ❌ **Inventar tools/resources fora do spec MCP** — se o cliente Claude Desktop não consegue parsear, é bug nosso. Toda extensão precisa virar pull request upstream em `modelcontextprotocol.io` antes
- ❌ **Reusar HTTP middleware do Laravel para auth de MCP sessions** — sessions MCP são JSON-RPC de longa duração, não requests Inertia; auth precisa de pipeline próprio (token-bound, sem CSRF)
- ❌ **Expor todos os Resources do panel automaticamente** — opt-in explícito via `Resource::exposeToMcp()` ou flag em config. Modelos sensíveis (User, Tenant billing) nunca devem ser readable por default
- ❌ **Bypass do path traversal guard** em tools que tocam filesystem (`RunTestTool`, prompts) — `..` e paths absolutos sempre rejeitados antes do reader/runner

## Related

- Tickets: [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §MCP-001..010
- Source: [`packages/mcp/src/`](./src/)
- Tests: [`packages/mcp/tests/`](./tests/)
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only (MCP é canal paralelo, não substitui)
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3
- Externos: [Model Context Protocol spec](https://modelcontextprotocol.io)

# SKILL.md — arqel/mcp

> Contexto canônico para AI agents.

## Purpose

`arqel/mcp` expõe um servidor [Model Context Protocol](https://modelcontextprotocol.io) sobre os panels Arqel: tools (executar Actions, mutar Resources), resources (ler dados de Resource/Table/Widget), prompts (templates pré-construídos para fluxos comuns de admin). Permite que clientes MCP — Claude Desktop, Cursor, Zed, agents customizados — operem o painel via JSON-RPC com a mesma authorização e validação que os usuários humanos vêem na UI Inertia.

A escolha é **aderir ao spec do protocol**: nenhum desvio de `modelcontextprotocol.io`; o pacote é só a tradução PHP/Laravel das primitivas (Tool, Resource, Prompt) e o glue que descobre Resources/Actions já registrados em `arqel/core`.

## Status

**Entregue (MCP-001):**

- Esqueleto do pacote (`composer.json`, PSR-4 `Arqel\Mcp\` → `src/`, dep em `arqel/core` via path repo)
- `McpServiceProvider` registado via auto-discovery (`extra.laravel.providers`)
- **`McpServer` (final, stub)** — assinaturas reais que MCP-002+ vai preencher: `registerTool(name, description, inputSchema, handler)`, `registerResource(uri, name, description, fetcher)`, `registerPrompt(name, description, arguments, generator)`, `getTools()`/`getResources()`/`getPrompts()`. Métodos de registro são no-op; getters retornam `[]`. Padrão TENANT-001: downstream type-hints já estáveis hoje, sem nullability shims quando a implementação real chegar
- Pest 3 + Orchestra Testbench setup com `defineEnvironment` SQLite in-memory
- **4 testes Pest passando**: boot do provider, autoload do namespace, `McpServer` bind como singleton (mesma instância em duas resoluções), métodos stub não lançam e getters retornam arrays vazios

**Por chegar (MCP-002..010):**

- JSON-RPC 2.0 transport (stdio + HTTP) + Artisan `arqel:mcp:serve` — MCP-002
- Tool registry funcional (`registerTool` persiste + expõe via `tools/list`/`tools/call`) — MCP-003
- Resource registry (`resources/list`, `resources/read`) lendo Eloquent + `arqel/core` Resources — MCP-004
- Prompt registry (`prompts/list`, `prompts/get`) — MCP-005
- Auto-descoberta: cada Resource Arqel vira tool CRUD + resource read; cada Action vira tool — MCP-006
- Auth (token bearer + tenant scoping via `arqel/tenant`) — MCP-007
- Streaming responses para tools de longa duração — MCP-008
- Manifest publishing (`mcp.json` para Claude Desktop autoinstall) — MCP-009
- Suite completa de testes + SKILL.md final — MCP-010

## Conventions

- `declare(strict_types=1)` obrigatório
- Classes `final` por padrão; quando MCP-002 trouxer drivers de transport, abstract bases ficam não-final em `src/Transport/`
- **Spec-first**: nenhum método/payload diverge do JSON Schema oficial em `modelcontextprotocol.io`. Quando o spec evolui, este pacote acompanha — não estende
- **Sem hard dep** em SDKs MCP de terceiros: o JSON-RPC handler em MCP-002 será implementação própria minimal (PSR-7 like) para não acoplar a uma lib que ainda está em flux

## Anti-patterns

- ❌ **Inventar tools/resources fora do spec MCP** — se o cliente Claude Desktop não consegue parsear, é bug nosso. Toda extensão precisa virar pull request upstream em `modelcontextprotocol.io` antes
- ❌ **Reusar HTTP middleware do Laravel para auth de MCP sessions** — sessions MCP são JSON-RPC de longa duração, não requests Inertia; auth precisa de pipeline próprio (token-bound, sem CSRF)
- ❌ **Expor todos os Resources do panel automaticamente** — opt-in explícito via `Resource::exposeToMcp()` ou flag em config. Modelos sensíveis (User, Tenant billing) nunca devem ser readable por default

## Related

- Tickets: [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §MCP-001..010
- Source: [`packages/mcp/src/`](./src/)
- Tests: [`packages/mcp/tests/`](./tests/)
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only (MCP é canal paralelo, não substitui)
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3
- Externos: [Model Context Protocol spec](https://modelcontextprotocol.io)

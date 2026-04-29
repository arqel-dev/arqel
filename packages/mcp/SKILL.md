# SKILL.md — arqel/mcp

> Contexto canônico para AI agents.

## Purpose

`arqel/mcp` expõe um servidor [Model Context Protocol](https://modelcontextprotocol.io) sobre os panels Arqel: tools (executar Actions, mutar Resources), resources (ler dados de Resource/Table/Widget), prompts (templates pré-construídos para fluxos comuns de admin). Permite que clientes MCP — Claude Desktop, Cursor, Zed, agents customizados — operem o painel via JSON-RPC com a mesma authorização e validação que os usuários humanos vêem na UI Inertia.

A escolha é **aderir ao spec do protocol**: nenhum desvio de `modelcontextprotocol.io`; o pacote é só a tradução PHP/Laravel das primitivas (Tool, Resource, Prompt) e o glue que descobre Resources/Actions já registrados em `arqel/core`.

## Status

**Entregue (MCP-001):**

- Esqueleto do pacote (`composer.json`, PSR-4 `Arqel\Mcp\` → `src/`, dep em `arqel/core` via path repo)
- `McpServiceProvider` registado via auto-discovery (`extra.laravel.providers`)
- Pest 3 + Orchestra Testbench setup com `defineEnvironment` SQLite in-memory

**Entregue (MCP-002):**

- **`McpServer` (final)** — handler JSON-RPC 2.0 completo aderente ao spec MCP `2024-11-05`. API pública:
  - **Registro**: `registerTool($name, $description, $inputSchema, $handler)`, `registerResource($uri, $name, $description, $fetcher)`, `registerPrompt($name, $description, $arguments, $generator)` persistem metadata + callable em mapas privados (chave: `name` para tools/prompts, `uri` para resources)
  - **Introspecção**: `getTools()`/`getResources()`/`getPrompts()` devolvem o mapa de metadata SEM o callable (handlers são runtime-only)
  - **Dispatch**: `handleRequest(array $request): array` — público para permitir testes unitários sem stdio. Implementa: `initialize`, `tools/list`, `tools/call`, `resources/list`, `resources/read`, `prompts/list`, `prompts/get`
  - **Loop**: `serve()` — wrapper fino do loop stdio newline-delimited (lê `fgets(STDIN)` → `handleRequest` → escreve em `STDOUT`). Não testado diretamente (bound a stdio); cobertura vem via `handleRequest`
- **Envelope JSON-RPC**:
  - Resposta sempre carrega `{jsonrpc: '2.0', id: <request.id>}` + `result` OU `error: {code, message, data?}`
  - Notifications (request sem `id`) NÃO recebem resposta — `handleRequest` retorna `[]`
  - Códigos de erro: `-32600` Invalid Request (envelope malformado, faltando `method` ou `jsonrpc !== '2.0'`), `-32601` Method not found (método desconhecido), `-32602` Invalid params (tool/resource/prompt name/uri desconhecido), `-32603` Internal error (handler lança Throwable; `data.exception` carrega a mensagem original)
  - Resultado de tool é wrapped em `{content: [{type: 'text', text: <stringified>}]}`; strings passam direto, arrays/objetos são `json_encode`-ados
  - Resultado de resource é wrapped em `{contents: [{uri, text, mimeType?}]}`
  - Resultado de prompt é wrapped em `{description, messages: <generator output>}` — generator é responsável por devolver o array de messages no formato MCP
- **24 testes Pest passando** (4 Feature + 20 Unit) cobrindo: registro persiste e excluí callables, dispatch de cada método, wrapping de resultados (string + array + objeto), erros `-32600..-32603`, notifications retornam `[]`, preservação de `id` numérico/string

**Por chegar (MCP-003..010):**

- Artisan `arqel:mcp:serve` envolvendo `McpServer::serve()` — followup de MCP-002
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

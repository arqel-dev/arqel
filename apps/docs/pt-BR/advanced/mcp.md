# MCP server

> Pacote: [`arqel-dev/mcp`](../../packages/mcp/) · Tickets: MCP-001..010

## Purpose

`arqel-dev/mcp` expõe um servidor [Model Context Protocol](https://modelcontextprotocol.io) sobre os panels Arqel: **tools** (executar Actions, mutar Resources), **resources** (ler dados de Resource/Table/Widget), **prompts** (templates pré-construídos para fluxos comuns de admin).

Permite que clientes MCP — Claude Desktop, Cursor, Zed, agents customizados — operem o painel via JSON-RPC com a mesma autorização e validação que os usuários humanos vêem na UI Inertia.

A escolha é **aderir ao spec do protocol**: nenhum desvio de `modelcontextprotocol.io`; o pacote é só a tradução PHP/Laravel das primitivas (Tool, Resource, Prompt) e o glue que descobre Resources/Actions já registrados em `arqel-dev/core`.

## Setup — Claude Desktop

Edite o config:

- macOS: `~/Library/Application Support/Claude/claude_desktop_config.json`
- Windows: `%APPDATA%\Claude\claude_desktop_config.json`

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

> **Nota:** o comando Artisan `arqel:mcp:serve` ainda está deferred — entrega num ticket de follow-up envolvendo `McpServer::serve()`. Por enquanto, integradores podem instanciar `McpServer` num script PHP custom e chamar `serve()` direto:
>
> ```php
> $server = app(Arqel\Mcp\McpServer::class);
> $server->serveStreams(STDIN, STDOUT);
> ```

Setup guides para Cursor e Windsurf serão adicionados quando o Artisan command estabilizar.

## Tools shipped

Quatro tools auto-registradas no `McpServer` via `packageBooted`:

### `list_resources`

Lista todas as Resources de `Arqel\Core\Resources\ResourceRegistry`. Payload por entry: `{class, model, slug, label, pluralLabel, navigationGroup}`. Resource quebrada em metadata é silenciosamente ignorada.

### `describe_resource`

Input: `{slug: string (required)}`. Devolve 8 chaves estáticas (`class/slug/model/label/pluralLabel/navigationIcon/navigationGroup/navigationSort`). Slug desconhecido → `RuntimeException` (`-32602`).

### `generate_resource`

Wrapper de `arqel:resource` Artisan. Input: `{model: string, fromModel: bool, withPolicy: bool}`. Closure runner injetável; default delega para `Kernel::call`.

### `run_test`

Wrapper Pest/PHPUnit para TDD-loop. Input: `{filter, path, coverage, timeout}` — todos opcionais. **Path traversal guard**: rejeita `..` e paths absolutos com `InvalidArgumentException`. Timeout clampado em `[1, 600]` segundos (default 300).

## Resources shipped

### `arqel-skill://<package>`

`SkillResource` descobre `packages/*/SKILL.md` no monorepo. URI scheme valida regex `[a-z0-9-]+`. `list()` + `read(uri)`; uri inválida → `RuntimeException`. Reader Closure injetável para testes.

Útil para o cliente MCP introspeccionar **a documentação canónica** de cada pacote sem ler tickets soltos no `PLANNING/`.

## Prompts shipped

### `migrate_filament_resource`

Template que inlina o conteúdo de um arquivo PHP Filament (Resource ou Page) num fenced code block + diretrizes de migração para Arqel. Argumento required: `filament_file`.

### `review_resource`

Template para code-review de uma Resource Arqel. Argumento required: `resource_file`. Inlina o source + checklist (Policy, FormRequest, Field types, Action authorization).

Ambos têm **path traversal guard** (`..` rejeitado antes do reader).

## JSON-RPC envelope

Aderente ao spec MCP `2024-11-05`. Códigos de erro:

| Código | Quando |
|---|---|
| `-32600` | Envelope inválido |
| `-32601` | Method not found |
| `-32602` | Params inválidos / lookup falhou |
| `-32603` | Handler throw |
| `-32700` | Parse error (JSON malformado) |

Notifications (sem `id`) → resposta vazia `[]`. Result wrapping é automático:

- Tool → `{content: [{type: 'text', text}]}`
- Resource → `{contents: [{uri, text, mimeType?}]}`
- Prompt → `{description, messages}`

## Security considerations

- **Auth**: sessions MCP são JSON-RPC de longa duração, não requests Inertia — middleware HTTP do Laravel **não** se aplica. Auth precisa de pipeline próprio (token-bound, sem CSRF). Implementação canónica entrega em ticket futuro.
- **Resource exposure**: opt-in explícito. Modelos sensíveis (User, Tenant billing) **nunca** devem ser readable por default. Recomendação: flag em config + método `Resource::exposeToMcp(): bool` para exposição granular.
- **Path traversal**: `RunTestTool` e prompts rejeitam `..` e paths absolutos antes de tocar filesystem. Não bypass.
- **Spec-first**: nenhuma extensão custom além do spec. Quando o spec evolui, este pacote acompanha — não estende. Se precisar de algo, abra PR upstream em `modelcontextprotocol.io`.

## Anti-patterns

- ❌ Inventar tools/resources fora do spec MCP.
- ❌ Reusar HTTP middleware do Laravel para auth de MCP sessions.
- ❌ Expor todos os Resources do panel automaticamente.
- ❌ Bypass do path traversal guard em tools que tocam filesystem.

## Related

- [`packages/mcp/SKILL.md`](../../packages/mcp/SKILL.md)
- [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §MCP-001..010
- [Model Context Protocol spec](https://modelcontextprotocol.io)

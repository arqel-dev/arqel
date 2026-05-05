# MCP server

> Package: [`arqel-dev/mcp`](../../packages/mcp/) · Tickets: MCP-001..010

## Purpose

`arqel-dev/mcp` exposes a [Model Context Protocol](https://modelcontextprotocol.io) server over Arqel panels: **tools** (execute Actions, mutate Resources), **resources** (read data from Resource/Table/Widget), **prompts** (pre-built templates for common admin flows).

It lets MCP clients — Claude Desktop, Cursor, Zed, custom agents — drive the panel via JSON-RPC with the same authorization and validation that human users see in the Inertia UI.

The choice is to **adhere to the protocol spec**: no deviation from `modelcontextprotocol.io`; the package is just the PHP/Laravel translation of the primitives (Tool, Resource, Prompt) and the glue that discovers Resources/Actions already registered in `arqel-dev/core`.

## Setup — Claude Desktop

Edit the config:

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

> **Note:** the `arqel:mcp:serve` Artisan command is still deferred — it ships in a follow-up ticket involving `McpServer::serve()`. For now, integrators can instantiate `McpServer` in a custom PHP script and call `serve()` directly:
>
> ```php
> $server = app(Arqel\Mcp\McpServer::class);
> $server->serveStreams(STDIN, STDOUT);
> ```

Setup guides for Cursor and Windsurf will be added once the Artisan command stabilizes.

## Tools shipped

Four tools auto-registered on `McpServer` via `packageBooted`:

### `list_resources`

Lists all Resources from `Arqel\Core\Resources\ResourceRegistry`. Per-entry payload: `{class, model, slug, label, pluralLabel, navigationGroup}`. A Resource broken in metadata is silently ignored.

### `describe_resource`

Input: `{slug: string (required)}`. Returns 8 static keys (`class/slug/model/label/pluralLabel/navigationIcon/navigationGroup/navigationSort`). Unknown slug → `RuntimeException` (`-32602`).

### `generate_resource`

Wrapper for the `arqel:resource` Artisan command. Input: `{model: string, fromModel: bool, withPolicy: bool}`. Injectable Closure runner; default delegates to `Kernel::call`.

### `run_test`

Pest/PHPUnit wrapper for TDD-loop. Input: `{filter, path, coverage, timeout}` — all optional. **Path traversal guard**: rejects `..` and absolute paths with `InvalidArgumentException`. Timeout clamped to `[1, 600]` seconds (default 300).

## Resources shipped

### `arqel-skill://<package>`

`SkillResource` discovers `packages/*/SKILL.md` in the monorepo. URI scheme validates the regex `[a-z0-9-]+`. `list()` + `read(uri)`; invalid uri → `RuntimeException`. Reader Closure injectable for tests.

Useful for an MCP client to introspect **the canonical documentation** of each package without reading scattered tickets in `PLANNING/`.

## Prompts shipped

### `migrate_filament_resource`

Template that inlines the contents of a Filament PHP file (Resource or Page) inside a fenced code block + migration guidelines for Arqel. Required argument: `filament_file`.

### `review_resource`

Template for code-reviewing an Arqel Resource. Required argument: `resource_file`. Inlines the source + checklist (Policy, FormRequest, Field types, Action authorization).

Both have a **path traversal guard** (`..` rejected before the reader).

## JSON-RPC envelope

Adheres to MCP spec `2024-11-05`. Error codes:

| Code | When |
|---|---|
| `-32600` | Invalid envelope |
| `-32601` | Method not found |
| `-32602` | Invalid params / lookup failed |
| `-32603` | Handler throw |
| `-32700` | Parse error (malformed JSON) |

Notifications (no `id`) → empty response `[]`. Result wrapping is automatic:

- Tool → `{content: [{type: 'text', text}]}`
- Resource → `{contents: [{uri, text, mimeType?}]}`
- Prompt → `{description, messages}`

## Security considerations

- **Auth**: MCP sessions are long-running JSON-RPC, not Inertia requests — Laravel HTTP middleware **does not** apply. Auth needs its own pipeline (token-bound, no CSRF). Canonical implementation ships in a future ticket.
- **Resource exposure**: explicit opt-in. Sensitive models (User, Tenant billing) **never** should be readable by default. Recommendation: a config flag + a `Resource::exposeToMcp(): bool` method for granular exposure.
- **Path traversal**: `RunTestTool` and prompts reject `..` and absolute paths before touching the filesystem. Don't bypass.
- **Spec-first**: no custom extensions beyond the spec. When the spec evolves, this package follows — it doesn't extend. If you need something, open an upstream PR at `modelcontextprotocol.io`.

## Anti-patterns

- ❌ Inventing tools/resources outside the MCP spec.
- ❌ Reusing Laravel HTTP middleware for MCP session auth.
- ❌ Exposing every panel Resource automatically.
- ❌ Bypassing the path traversal guard in tools that touch the filesystem.

## Related

- [`packages/mcp/SKILL.md`](../../packages/mcp/SKILL.md)
- [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §MCP-001..010
- [Model Context Protocol spec](https://modelcontextprotocol.io)

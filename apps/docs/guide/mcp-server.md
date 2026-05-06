# MCP server

> Package: [`@arqel-dev/mcp-server`](https://www.npmjs.com/package/@arqel-dev/mcp-server) · Roadmap: [`PLANNING/13-pos-mvp-mcp-server.md`](https://github.com/arqel-dev/arqel/blob/main/PLANNING/13-pos-mvp-mcp-server.md)

The **Arqel MCP server** is the framework's official [Model Context Protocol](https://modelcontextprotocol.io) server. Shipped as an npm package with the `arqel-mcp` binary running over stdio, it gives AI assistants — Claude Code, Cursor, Copilot CLI, Gemini CLI — direct access to Arqel docs, ADRs, API reference (PHP + TypeScript), Laravel project introspection, and scaffolding consistent with project conventions.

## Why use it

- **Always-fresh docs** — the published tarball bundles a copy of `apps/docs/`, the ADRs, and the API reference, so the assistant never quotes a stale version pulled from training data.
- **Project-aware introspection** — introspection tools discover real Resources registered in the user's Laravel project by calling `php artisan arqel:introspect --json`.
- **Consistent scaffolding** — Resource and Field generation uses the same canonical stubs as `arqel-dev/core`, preventing the assistant from improvising code outside conventions.

## Installation

### Claude Code

```bash
claude mcp add arqel npx -- -y @arqel-dev/mcp-server
```

This registers the server with Claude Code. The first invocation has `npx` download the package; subsequent calls use the cache.

### Cursor

Edit (or create) `.cursor/mcp.json` at the project root:

```json
{
  "mcpServers": {
    "arqel": {
      "command": "npx",
      "args": ["-y", "@arqel-dev/mcp-server"]
    }
  }
}
```

Restart Cursor. The server shows up in the active MCPs list.

### Copilot CLI / Gemini CLI

MCP configuration formats vary per client and still evolve quickly. Consult your client's MCP setup documentation — the canonical shape is always a `command: "npx"` + `args: ["-y", "@arqel-dev/mcp-server"]` entry.

## Available tools

The server exposes **7 tools** (additions tracked in [`PLANNING/13-pos-mvp-mcp-server.md`](https://github.com/arqel-dev/arqel/blob/main/PLANNING/13-pos-mvp-mcp-server.md)):

| Tool | Description |
|---|---|
| `search_docs(query, limit?)` | Search the docs corpus (BM25 over `apps/docs/`). |
| `get_adr(id)` | Return the full content of an ADR (`ADR-001` … `ADR-018`). |
| `get_api_reference(symbol, language?)` | Return the official reference for a PHP or TypeScript symbol. |
| `list_resources(projectPath?)` | List Arqel Resources registered in the user's Laravel project. |
| `describe_resource(class, projectPath?)` | Return structured metadata for a specific Resource (model, fields, navigation). |
| `generate_resource(model, fields[], …)` | Generate the `<Model>Resource.php` file from the canonical stub. |
| `generate_field(name, type, options?)` | Generate a Field declaration (e.g., `Text::make('title')->required()`). |

Example call (JSON-RPC, executed internally by the MCP client):

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "name": "search_docs",
    "arguments": { "query": "field types", "limit": 5 }
  }
}
```

## Laravel project resolution

Introspection tools (`list_resources`, `describe_resource`) need to know which Laravel project to inspect. Resolution follows this order:

1. **`projectPath` argument** passed on the tool call (highest precedence).
2. **`ARQEL_PROJECT_PATH` environment variable** defined in the MCP server's environment.
3. **Auto walk-up** from the server `cwd` — climbs directories looking for an `artisan` file.

::: tip Custom PHP binary
If `php` isn't on `$PATH` (Herd, Valet, Docker, projects with versioned PHP), set `ARQEL_PHP_BIN` pointing to the correct binary. Example:

```bash
export ARQEL_PHP_BIN=/Users/me/Library/Application\ Support/Herd/bin/php
```
:::

## Source repository

- Source: [`packages-js/mcp-server/`](https://github.com/arqel-dev/arqel/tree/main/packages-js/mcp-server)
- Post-MVP roadmap: [`PLANNING/13-pos-mvp-mcp-server.md`](https://github.com/arqel-dev/arqel/blob/main/PLANNING/13-pos-mvp-mcp-server.md)
- MCP spec: [modelcontextprotocol.io](https://modelcontextprotocol.io)

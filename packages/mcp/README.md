# arqel-dev/mcp

Model Context Protocol server for Arqel — exposes panel resources, fields, tables, and actions to MCP-compatible AI clients (Claude Desktop, Cursor, Zed, etc.).

## Status

Shipped. The JSON-RPC 2.0 handler (`McpServer`), the tool/resource/prompt registries, and panel autoloading (4 tools, 1 resource, 2 prompts via `McpServiceProvider`) are implemented. The only remaining work is the `arqel:mcp:serve` Artisan command wrapper — for now, instantiate `McpServer` and call `serve()` from a custom script. See [`SKILL.md`](./SKILL.md) for the full contract surface.

## Install

In a Laravel app already running `arqel-dev/core`:

```bash
composer require arqel-dev/mcp
```

The service provider is auto-discovered.

## Tests

```bash
composer test
```

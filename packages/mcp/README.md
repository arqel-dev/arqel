# arqel/mcp

Model Context Protocol server for Arqel — exposes panel resources, fields, tables, and actions to MCP-compatible AI clients (Claude Desktop, Cursor, Zed, etc.).

## Status

Phase 2 scaffold (MCP-001). The JSON-RPC handler, `serve` Artisan command, tool/resource/prompt registries, and panel autoloading land in MCP-002..010. See [`SKILL.md`](./SKILL.md) for the full contract surface and roadmap.

## Install

In a Laravel app already running `arqel/core`:

```bash
composer require arqel/mcp
```

The service provider is auto-discovered.

## Tests

```bash
composer test
```

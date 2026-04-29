<?php

declare(strict_types=1);

namespace Arqel\Mcp;

/**
 * Model Context Protocol server entrypoint.
 *
 * **MCP-001 scaffold — implementation lands in MCP-002+.**
 *
 * This class deliberately mirrors the TENANT-001-style "stub class
 * with real method signatures" pattern: every public method below
 * has the signature it will keep once the JSON-RPC handler, tool
 * registry, resource fetchers, and prompt generators are wired up.
 * Downstream packages (`arqel/core`, `arqel/widgets`, app-level
 * panels) can type-hint `McpServer` and call the registration
 * methods today — calls are no-ops, getters return empty arrays —
 * so once MCP-002 lands no consumer needs nullable shims.
 *
 * Roadmap (see `PLANNING/09-fase-2-essenciais.md` §MCP-001..010):
 *
 *  - MCP-002 — JSON-RPC 2.0 transport + `serve` Artisan command
 *  - MCP-003 — Tool registry honours `registerTool()` calls
 *  - MCP-004 — Resource registry honours `registerResource()`
 *  - MCP-005 — Prompt registry honours `registerPrompt()`
 *  - MCP-006..010 — auth, panel autoloading, polish
 */
final class McpServer
{
    /**
     * Register an MCP tool. No-op until MCP-003.
     *
     * @param array<string, mixed> $inputSchema JSON Schema describing the tool input.
     * @param callable(array<string, mixed>): mixed $handler Invoked with validated args.
     */
    public function registerTool(string $name, string $description, array $inputSchema, callable $handler): void
    {
        // Stub: real implementation in MCP-003.
    }

    /**
     * Register an MCP resource. No-op until MCP-004.
     *
     * @param callable(string): mixed $fetcher Invoked with the resource URI.
     */
    public function registerResource(string $uri, string $name, string $description, callable $fetcher): void
    {
        // Stub: real implementation in MCP-004.
    }

    /**
     * Register an MCP prompt template. No-op until MCP-005.
     *
     * @param array<int, array<string, mixed>> $arguments Argument schema list.
     * @param callable(array<string, mixed>): string $generator Renders the prompt body.
     */
    public function registerPrompt(string $name, string $description, array $arguments, callable $generator): void
    {
        // Stub: real implementation in MCP-005.
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTools(): array
    {
        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getResources(): array
    {
        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPrompts(): array
    {
        return [];
    }
}

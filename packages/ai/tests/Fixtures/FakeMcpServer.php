<?php

declare(strict_types=1);

namespace Arqel\Ai\Tests\Fixtures;

/**
 * Mock leve do `Arqel\Mcp\McpServer` usado para validar o registo
 * cross-package das tools AI-MCP sem depender do pacote `arqel-dev/mcp`.
 *
 * Replica apenas a assinatura pública de `registerTool()` consumida
 * pelo `AiServiceProvider::packageBooted()`.
 */
final class FakeMcpServer
{
    /**
     * @var array<string, array{description: string, inputSchema: array<string, mixed>, handler: callable}>
     */
    public array $tools = [];

    /**
     * @param array<string, mixed> $inputSchema
     * @param callable(array<string, mixed>): mixed $handler
     */
    public function registerTool(string $name, string $description, array $inputSchema, callable $handler): void
    {
        $this->tools[$name] = [
            'description' => $description,
            'inputSchema' => $inputSchema,
            'handler' => $handler,
        ];
    }
}

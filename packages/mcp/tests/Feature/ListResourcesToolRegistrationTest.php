<?php

declare(strict_types=1);

use Arqel\Mcp\McpServer;

it('auto-registers the list_resources tool when the package boots', function (): void {
    $server = app(McpServer::class);

    expect($server->getTools())->toHaveKey('list_resources')
        ->and($server->getTools()['list_resources']['description'])
        ->toBe('List all Arqel Resources registered in the application')
        ->and($server->getTools()['list_resources']['inputSchema'])
        ->toBe(['type' => 'object', 'properties' => []]);
});

it('dispatches list_resources via tools/call and returns a JSON-encoded resources payload', function (): void {
    $server = app(McpServer::class);

    $response = $server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => 'list_resources', 'arguments' => []],
    ]);

    expect($response['result']['content'][0]['type'])->toBe('text');

    /** @var array{resources: array<int, array<string, mixed>>} $decoded */
    $decoded = json_decode((string) $response['result']['content'][0]['text'], true);

    expect($decoded)->toHaveKey('resources')
        ->and($decoded['resources'])->toBeArray();
});

<?php

declare(strict_types=1);

use Arqel\Mcp\McpServer;

it('auto-registers describe_resource alongside list_resources when the package boots', function (): void {
    $server = app(McpServer::class);

    expect($server->getTools())->toHaveKey('describe_resource')
        ->and($server->getTools())->toHaveKey('list_resources')
        ->and($server->getTools()['describe_resource']['description'])
        ->toBe('Get detailed information about a specific Arqel Resource')
        ->and($server->getTools()['describe_resource']['inputSchema'])
        ->toBe([
            'type' => 'object',
            'properties' => [
                'slug' => [
                    'type' => 'string',
                    'description' => 'The Resource slug (e.g., "users")',
                ],
            ],
            'required' => ['slug'],
        ]);
});

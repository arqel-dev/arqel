<?php

declare(strict_types=1);

use Arqel\Mcp\McpServer;

it('auto-registers generate_resource alongside the other built-in tools when the package boots', function (): void {
    $server = app(McpServer::class);

    $tools = $server->getTools();

    expect($tools)->toHaveKey('generate_resource')
        ->and($tools)->toHaveKey('list_resources')
        ->and($tools)->toHaveKey('describe_resource')
        ->and($tools['generate_resource']['description'])
        ->toBe('Generate a new Arqel Resource for an Eloquent model')
        ->and($tools['generate_resource']['inputSchema']['required'])
        ->toBe(['model'])
        ->and($tools['generate_resource']['inputSchema']['properties'])
        ->toHaveKeys(['model', 'fromModel', 'withPolicy']);
});

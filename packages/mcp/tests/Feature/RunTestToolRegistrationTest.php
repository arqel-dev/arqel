<?php

declare(strict_types=1);

use Arqel\Mcp\McpServer;

it('auto-registers all four built-in tools when the package boots', function (): void {
    $server = app(McpServer::class);

    $tools = $server->getTools();

    expect($tools)->toHaveKey('list_resources')
        ->and($tools)->toHaveKey('describe_resource')
        ->and($tools)->toHaveKey('generate_resource')
        ->and($tools)->toHaveKey('run_test')
        ->and($tools['run_test']['description'])
        ->toBe('Run Pest or PHPUnit tests with optional filter')
        ->and($tools['run_test']['inputSchema']['properties'])
        ->toHaveKeys(['filter', 'path', 'coverage'])
        ->and($tools['run_test']['inputSchema'])
        ->not->toHaveKey('required');
});

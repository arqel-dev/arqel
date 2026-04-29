<?php

declare(strict_types=1);

use Arqel\Mcp\McpServer;

it('boots the mcp service provider in a Testbench app', function (): void {
    expect(true)->toBeTrue();
});

it('autoloads the Arqel\\Mcp namespace', function (): void {
    expect(class_exists(McpServer::class))->toBeTrue();
});

it('binds McpServer as a singleton', function (): void {
    $first = app(McpServer::class);
    $second = app(McpServer::class);

    expect($first)->toBeInstanceOf(McpServer::class)
        ->and($second)->toBe($first);
});

it('exposes stub registration methods and empty registry getters', function (): void {
    $server = app(McpServer::class);

    $server->registerTool('noop', 'desc', ['type' => 'object'], fn (array $args): array => $args);
    $server->registerResource('arqel://noop', 'noop', 'desc', fn (string $uri): string => $uri);
    $server->registerPrompt('noop', 'desc', [], fn (array $args): string => 'noop');

    expect($server->getTools())->toBe([])
        ->and($server->getResources())->toBe([])
        ->and($server->getPrompts())->toBe([]);
});

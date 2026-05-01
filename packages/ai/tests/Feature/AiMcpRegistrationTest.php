<?php

declare(strict_types=1);

use Arqel\Ai\AiServiceProvider;
use Arqel\Ai\Tests\Fixtures\FakeMcpServer;
use Illuminate\Contracts\Foundation\Application;

/**
 * Garante que o registo cross-package das tools AI-MCP é defensivo:
 * só dispara quando `Arqel\Mcp\McpServer` está bound; é silencioso
 * quando ausente; idempotente quando chamado mais que uma vez.
 *
 * Nota: o `class_alias` cria `Arqel\Mcp\McpServer` apontando para o
 * `FakeMcpServer` apenas durante este suite de testes (uma vez no
 * processo PHP). Os testes que dependem de "ausência" do alias
 * funcionam controlando apenas o binding no container.
 */
beforeAll(function (): void {
    if (! class_exists('Arqel\\Mcp\\McpServer', false)) {
        class_alias(FakeMcpServer::class, 'Arqel\\Mcp\\McpServer');
    }
});

it('registers the 3 AI MCP tools when McpServer is bound', function (): void {
    /** @var Application $appInstance */
    $appInstance = app();

    $server = new FakeMcpServer;
    $appInstance->instance('Arqel\\Mcp\\McpServer', $server);

    $provider = new AiServiceProvider($appInstance);
    $provider->packageBooted();

    expect(array_keys($server->tools))->toContain(
        'generate_resource_from_description',
        'analyze_resource',
        'suggest_fields',
    );
    expect($server->tools)->toHaveCount(3);
});

it('skips registration silently when McpServer is not bound', function (): void {
    /** @var Application $appInstance */
    $appInstance = app();
    $appInstance->forgetInstance('Arqel\\Mcp\\McpServer');

    $provider = new AiServiceProvider($appInstance);

    expect(fn () => $provider->packageBooted())->not->toThrow(Throwable::class);
    expect($appInstance->bound('Arqel\\Mcp\\McpServer'))->toBeFalse();
});

it('is idempotent when invoked multiple times', function (): void {
    /** @var Application $appInstance */
    $appInstance = app();

    $server = new FakeMcpServer;
    $appInstance->instance('Arqel\\Mcp\\McpServer', $server);

    $provider = new AiServiceProvider($appInstance);
    $provider->packageBooted();
    $provider->packageBooted();
    $provider->packageBooted();

    expect($server->tools)->toHaveCount(3);
});

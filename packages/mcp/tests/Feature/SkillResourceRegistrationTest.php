<?php

declare(strict_types=1);

use Arqel\Mcp\McpServer;
use Arqel\Mcp\Resources\SkillResource;

beforeEach(function (): void {
    // Swap the bound SkillResource with one driven by closures so we
    // never hit the real filesystem during package boot.
    $this->app->instance(SkillResource::class, new SkillResource(
        packagesResolver: static fn (): array => ['core', 'mcp'],
        contentReader: static fn (string $package): string => "# SKILL.md fixture for arqel/{$package}",
    ));

    // Re-run the boot block now that we've replaced the binding. We
    // mirror what `McpServiceProvider::packageBooted` does for the
    // skill auto-registration.
    /** @var McpServer $server */
    $server = $this->app->make(McpServer::class);
    /** @var SkillResource $skillResource */
    $skillResource = $this->app->make(SkillResource::class);
    foreach ($skillResource->list() as $entry) {
        $uri = $entry['uri'];
        $server->registerResource(
            $uri,
            $entry['name'],
            $entry['description'],
            static fn (string $resourceUri): array => $skillResource->read($resourceUri),
        );
    }
});

it('auto-registers one McpServer resource per package on boot', function (): void {
    /** @var McpServer $server */
    $server = $this->app->make(McpServer::class);

    $resources = $server->getResources();

    expect($resources)->toHaveKey('arqel-skill://core')
        ->and($resources)->toHaveKey('arqel-skill://mcp')
        ->and($resources['arqel-skill://core']['name'])->toBe('SKILL.md for arqel-dev/core')
        ->and($resources['arqel-skill://core']['description'])->toBe('AI agent context for the core package');
});

it('dispatches resources/read for a registered SKILL.md URI and returns the markdown text payload', function (): void {
    /** @var McpServer $server */
    $server = $this->app->make(McpServer::class);

    $response = $server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 42,
        'method' => 'resources/read',
        'params' => ['uri' => 'arqel-skill://mcp'],
    ]);

    expect($response['result']['contents'][0]['uri'])->toBe('arqel-skill://mcp')
        ->and($response['result']['contents'][0]['text'])
        ->toContain('# SKILL.md fixture for arqel-dev/mcp');
});

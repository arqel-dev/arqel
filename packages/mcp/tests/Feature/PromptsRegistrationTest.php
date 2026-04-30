<?php

declare(strict_types=1);

use Arqel\Mcp\McpServer;

it('auto-registers both prompts on package boot with their canonical schemas', function (): void {
    /** @var McpServer $server */
    $server = $this->app->make(McpServer::class);

    $prompts = $server->getPrompts();

    expect($prompts)->toHaveKey('migrate_filament_resource')
        ->and($prompts)->toHaveKey('review_resource')
        ->and($prompts['migrate_filament_resource']['description'])->toBe('Help migrate a Filament Resource to Arqel')
        ->and($prompts['migrate_filament_resource']['arguments'][0]['name'])->toBe('filament_file')
        ->and($prompts['migrate_filament_resource']['arguments'][0]['required'])->toBeTrue()
        ->and($prompts['review_resource']['description'])->toBe('Review an existing Arqel Resource for issues, code smells, and improvement opportunities')
        ->and($prompts['review_resource']['arguments'][0]['name'])->toBe('resource_file')
        ->and($prompts['review_resource']['arguments'][0]['required'])->toBeTrue();
});

it('dispatches prompts/get for migrate_filament_resource using a real file under the project base_path', function (): void {
    /** @var McpServer $server */
    $server = $this->app->make(McpServer::class);

    // Drop a tiny PHP fixture under the Testbench base_path so the
    // default file reader (which resolves against `path.base`) succeeds.
    $base = $this->app->basePath();
    $relative = 'mcp_prompts_fixture_'.uniqid().'.php';
    $absolute = $base.'/'.$relative;
    file_put_contents($absolute, "<?php\n\nclass FixtureResource {}\n");

    try {
        $response = $server->handleRequest([
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => 'prompts/get',
            'params' => [
                'name' => 'migrate_filament_resource',
                'arguments' => ['filament_file' => $relative],
            ],
        ]);

        expect($response['result']['description'])->toBe('Help migrate a Filament Resource to Arqel')
            ->and($response['result']['messages'])->toHaveCount(1)
            ->and($response['result']['messages'][0]['role'])->toBe('user');

        $text = $response['result']['messages'][0]['content'][0]['text'];
        expect($text)->toContain('class FixtureResource')
            ->and($text)->toContain($relative);
    } finally {
        @unlink($absolute);
    }
});

it('returns a JSON-RPC error when prompts/get is called with an unknown prompt name', function (): void {
    /** @var McpServer $server */
    $server = $this->app->make(McpServer::class);

    $response = $server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 8,
        'method' => 'prompts/get',
        'params' => ['name' => 'does_not_exist'],
    ]);

    expect($response)->toHaveKey('error')
        ->and($response['error']['code'])->toBe(-32602);
});

<?php

declare(strict_types=1);

use Arqel\Mcp\McpServer;
use Arqel\Mcp\Resources\SkillResource;

/**
 * Integration tests that drive `McpServer::serveStreams()` end-to-end
 * via `php://memory` handles, simulating a full Claude Desktop / Cursor
 * MCP conversation over newline-delimited JSON-RPC.
 *
 * MCP-009 (scoped) — see PLANNING/09-fase-2-essenciais.md.
 */

/**
 * Open an in-memory bidirectional stream pair: a single php://memory
 * handle for input (caller writes the conversation script + rewinds)
 * and another for output (server writes responses, caller rewinds to
 * read them line by line).
 *
 * @return array{0: resource, 1: resource}
 */
function mcp_streams(string $script): array
{
    $input = fopen('php://memory', 'rw+');
    if ($input === false) {
        throw new RuntimeException('Failed to open input php://memory stream');
    }
    fwrite($input, $script);
    rewind($input);

    $output = fopen('php://memory', 'rw+');
    if ($output === false) {
        throw new RuntimeException('Failed to open output php://memory stream');
    }

    return [$input, $output];
}

/**
 * Encode each request as one JSON object per line — exactly the wire
 * format Claude Desktop / Cursor speak over stdio.
 *
 * @param array<int, array<string, mixed>> $requests
 */
function mcp_script(array $requests): string
{
    $lines = [];
    foreach ($requests as $request) {
        $lines[] = (string) json_encode($request, JSON_UNESCAPED_SLASHES);
    }

    return implode("\n", $lines)."\n";
}

/**
 * Read all newline-delimited JSON responses written to a memory stream
 * and decode them in order.
 *
 * @param resource $output
 *
 * @return array<int, array<string, mixed>>
 */
function mcp_read_responses($output): array
{
    rewind($output);
    $raw = stream_get_contents($output);
    if (! is_string($raw) || $raw === '') {
        return [];
    }

    $responses = [];
    foreach (explode("\n", $raw) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        /** @var mixed $decoded */
        $decoded = json_decode($line, true);
        if (is_array($decoded)) {
            $responses[] = $decoded;
        }
    }

    return $responses;
}

beforeEach(function (): void {
    // Swap SkillResource with a closure-driven fake so package boot
    // does not hit the real packages/* filesystem during integration.
    $this->app->instance(SkillResource::class, new SkillResource(
        packagesResolver: static fn (): array => ['core', 'mcp'],
        contentReader: static fn (string $package): string => "# SKILL.md fixture for arqel/{$package}",
    ));

    /** @var McpServer $server */
    $server = $this->app->make(McpServer::class);
    /** @var SkillResource $skillResource */
    $skillResource = $this->app->make(SkillResource::class);
    foreach ($skillResource->list() as $entry) {
        $server->registerResource(
            $entry['uri'],
            $entry['name'],
            $entry['description'],
            static fn (string $resourceUri): array => $skillResource->read($resourceUri),
        );
    }
});

it('drives a full Claude Desktop conversation: initialize → tools/list → tools/call → result', function (): void {
    /** @var McpServer $server */
    $server = $this->app->make(McpServer::class);

    // Replace `list_resources` with a fixture that does not touch the
    // ResourceRegistry singleton state.
    $server->registerTool(
        'echo',
        'Echo back a message',
        ['type' => 'object', 'properties' => ['msg' => ['type' => 'string']]],
        static fn (array $args): string => 'echoed: '.($args['msg'] ?? ''),
    );

    [$input, $output] = mcp_streams(mcp_script([
        ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'],
        ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'],
        ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => [
            'name' => 'echo',
            'arguments' => ['msg' => 'hello arqel'],
        ]],
        ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
    ]));

    $server->serveStreams($input, $output);

    $responses = mcp_read_responses($output);

    // Three responses (notification produces no output).
    expect($responses)->toHaveCount(3);

    // initialize.
    expect($responses[0]['id'])->toBe(1)
        ->and($responses[0]['result']['protocolVersion'])->toBe('2024-11-05')
        ->and($responses[0]['result']['serverInfo']['name'])->toBe('arqel-mcp');

    // tools/list contains echo plus the four registered tools.
    expect($responses[1]['id'])->toBe(2);
    $toolNames = array_column($responses[1]['result']['tools'], 'name');
    expect($toolNames)->toContain('echo')
        ->and($toolNames)->toContain('list_resources')
        ->and($toolNames)->toContain('describe_resource')
        ->and($toolNames)->toContain('generate_resource')
        ->and($toolNames)->toContain('run_test');

    // tools/call returns wrapped content.
    expect($responses[2]['id'])->toBe(3)
        ->and($responses[2]['result']['content'][0]['type'])->toBe('text')
        ->and($responses[2]['result']['content'][0]['text'])->toBe('echoed: hello arqel');

    fclose($input);
    fclose($output);
});

it('lists exactly the four package-registered tools after a fresh boot', function (): void {
    [$input, $output] = mcp_streams(mcp_script([
        ['jsonrpc' => '2.0', 'id' => 'list-1', 'method' => 'tools/list'],
    ]));

    /** @var McpServer $server */
    $server = $this->app->make(McpServer::class);
    $server->serveStreams($input, $output);

    $responses = mcp_read_responses($output);

    expect($responses)->toHaveCount(1)
        ->and($responses[0]['id'])->toBe('list-1');

    $toolNames = array_column($responses[0]['result']['tools'], 'name');
    expect($toolNames)->toContain('list_resources')
        ->and($toolNames)->toContain('describe_resource')
        ->and($toolNames)->toContain('generate_resource')
        ->and($toolNames)->toContain('run_test');

    fclose($input);
    fclose($output);
});

it('completes a resources/list → resources/read cycle for SkillResource', function (): void {
    [$input, $output] = mcp_streams(mcp_script([
        ['jsonrpc' => '2.0', 'id' => 10, 'method' => 'resources/list'],
        ['jsonrpc' => '2.0', 'id' => 11, 'method' => 'resources/read', 'params' => [
            'uri' => 'arqel-skill://mcp',
        ]],
    ]));

    /** @var McpServer $server */
    $server = $this->app->make(McpServer::class);
    $server->serveStreams($input, $output);

    $responses = mcp_read_responses($output);

    expect($responses)->toHaveCount(2);

    $uris = array_column($responses[0]['result']['resources'], 'uri');
    expect($uris)->toContain('arqel-skill://core')
        ->and($uris)->toContain('arqel-skill://mcp');

    expect($responses[1]['result']['contents'][0]['uri'])->toBe('arqel-skill://mcp')
        ->and($responses[1]['result']['contents'][0]['text'])
        ->toContain('# SKILL.md fixture for arqel/mcp');

    fclose($input);
    fclose($output);
});

it('completes a prompts/list → prompts/get cycle for review_resource', function (): void {
    $base = $this->app->basePath();
    $relative = 'mcp_conversation_fixture_'.uniqid().'.php';
    $absolute = $base.'/'.$relative;
    file_put_contents($absolute, "<?php\n\nclass ConversationFixture {}\n");

    try {
        [$input, $output] = mcp_streams(mcp_script([
            ['jsonrpc' => '2.0', 'id' => 20, 'method' => 'prompts/list'],
            ['jsonrpc' => '2.0', 'id' => 21, 'method' => 'prompts/get', 'params' => [
                'name' => 'review_resource',
                'arguments' => ['resource_file' => $relative],
            ]],
        ]));

        /** @var McpServer $server */
        $server = $this->app->make(McpServer::class);
        $server->serveStreams($input, $output);

        $responses = mcp_read_responses($output);

        expect($responses)->toHaveCount(2);

        $names = array_column($responses[0]['result']['prompts'], 'name');
        expect($names)->toContain('migrate_filament_resource')
            ->and($names)->toContain('review_resource');

        expect($responses[1]['result']['description'])
            ->toBe('Review an existing Arqel Resource for issues, code smells, and improvement opportunities')
            ->and($responses[1]['result']['messages'])->toHaveCount(1);

        $text = $responses[1]['result']['messages'][0]['content'][0]['text'];
        expect($text)->toContain('class ConversationFixture');

        fclose($input);
        fclose($output);
    } finally {
        @unlink($absolute);
    }
});

it('emits a -32700 Parse error for a malformed JSON line and continues the loop', function (): void {
    $script = "this is not json\n"
        .(string) json_encode([
            'jsonrpc' => '2.0', 'id' => 99, 'method' => 'initialize',
        ], JSON_UNESCAPED_SLASHES)."\n";

    [$input, $output] = mcp_streams($script);

    /** @var McpServer $server */
    $server = $this->app->make(McpServer::class);
    $server->serveStreams($input, $output);

    $responses = mcp_read_responses($output);

    expect($responses)->toHaveCount(2);

    // Parse error envelope: id=null, code=-32700.
    expect($responses[0]['id'])->toBeNull()
        ->and($responses[0]['error']['code'])->toBe(-32700)
        ->and($responses[0]['error']['message'])->toBe('Parse error');

    // The loop survived and processed the second (well-formed) line.
    expect($responses[1]['id'])->toBe(99)
        ->and($responses[1]['result']['protocolVersion'])->toBe('2024-11-05');

    fclose($input);
    fclose($output);
});

it('skips blank lines without producing output and still answers subsequent requests', function (): void {
    $script = "\n   \n"
        .(string) json_encode([
            'jsonrpc' => '2.0', 'id' => 'after-blank', 'method' => 'tools/list',
        ], JSON_UNESCAPED_SLASHES)."\n";

    [$input, $output] = mcp_streams($script);

    /** @var McpServer $server */
    $server = $this->app->make(McpServer::class);
    $server->serveStreams($input, $output);

    $responses = mcp_read_responses($output);

    expect($responses)->toHaveCount(1)
        ->and($responses[0]['id'])->toBe('after-blank')
        ->and($responses[0]['result'])->toHaveKey('tools');

    fclose($input);
    fclose($output);
});

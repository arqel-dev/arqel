<?php

declare(strict_types=1);

use Arqel\Mcp\McpServer;

beforeEach(function (): void {
    $this->server = new McpServer;
});

it('stores tool metadata via registerTool and returns it without the handler', function (): void {
    $this->server->registerTool(
        'echo',
        'Echo back the args',
        ['type' => 'object', 'properties' => ['msg' => ['type' => 'string']]],
        fn (array $args): array => $args,
    );

    $tools = $this->server->getTools();

    expect($tools)->toHaveKey('echo')
        ->and($tools['echo'])->toBe([
            'description' => 'Echo back the args',
            'inputSchema' => ['type' => 'object', 'properties' => ['msg' => ['type' => 'string']]],
        ])
        ->and($tools['echo'])->not->toHaveKey('handler');
});

it('stores resource metadata via registerResource and excludes the fetcher', function (): void {
    $this->server->registerResource(
        'arqel://users',
        'Users',
        'List of users',
        fn (string $uri): string => "fetched:$uri",
    );

    $resources = $this->server->getResources();

    expect($resources)->toHaveKey('arqel://users')
        ->and($resources['arqel://users'])->toBe([
            'name' => 'Users',
            'description' => 'List of users',
        ])
        ->and($resources['arqel://users'])->not->toHaveKey('fetcher');
});

it('stores prompt metadata via registerPrompt and excludes the generator', function (): void {
    $this->server->registerPrompt(
        'greet',
        'Greeting prompt',
        [['name' => 'who', 'required' => true]],
        fn (array $args): array => [],
    );

    $prompts = $this->server->getPrompts();

    expect($prompts)->toHaveKey('greet')
        ->and($prompts['greet'])->toBe([
            'description' => 'Greeting prompt',
            'arguments' => [['name' => 'who', 'required' => true]],
        ])
        ->and($prompts['greet'])->not->toHaveKey('generator');
});

it('handles initialize with the canonical envelope', function (): void {
    $response = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
    ]);

    expect($response['jsonrpc'])->toBe('2.0')
        ->and($response['id'])->toBe(1)
        ->and($response['result']['protocolVersion'])->toBe('2024-11-05')
        ->and($response['result']['serverInfo'])->toBe([
            'name' => 'arqel-mcp',
            'version' => '0.1.0-alpha',
        ])
        ->and($response['result']['capabilities'])->toHaveKeys(['tools', 'resources', 'prompts']);
});

it('lists registered tools via tools/list', function (): void {
    $this->server->registerTool('a', 'A', ['type' => 'object'], fn () => 'a');
    $this->server->registerTool('b', 'B', ['type' => 'object'], fn () => 'b');

    $response = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 'list-1',
        'method' => 'tools/list',
    ]);

    expect($response['result']['tools'])->toHaveCount(2)
        ->and($response['result']['tools'][0]['name'])->toBe('a')
        ->and($response['result']['tools'][1]['name'])->toBe('b');
});

it('invokes the tool handler with arguments and wraps a string result', function (): void {
    $this->server->registerTool(
        'shout',
        'Shout',
        ['type' => 'object'],
        fn (array $args): string => 'HELLO '.($args['who'] ?? 'world'),
    );

    $response = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => ['name' => 'shout', 'arguments' => ['who' => 'arqel']],
    ]);

    expect($response['result']['content'])->toBe([
        ['type' => 'text', 'text' => 'HELLO arqel'],
    ]);
});

it('json-encodes array results from tool handlers', function (): void {
    $this->server->registerTool(
        'pair',
        'Pair',
        ['type' => 'object'],
        fn (array $args): array => ['a' => 1, 'b' => 2],
    );

    $response = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'tools/call',
        'params' => ['name' => 'pair', 'arguments' => []],
    ]);

    expect($response['result']['content'][0]['text'])->toBe('{"a":1,"b":2}');
});

it('json-encodes object results from tool handlers', function (): void {
    $this->server->registerTool(
        'obj',
        'Obj',
        ['type' => 'object'],
        fn (array $args): object => (object) ['x' => 42],
    );

    $response = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 4,
        'method' => 'tools/call',
        'params' => ['name' => 'obj', 'arguments' => []],
    ]);

    expect($response['result']['content'][0]['text'])->toBe('{"x":42}');
});

it('returns -32602 when tools/call targets an unknown tool', function (): void {
    $response = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 5,
        'method' => 'tools/call',
        'params' => ['name' => 'missing', 'arguments' => []],
    ]);

    expect($response['error']['code'])->toBe(-32602)
        ->and($response['error']['message'])->toBe('Tool not found: missing');
});

it('returns -32603 when a tool handler throws and surfaces the exception message', function (): void {
    $this->server->registerTool(
        'boom',
        'Boom',
        ['type' => 'object'],
        function (array $args): never {
            throw new RuntimeException('kaboom');
        },
    );

    $response = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 6,
        'method' => 'tools/call',
        'params' => ['name' => 'boom', 'arguments' => []],
    ]);

    expect($response['error']['code'])->toBe(-32603)
        ->and($response['error']['message'])->toBe('Internal error: kaboom')
        ->and($response['error']['data']['exception'])->toBe('kaboom');
});

it('lists and reads resources', function (): void {
    $this->server->registerResource(
        'arqel://hello',
        'Hello',
        'Greeting resource',
        fn (string $uri): string => "content of $uri",
    );

    $list = $this->server->handleRequest([
        'jsonrpc' => '2.0', 'id' => 'r1', 'method' => 'resources/list',
    ]);
    expect($list['result']['resources'])->toHaveCount(1)
        ->and($list['result']['resources'][0]['uri'])->toBe('arqel://hello');

    $read = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 'r2',
        'method' => 'resources/read',
        'params' => ['uri' => 'arqel://hello'],
    ]);
    expect($read['result']['contents'])->toBe([
        ['uri' => 'arqel://hello', 'text' => 'content of arqel://hello'],
    ]);
});

it('returns -32602 when resources/read targets an unknown uri', function (): void {
    $response = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 'r3',
        'method' => 'resources/read',
        'params' => ['uri' => 'arqel://missing'],
    ]);

    expect($response['error']['code'])->toBe(-32602)
        ->and($response['error']['message'])->toBe('Resource not found: arqel://missing');
});

it('lists and gets prompts', function (): void {
    $this->server->registerPrompt(
        'welcome',
        'Welcome prompt',
        [['name' => 'who']],
        fn (array $args): array => [
            ['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Hi '.($args['who'] ?? 'there')]],
        ],
    );

    $list = $this->server->handleRequest([
        'jsonrpc' => '2.0', 'id' => 'p1', 'method' => 'prompts/list',
    ]);
    expect($list['result']['prompts'])->toHaveCount(1)
        ->and($list['result']['prompts'][0]['name'])->toBe('welcome');

    $get = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 'p2',
        'method' => 'prompts/get',
        'params' => ['name' => 'welcome', 'arguments' => ['who' => 'Arqel']],
    ]);
    expect($get['result']['description'])->toBe('Welcome prompt')
        ->and($get['result']['messages'][0]['content']['text'])->toBe('Hi Arqel');
});

it('returns -32602 when prompts/get targets an unknown name', function (): void {
    $response = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 'p3',
        'method' => 'prompts/get',
        'params' => ['name' => 'missing'],
    ]);

    expect($response['error']['code'])->toBe(-32602)
        ->and($response['error']['message'])->toBe('Prompt not found: missing');
});

it('returns -32601 for unknown methods', function (): void {
    $response = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 99,
        'method' => 'does/not/exist',
    ]);

    expect($response['error']['code'])->toBe(-32601)
        ->and($response['error']['message'])->toBe('Method not found');
});

it('returns -32600 when method is missing', function (): void {
    $response = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 100,
    ]);

    expect($response['error']['code'])->toBe(-32600)
        ->and($response['error']['message'])->toBe('Invalid Request');
});

it('returns -32600 when jsonrpc version is wrong', function (): void {
    $response = $this->server->handleRequest([
        'jsonrpc' => '1.0',
        'id' => 101,
        'method' => 'initialize',
    ]);

    expect($response['error']['code'])->toBe(-32600);
});

it('returns [] for notifications (no id)', function (): void {
    $this->server->registerTool('echo', 'E', ['type' => 'object'], fn (array $args): string => 'ok');

    $response = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'method' => 'tools/call',
        'params' => ['name' => 'echo', 'arguments' => []],
    ]);

    expect($response)->toBe([]);
});

it('returns [] for notifications even when the method errors', function (): void {
    $response = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'method' => 'unknown/method',
    ]);

    expect($response)->toBe([]);
});

it('returns -32600 when method is not a string (e.g. integer or array)', function (): void {
    $r1 = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 200,
        'method' => 42,
    ]);
    $r2 = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 201,
        'method' => ['tools/list'],
    ]);

    expect($r1['error']['code'])->toBe(-32600)
        ->and($r1['error']['message'])->toBe('Invalid Request')
        ->and($r2['error']['code'])->toBe(-32600)
        ->and($r2['error']['message'])->toBe('Invalid Request');
});

it('returns [] for a notification with a malformed envelope (no id, missing method)', function (): void {
    $response = $this->server->handleRequest([
        'jsonrpc' => '2.0',
    ]);

    expect($response)->toBe([]);
});

it('coerces non-array params into an empty array (tools/call with string params surfaces -32602)', function (): void {
    $response = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 202,
        'method' => 'tools/call',
        'params' => 'not-an-array',
    ]);

    expect($response['error']['code'])->toBe(-32602)
        ->and($response['error']['message'])->toBe('Tool not found: ');
});

it('coerces non-array tool arguments into an empty array before calling the handler', function (): void {
    $captured = null;
    $this->server->registerTool(
        'capture',
        'Capture',
        ['type' => 'object'],
        function (array $args) use (&$captured): string {
            $captured = $args;

            return 'ok';
        },
    );

    $response = $this->server->handleRequest([
        'jsonrpc' => '2.0',
        'id' => 203,
        'method' => 'tools/call',
        'params' => ['name' => 'capture', 'arguments' => 'not-an-array'],
    ]);

    expect($response['result']['content'][0]['text'])->toBe('ok')
        ->and($captured)->toBe([]);
});

it('preserves the request id (string and integer) on every response', function (): void {
    $r1 = $this->server->handleRequest([
        'jsonrpc' => '2.0', 'id' => 'abc-123', 'method' => 'initialize',
    ]);
    $r2 = $this->server->handleRequest([
        'jsonrpc' => '2.0', 'id' => 7, 'method' => 'initialize',
    ]);

    expect($r1['jsonrpc'])->toBe('2.0')
        ->and($r1['id'])->toBe('abc-123')
        ->and($r2['id'])->toBe(7);
});

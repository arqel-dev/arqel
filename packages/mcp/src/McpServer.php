<?php

declare(strict_types=1);

namespace Arqel\Mcp;

use Throwable;

/**
 * Model Context Protocol server (JSON-RPC 2.0).
 *
 * Spec compliance: `2024-11-05` (modelcontextprotocol.io).
 *
 * Public API surface:
 *
 *  - Registration: {@see registerTool()}, {@see registerResource()},
 *    {@see registerPrompt()} store metadata + handler in private maps.
 *  - Introspection: {@see getTools()}, {@see getResources()},
 *    {@see getPrompts()} return the metadata maps WITHOUT the
 *    callable handlers (handlers are runtime-only).
 *  - Dispatch: {@see handleRequest()} consumes a decoded JSON-RPC
 *    request array and returns the response envelope (or `[]` for
 *    notifications). It is public so unit tests can drive it without
 *    going through stdio.
 *  - Loop: {@see serve()} is the stdio newline-delimited JSON-RPC
 *    loop wrapper. It is intentionally thin — all dispatch logic
 *    lives in {@see handleRequest()}.
 */
final class McpServer
{
    private const PROTOCOL_VERSION = '2024-11-05';

    private const SERVER_NAME = 'arqel-mcp';

    private const SERVER_VERSION = '0.1.0-alpha';

    /** JSON-RPC 2.0 error codes. */
    private const ERROR_INVALID_REQUEST = -32600;

    private const ERROR_METHOD_NOT_FOUND = -32601;

    private const ERROR_INVALID_PARAMS = -32602;

    private const ERROR_INTERNAL = -32603;

    /**
     * @var array<string, array{description: string, inputSchema: array<string, mixed>, handler: callable}>
     */
    private array $tools = [];

    /**
     * @var array<string, array{name: string, description: string, mimeType?: string, fetcher: callable}>
     */
    private array $resources = [];

    /**
     * @var array<string, array{description: string, arguments: array<int, array<string, mixed>>, generator: callable}>
     */
    private array $prompts = [];

    /**
     * Register an MCP tool.
     *
     * @param array<string, mixed> $inputSchema JSON Schema describing the tool input.
     * @param callable(array<string, mixed>): mixed $handler Invoked with the call arguments.
     */
    public function registerTool(string $name, string $description, array $inputSchema, callable $handler): void
    {
        $this->tools[$name] = [
            'description' => $description,
            'inputSchema' => $inputSchema,
            'handler' => $handler,
        ];
    }

    /**
     * Register an MCP resource keyed by URI.
     *
     * @param callable(string): mixed $fetcher Invoked with the resource URI.
     */
    public function registerResource(string $uri, string $name, string $description, callable $fetcher): void
    {
        $this->resources[$uri] = [
            'name' => $name,
            'description' => $description,
            'fetcher' => $fetcher,
        ];
    }

    /**
     * Register an MCP prompt template.
     *
     * @param array<int, array<string, mixed>> $arguments Argument schema list.
     * @param callable(array<string, mixed>): array<int, array<string, mixed>> $generator Returns the messages array.
     */
    public function registerPrompt(string $name, string $description, array $arguments, callable $generator): void
    {
        $this->prompts[$name] = [
            'description' => $description,
            'arguments' => $arguments,
            'generator' => $generator,
        ];
    }

    /**
     * @return array<string, array{description: string, inputSchema: array<string, mixed>}>
     */
    public function getTools(): array
    {
        $out = [];
        foreach ($this->tools as $name => $tool) {
            $out[$name] = [
                'description' => $tool['description'],
                'inputSchema' => $tool['inputSchema'],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{name: string, description: string}>
     */
    public function getResources(): array
    {
        $out = [];
        foreach ($this->resources as $uri => $res) {
            $out[$uri] = [
                'name' => $res['name'],
                'description' => $res['description'],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{description: string, arguments: array<int, array<string, mixed>>}>
     */
    public function getPrompts(): array
    {
        $out = [];
        foreach ($this->prompts as $name => $prompt) {
            $out[$name] = [
                'description' => $prompt['description'],
                'arguments' => $prompt['arguments'],
            ];
        }

        return $out;
    }

    /**
     * Dispatch a single JSON-RPC 2.0 request.
     *
     * @param array<mixed, mixed> $request Decoded JSON-RPC envelope (untrusted shape).
     *
     * @return array<string, mixed> Empty array for notifications (no response).
     */
    public function handleRequest(array $request): array
    {
        $id = $request['id'] ?? null;
        $isNotification = ! array_key_exists('id', $request);

        // Validate envelope.
        if (($request['jsonrpc'] ?? null) !== '2.0' || ! isset($request['method']) || ! is_string($request['method'])) {
            if ($isNotification) {
                return [];
            }

            return $this->errorResponse($id, self::ERROR_INVALID_REQUEST, 'Invalid Request');
        }

        $method = $request['method'];
        $rawParams = $request['params'] ?? [];
        $params = is_array($rawParams) ? $rawParams : [];

        try {
            $result = $this->dispatch($method, $params);
        } catch (McpDispatchException $e) {
            if ($isNotification) {
                return [];
            }

            return $this->errorResponse($id, $e->getCode(), $e->getMessage());
        } catch (Throwable $e) {
            if ($isNotification) {
                return [];
            }

            return $this->errorResponse(
                $id,
                self::ERROR_INTERNAL,
                'Internal error: '.$e->getMessage(),
                ['exception' => $e->getMessage()],
            );
        }

        if ($isNotification) {
            return [];
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    /**
     * stdio loop: read newline-delimited JSON-RPC requests from STDIN,
     * write responses to STDOUT. One JSON object per line.
     *
     * Not unit-tested directly (stdio-bound) — `handleRequest()` carries
     * the dispatch logic and is exercised by the unit suite.
     */
    public function serve(): void
    {
        // @codeCoverageIgnoreStart
        while (($line = fgets(STDIN)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            /** @var mixed $decoded */
            $decoded = json_decode($line, true);
            if (! is_array($decoded)) {
                $response = $this->errorResponse(null, self::ERROR_INVALID_REQUEST, 'Invalid Request');
                fwrite(STDOUT, json_encode($response, JSON_UNESCAPED_SLASHES).PHP_EOL);

                continue;
            }

            $response = $this->handleRequest($decoded);
            if ($response === []) {
                continue;
            }

            fwrite(STDOUT, json_encode($response, JSON_UNESCAPED_SLASHES).PHP_EOL);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param array<mixed, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @throws McpDispatchException
     */
    private function dispatch(string $method, array $params): array
    {
        return match ($method) {
            'initialize' => [
                'protocolVersion' => self::PROTOCOL_VERSION,
                'capabilities' => [
                    'tools' => (object) [],
                    'resources' => (object) [],
                    'prompts' => (object) [],
                ],
                'serverInfo' => [
                    'name' => self::SERVER_NAME,
                    'version' => self::SERVER_VERSION,
                ],
            ],
            'tools/list' => ['tools' => $this->listTools()],
            'tools/call' => $this->callTool($params),
            'resources/list' => ['resources' => $this->listResources()],
            'resources/read' => $this->readResource($params),
            'prompts/list' => ['prompts' => $this->listPrompts()],
            'prompts/get' => $this->getPrompt($params),
            default => throw new McpDispatchException('Method not found', self::ERROR_METHOD_NOT_FOUND),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listTools(): array
    {
        $out = [];
        foreach ($this->tools as $name => $tool) {
            $out[] = [
                'name' => $name,
                'description' => $tool['description'],
                'inputSchema' => $tool['inputSchema'],
            ];
        }

        return $out;
    }

    /**
     * @param array<mixed, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @throws McpDispatchException
     */
    private function callTool(array $params): array
    {
        $name = $params['name'] ?? null;
        if (! is_string($name) || ! isset($this->tools[$name])) {
            throw new McpDispatchException('Tool not found: '.(is_string($name) ? $name : ''), self::ERROR_INVALID_PARAMS);
        }

        $arguments = $params['arguments'] ?? [];
        if (! is_array($arguments)) {
            $arguments = [];
        }

        $result = ($this->tools[$name]['handler'])($arguments);

        return [
            'content' => [
                ['type' => 'text', 'text' => $this->stringifyResult($result)],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listResources(): array
    {
        $out = [];
        foreach ($this->resources as $uri => $res) {
            $entry = [
                'uri' => $uri,
                'name' => $res['name'],
                'description' => $res['description'],
            ];
            if (isset($res['mimeType'])) {
                $entry['mimeType'] = $res['mimeType'];
            }
            $out[] = $entry;
        }

        return $out;
    }

    /**
     * @param array<mixed, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @throws McpDispatchException
     */
    private function readResource(array $params): array
    {
        $uri = $params['uri'] ?? null;
        if (! is_string($uri) || ! isset($this->resources[$uri])) {
            throw new McpDispatchException('Resource not found: '.(is_string($uri) ? $uri : ''), self::ERROR_INVALID_PARAMS);
        }

        $result = ($this->resources[$uri]['fetcher'])($uri);

        $entry = ['uri' => $uri, 'text' => $this->stringifyResult($result)];
        if (isset($this->resources[$uri]['mimeType'])) {
            $entry['mimeType'] = $this->resources[$uri]['mimeType'];
        }

        return ['contents' => [$entry]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listPrompts(): array
    {
        $out = [];
        foreach ($this->prompts as $name => $prompt) {
            $out[] = [
                'name' => $name,
                'description' => $prompt['description'],
                'arguments' => $prompt['arguments'],
            ];
        }

        return $out;
    }

    /**
     * @param array<mixed, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @throws McpDispatchException
     */
    private function getPrompt(array $params): array
    {
        $name = $params['name'] ?? null;
        if (! is_string($name) || ! isset($this->prompts[$name])) {
            throw new McpDispatchException('Prompt not found: '.(is_string($name) ? $name : ''), self::ERROR_INVALID_PARAMS);
        }

        $arguments = $params['arguments'] ?? [];
        if (! is_array($arguments)) {
            $arguments = [];
        }

        $messages = ($this->prompts[$name]['generator'])($arguments);

        return [
            'description' => $this->prompts[$name]['description'],
            'messages' => $messages,
        ];
    }

    private function stringifyResult(mixed $result): string
    {
        if (is_string($result)) {
            return $result;
        }

        return (string) json_encode($result, JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param array<string, mixed>|null $data
     *
     * @return array<string, mixed>
     */
    private function errorResponse(mixed $id, int $code, string $message, ?array $data = null): array
    {
        $error = ['code' => $code, 'message' => $message];
        if ($data !== null) {
            $error['data'] = $data;
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => $error,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Mcp\Tools;

use Closure;
use InvalidArgumentException;
use Symfony\Component\Process\Process;

/**
 * MCP tool: `run_test`.
 *
 * Wraps Pest/PHPUnit invocation so MCP clients (Claude Desktop, Cursor,
 * agents) can execute the test suite during a TDD workflow with the LLM in
 * the loop.
 *
 * The tool is testable via a closure runner injected through the constructor:
 * `Closure(array<int, string>, int): array{exitCode: int, output: string, errorOutput: string}`.
 * When the runner is `null`, the tool falls back to a default implementation
 * that wraps `Symfony\Component\Process\Process` — exercised only in
 * dogfooding (never in unit tests, to avoid spawning real test subprocesses).
 *
 * Security:
 *
 * The `path` parameter is validated to disallow absolute paths and parent
 * directory traversals (`..`). Any violation throws
 * {@see InvalidArgumentException} before the runner is invoked.
 *
 * Timeout:
 *
 * `timeout` (seconds) is clamped to the inclusive range `[1, 600]` (default
 * 300 — five minutes, the value mandated by the MCP-006 spec).
 */
final class RunTestTool
{
    private const DEFAULT_TIMEOUT = 300;

    private const MIN_TIMEOUT = 1;

    private const MAX_TIMEOUT = 600;

    /**
     * Optional command runner. Receives the cmd array + timeout and returns
     * `{exitCode, output, errorOutput}`. When null, the tool spawns a real
     * {@see Process}.
     *
     * @var (Closure(array<int, string>, int): array{exitCode: int, output: string, errorOutput: string})|null
     */
    private ?Closure $runner;

    /**
     * @param (Closure(array<int, string>, int): array{exitCode: int, output: string, errorOutput: string})|null $runner
     */
    public function __construct(?Closure $runner = null)
    {
        $this->runner = $runner;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array{exitCode: int, output: string, errorOutput: string, success: bool, command: string}
     */
    public function __invoke(array $params): array
    {
        $cmd = ['./vendor/bin/pest'];

        $filter = $params['filter'] ?? null;
        if (is_string($filter) && $filter !== '') {
            $cmd[] = "--filter={$filter}";
        }

        $path = $params['path'] ?? null;
        if (is_string($path) && $path !== '') {
            if (str_starts_with($path, '/') || str_contains($path, '..')) {
                throw new InvalidArgumentException("path must be relative and may not contain '..'");
            }
            $cmd[] = $path;
        }

        if (($params['coverage'] ?? false) === true) {
            $cmd[] = '--coverage';
        }

        $timeout = $this->resolveTimeout($params['timeout'] ?? null);

        $result = $this->runner !== null
            ? ($this->runner)($cmd, $timeout)
            : $this->defaultRunner($cmd, $timeout);

        return [
            'exitCode' => $result['exitCode'],
            'output' => $result['output'],
            'errorOutput' => $result['errorOutput'],
            'success' => $result['exitCode'] === 0,
            'command' => implode(' ', $cmd),
        ];
    }

    /**
     * @return array{name: string, description: string, inputSchema: array{type: string, properties: array<string, mixed>}}
     */
    public function schema(): array
    {
        return [
            'name' => 'run_test',
            'description' => 'Run Pest or PHPUnit tests with optional filter',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'filter' => [
                        'type' => 'string',
                        'description' => 'Test name filter',
                    ],
                    'path' => [
                        'type' => 'string',
                        'description' => 'Path to tests directory',
                    ],
                    'coverage' => [
                        'type' => 'boolean',
                        'description' => 'Generate coverage report',
                        'default' => false,
                    ],
                ],
            ],
        ];
    }

    private function resolveTimeout(mixed $raw): int
    {
        if (! is_int($raw)) {
            return self::DEFAULT_TIMEOUT;
        }

        return max(self::MIN_TIMEOUT, min(self::MAX_TIMEOUT, $raw));
    }

    /**
     * @param array<int, string> $cmd
     *
     * @return array{exitCode: int, output: string, errorOutput: string}
     */
    private function defaultRunner(array $cmd, int $timeout): array
    {
        $process = new Process($cmd);
        $process->setTimeout($timeout);
        $process->run();

        return [
            'exitCode' => $process->getExitCode() ?? 1,
            'output' => $process->getOutput(),
            'errorOutput' => $process->getErrorOutput(),
        ];
    }
}

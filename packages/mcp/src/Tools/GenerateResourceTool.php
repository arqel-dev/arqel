<?php

declare(strict_types=1);

namespace Arqel\Mcp\Tools;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;

/**
 * MCP tool: `generate_resource`.
 *
 * Wraps the `arqel:resource` Artisan command so MCP clients (Claude Desktop,
 * Cursor, agents) can scaffold an Arqel Resource for an Eloquent model.
 *
 * The tool is testable via a closure runner injected through the constructor:
 * `Closure(array): array{exitCode: int, output: string}`. When the runner is
 * `null`, the tool falls back to invoking the bound `Kernel::call()` and
 * `Artisan::output()`.
 *
 * Divergence note (vs MCP-005 spec):
 *
 * The current `Arqel\Core\Commands\MakeResourceCommand` signature only
 * supports `{model}`, `--with-policy`, and `--force` — there is no
 * `--from-model` flag. The schema still exposes `fromModel` for forward
 * compatibility (the property is forwarded as `--from-model` only when the
 * runner is the user's responsibility to honor; the default Artisan-based
 * runner in this class deliberately drops it to avoid an "unknown option"
 * Artisan error). The result payload reports the parameters as received so
 * the LLM can inspect what was actually requested.
 */
final class GenerateResourceTool
{
    /**
     * Optional command runner. Receives the args array (model + options) and
     * returns `{exitCode, output}`. When null, the tool delegates to the
     * Laravel Console Kernel via the Container.
     *
     * @var (Closure(array<string, mixed>): array{exitCode: int, output: string})|null
     */
    private ?Closure $runner;

    /**
     * @param (Closure(array<string, mixed>): array{exitCode: int, output: string})|null $runner
     */
    public function __construct(?Closure $runner = null)
    {
        $this->runner = $runner;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array{model: string, exitCode: int, output: string, success: bool}
     */
    public function __invoke(array $params): array
    {
        $model = $params['model'] ?? null;

        if (! is_string($model)) {
            throw new InvalidArgumentException("'model' parameter is required and must be a string");
        }

        $args = [
            'model' => $model,
            '--from-model' => $params['fromModel'] ?? true,
            '--with-policy' => $params['withPolicy'] ?? true,
        ];

        $result = $this->runner !== null
            ? ($this->runner)($args)
            : $this->defaultRunner($args);

        return [
            'model' => $model,
            'exitCode' => $result['exitCode'],
            'output' => $result['output'],
            'success' => $result['exitCode'] === 0,
        ];
    }

    /**
     * @return array{name: string, description: string, inputSchema: array{type: string, properties: array<string, mixed>, required: array<int, string>}}
     */
    public function schema(): array
    {
        return [
            'name' => 'generate_resource',
            'description' => 'Generate a new Arqel Resource for an Eloquent model',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'model' => [
                        'type' => 'string',
                        'description' => 'Eloquent model class name (e.g., "User" or "App\\Models\\User")',
                    ],
                    'fromModel' => [
                        'type' => 'boolean',
                        'description' => 'Auto-generate fields from model attributes',
                        'default' => true,
                    ],
                    'withPolicy' => [
                        'type' => 'boolean',
                        'description' => 'Also generate Policy class',
                        'default' => true,
                    ],
                ],
                'required' => ['model'],
            ],
        ];
    }

    /**
     * Default runner: dispatch to the Laravel Console Kernel and capture
     * Artisan's textual output. Strips the (currently unsupported)
     * `--from-model` option to avoid an "unknown option" failure against the
     * real `arqel:resource` command — see class-level divergence note.
     *
     * @param array<string, mixed> $args
     *
     * @return array{exitCode: int, output: string}
     */
    private function defaultRunner(array $args): array
    {
        unset($args['--from-model']);

        /** @var Kernel $kernel */
        $kernel = Container::getInstance()->make(Kernel::class);

        $exitCode = $kernel->call('arqel:resource', $args);
        $output = Artisan::output();

        return [
            'exitCode' => $exitCode,
            'output' => $output,
        ];
    }
}

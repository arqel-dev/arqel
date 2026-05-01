<?php

declare(strict_types=1);

namespace Arqel\Ai\Mcp\Tools;

use Arqel\Ai\AiManager;
use InvalidArgumentException;

/**
 * Tool MCP cross-package (AI-013).
 *
 * Recebe uma descrição em linguagem natural ("a blog post resource with
 * title, slug, body and author relation") e devolve o código PHP de um
 * `Arqel\Core\Resources\Resource` pronto a ser revisto e gravado em
 * `app/Arqel/Resources/`. A escrita em disco fica deliberadamente do lado
 * do consumidor (humano ou Claude Code) — esta tool nunca toca o
 * filesystem, evitando overwrites silenciosos a partir de prompts MCP.
 *
 * Defensiva por design: depende apenas de `AiManager` (mesmo pacote). O
 * registo no `Arqel\Mcp\McpServer` é feito via `AiServiceProvider` apenas
 * quando o servidor MCP estiver bound no container.
 */
final readonly class GenerateResourceFromDescriptionTool
{
    public function __construct(
        private AiManager $manager,
    ) {}

    /**
     * @return array{name: string, description: string, inputSchema: array<string, mixed>}
     */
    public function schema(): array
    {
        return [
            'name' => 'generate_resource_from_description',
            'description' => 'Generate a PHP Arqel Resource class from a natural-language description.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'description' => [
                        'type' => 'string',
                        'description' => 'Natural-language description of the resource (entity, fields, relations).',
                    ],
                    'model_name' => [
                        'type' => 'string',
                        'description' => 'PascalCase Eloquent model name (e.g. "BlogPost").',
                    ],
                    'provider' => [
                        'type' => 'string',
                        'description' => 'Optional AI provider name to use (default: configured default).',
                    ],
                ],
                'required' => ['description', 'model_name'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array{resource_code: string, suggested_path: string, model_name: string}
     */
    public function __invoke(array $params): array
    {
        $description = $params['description'] ?? null;
        if (! is_string($description) || trim($description) === '') {
            throw new InvalidArgumentException("'description' parameter is required and must be a non-empty string");
        }

        $modelName = $params['model_name'] ?? null;
        if (! is_string($modelName) || ! preg_match('/^[A-Z][A-Za-z0-9]*$/', $modelName)) {
            throw new InvalidArgumentException("'model_name' parameter must be a PascalCase identifier");
        }

        $provider = isset($params['provider']) && is_string($params['provider']) ? $params['provider'] : null;

        $prompt = <<<PROMPT
            You are generating a PHP Arqel Resource class. Output ONLY the PHP code, no prose.

            Requirements:
            - Declare strict_types=1.
            - Namespace: App\\Arqel\\Resources.
            - Class name: {$modelName}Resource, final, extends Arqel\\Core\\Resources\\Resource.
            - Implement fields() returning an array of field instances from Arqel\\Fields.
            - Optionally implement table() and form() with sensible defaults.
            - Set public static string \$model to App\\Models\\{$modelName}.

            Description: {$description}
            Model name: {$modelName}
            PROMPT;

        $options = [];
        if ($provider !== null) {
            $options['provider'] = $provider;
        }

        $result = $this->manager->complete($prompt, $options);

        return [
            'resource_code' => $result->text,
            'suggested_path' => "app/Arqel/Resources/{$modelName}Resource.php",
            'model_name' => $modelName,
        ];
    }
}

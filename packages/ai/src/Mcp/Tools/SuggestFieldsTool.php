<?php

declare(strict_types=1);

namespace Arqel\Ai\Mcp\Tools;

use Arqel\Ai\AiManager;
use InvalidArgumentException;

/**
 * Tool MCP cross-package (AI-013).
 *
 * Dado o nome de um modelo e o seu mapa `column => DB type`, pede à AI
 * para sugerir o tipo de field Arqel apropriado para cada coluna
 * (`TextField`, `SelectField`, `DateField`, etc.) com a respectiva
 * justificação. O output é texto livre — o consumidor decide se aplica
 * directamente ou se gera código a partir das sugestões.
 */
final readonly class SuggestFieldsTool
{
    public function __construct(
        private AiManager $manager,
    ) {}

    /**
     * @param array<string, mixed> $params
     *
     * @return array{suggestions: string, model_name: string}
     */
    public function __invoke(array $params): array
    {
        $modelName = $params['model_name'] ?? null;
        if (! is_string($modelName) || ! preg_match('/^[A-Z][A-Za-z0-9]*$/', $modelName)) {
            throw new InvalidArgumentException("'model_name' parameter must be a PascalCase identifier");
        }

        $fields = $params['model_fields'] ?? null;
        if (! is_array($fields) || $fields === []) {
            throw new InvalidArgumentException("'model_fields' parameter must be a non-empty array of column => type");
        }

        $serialized = [];
        foreach ($fields as $column => $type) {
            if (! is_string($column) || ! is_string($type)) {
                throw new InvalidArgumentException("'model_fields' entries must be string => string");
            }
            $serialized[] = "  - {$column}: {$type}";
        }

        $columns = implode("\n", $serialized);
        $prompt = "Given a model {$modelName} with columns:\n{$columns}\n\nSuggest appropriate Arqel field types (TextField, SelectField, DateField, BooleanField, NumberField, etc.) for each column with a one-line rationale. Output a markdown list, one entry per column.";

        $options = [];
        if (isset($params['provider']) && is_string($params['provider'])) {
            $options['provider'] = $params['provider'];
        }

        $result = $this->manager->complete($prompt, $options);

        return [
            'suggestions' => $result->text,
            'model_name' => $modelName,
        ];
    }

    /**
     * @return array{name: string, description: string, inputSchema: array<string, mixed>}
     */
    public function schema(): array
    {
        return [
            'name' => 'suggest_fields',
            'description' => 'Suggest appropriate Arqel field types for an Eloquent model given its columns.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'model_name' => [
                        'type' => 'string',
                        'description' => 'PascalCase model name (e.g. "User", "BlogPost").',
                    ],
                    'model_fields' => [
                        'type' => 'object',
                        'description' => 'Map of column name to DB type, e.g. {"email": "string", "created_at": "timestamp"}.',
                        'additionalProperties' => ['type' => 'string'],
                    ],
                    'provider' => [
                        'type' => 'string',
                        'description' => 'Optional AI provider name to use.',
                    ],
                ],
                'required' => ['model_name', 'model_fields'],
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Ai\Mcp\Tools;

use Arqel\Ai\AiManager;
use Illuminate\Container\Container;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Tool MCP cross-package (AI-013).
 *
 * Recebe um slug de Resource registado no `ResourceRegistry` do
 * `arqel/core` e devolve uma análise da AI (resumo, problemas
 * potenciais, sugestões). A introspecção é feita lendo a forma
 * pública do Resource (slug, model, número de fields) — não
 * inspeciona código nem invoca queries.
 *
 * Defensiva: o registry é resolvido pelo FQCN string para evitar um
 * `use` directo (o `arqel/core` está sempre presente como peer-dep,
 * mas o FQCN explicitamente documentado permite que mocks/fakes
 * substituam o binding em testes sem tocar autoload).
 */
final readonly class AnalyzeResourceTool
{
    private const RESOURCE_REGISTRY_CLASS = 'Arqel\\Core\\Resources\\ResourceRegistry';

    public function __construct(
        private AiManager $manager,
    ) {}

    /**
     * @param array<string, mixed> $params
     *
     * @return array{summary: string, resource_slug: string}
     */
    public function __invoke(array $params): array
    {
        $slug = $params['resource_slug'] ?? null;
        if (! is_string($slug) || trim($slug) === '') {
            throw new InvalidArgumentException("'resource_slug' parameter is required and must be a non-empty string");
        }

        $container = Container::getInstance();
        if (! $container->bound(self::RESOURCE_REGISTRY_CLASS)) {
            throw new RuntimeException('ResourceRegistry is not bound; ensure arqel/core is installed and registered');
        }

        $registry = $container->make(self::RESOURCE_REGISTRY_CLASS);
        if (! is_object($registry) || ! method_exists($registry, 'findBySlug')) {
            throw new RuntimeException('Bound ResourceRegistry does not expose findBySlug()');
        }

        /** @var class-string|null $resourceClass */
        $resourceClass = $registry->findBySlug($slug);
        if ($resourceClass === null) {
            throw new InvalidArgumentException("Resource with slug '{$slug}' is not registered");
        }

        $shape = $this->introspect($resourceClass, $slug);

        $prompt = "Analyze this Arqel Resource and provide: (1) summary of its purpose, (2) potential issues (missing validation, N+1, security), (3) suggestions for improvement. Resource shape:\n".json_encode($shape, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $options = [];
        if (isset($params['provider']) && is_string($params['provider'])) {
            $options['provider'] = $params['provider'];
        }

        $result = $this->manager->complete($prompt, $options);

        return [
            'summary' => $result->text,
            'resource_slug' => $slug,
        ];
    }

    /**
     * @return array{name: string, description: string, inputSchema: array<string, mixed>}
     */
    public function schema(): array
    {
        return [
            'name' => 'analyze_resource',
            'description' => 'Analyze an Arqel Resource by slug and return AI-generated summary, issues and suggestions.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'resource_slug' => [
                        'type' => 'string',
                        'description' => 'Slug of the Resource to analyze (e.g. "users", "blog-posts").',
                    ],
                    'provider' => [
                        'type' => 'string',
                        'description' => 'Optional AI provider name to use.',
                    ],
                ],
                'required' => ['resource_slug'],
            ],
        ];
    }

    /**
     * @param class-string $resourceClass
     *
     * @return array<string, mixed>
     */
    private function introspect(string $resourceClass, string $slug): array
    {
        $shape = [
            'class' => $resourceClass,
            'slug' => $slug,
        ];

        try {
            $instance = new $resourceClass;
            if (method_exists($instance, 'fields')) {
                $fields = $instance->fields();
                if (is_array($fields)) {
                    $shape['field_count'] = count($fields);
                    $shape['field_types'] = array_values(array_map(
                        static fn (mixed $f): string => is_object($f) ? $f::class : gettype($f),
                        $fields,
                    ));
                }
            }
            if (method_exists($resourceClass, 'getModel')) {
                $shape['model'] = $resourceClass::getModel();
            }
        } catch (Throwable $e) {
            $shape['introspection_error'] = $e->getMessage();
        }

        return $shape;
    }
}

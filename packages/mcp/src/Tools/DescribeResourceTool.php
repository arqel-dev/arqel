<?php

declare(strict_types=1);

namespace Arqel\Mcp\Tools;

use Arqel\Core\Resources\ResourceRegistry;
use Closure;
use Illuminate\Container\Container;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * MCP tool: `describe_resource`.
 *
 * Returns the static metadata payload for a single Arqel Resource, located
 * by its slug. Lets MCP clients drill into a Resource discovered via
 * `list_resources` without instantiating the class or walking its schema.
 *
 * Defensive: optional metadata accessors (label, pluralLabel, navigation*)
 * are wrapped in `try/catch` and degrade gracefully — the field becomes the
 * exception message (or `null` for nullable accessors). Strict accessors
 * (`class`, `slug`, `model`) propagate any error: a Resource that cannot
 * report those is too broken to describe.
 *
 * Out of scope for MCP-004: form fields, table columns, actions, policy
 * introspection. Those require resource instantiation and arrive in
 * MCP-005+.
 */
final class DescribeResourceTool
{
    /**
     * Optional resolver of resource class-strings by slug. When null, the
     * tool delegates to the bound `ResourceRegistry::findBySlug()`. Tests
     * inject a closure to bypass the registry's `HasResource` type guard
     * (`ResourceRegistry` is `final`, cannot be subclassed).
     *
     * @var (Closure(string): ?class-string)|null
     */
    private ?Closure $resolver;

    /**
     * @param (Closure(string): ?class-string)|null $resolver
     */
    public function __construct(?Closure $resolver = null)
    {
        $this->resolver = $resolver;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array{class: string, model: string, slug: string, label: string|null, pluralLabel: string|null, navigationIcon: string|null, navigationGroup: string|null, navigationSort: int|string|null}
     */
    public function __invoke(array $params): array
    {
        $slug = $params['slug'] ?? null;

        if (! is_string($slug)) {
            throw new InvalidArgumentException("'slug' parameter is required and must be a string");
        }

        $class = $this->resolver !== null
            ? ($this->resolver)($slug)
            : Container::getInstance()->make(ResourceRegistry::class)->findBySlug($slug);

        if ($class === null) {
            throw new RuntimeException("Resource '{$slug}' not found");
        }

        // Strict accessors: a Resource that fails on these is unusable; let it bubble.
        $payload = [
            'class' => $class,
            'model' => $class::getModel(),
            'slug' => $class::getSlug(),
        ];

        // Optional accessors: degrade to the error message (or null) on failure.
        $payload['label'] = $this->safeString(static fn () => $class::getLabel());
        $payload['pluralLabel'] = $this->safeString(static fn () => $class::getPluralLabel());
        $payload['navigationIcon'] = $this->safeNullable(static fn () => $class::getNavigationIcon());
        $payload['navigationGroup'] = $this->safeNullable(static fn () => $class::getNavigationGroup());
        $payload['navigationSort'] = $this->safeNullable(static fn () => $class::getNavigationSort());

        return $payload;
    }

    /**
     * @return array{name: string, description: string, inputSchema: array{type: string, properties: array<string, mixed>, required: array<int, string>}}
     */
    public function schema(): array
    {
        return [
            'name' => 'describe_resource',
            'description' => 'Get detailed information about a specific Arqel Resource',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'slug' => [
                        'type' => 'string',
                        'description' => 'The Resource slug (e.g., "users")',
                    ],
                ],
                'required' => ['slug'],
            ],
        ];
    }

    /**
     * Run a non-nullable accessor; on failure return the exception message.
     *
     * @param Closure(): string $callable
     */
    private function safeString(Closure $callable): string
    {
        try {
            return $callable();
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    /**
     * Run a nullable accessor; on failure return null.
     *
     * @param Closure(): (string|int|null) $callable
     */
    private function safeNullable(Closure $callable): string|int|null
    {
        try {
            return $callable();
        } catch (Throwable) {
            return null;
        }
    }
}

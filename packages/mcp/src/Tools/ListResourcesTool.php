<?php

declare(strict_types=1);

namespace Arqel\Mcp\Tools;

use Arqel\Core\Resources\ResourceRegistry;
use Closure;
use Illuminate\Container\Container;
use Throwable;

/**
 * MCP tool: `list_resources`.
 *
 * Lists every Arqel Resource registered in the application along with its
 * core metadata (model class, slug, labels, navigation group). Lets MCP
 * clients (Claude Desktop, Cursor, agents) introspect the panel structure
 * before issuing CRUD calls.
 *
 * Defensive: each resource's static metadata calls are wrapped in a
 * `try/catch` so a single mis-configured Resource cannot break the tool
 * during application boot.
 */
final class ListResourcesTool
{
    /**
     * Optional resolver of resource class-strings. When null, the tool
     * pulls them from the bound `ResourceRegistry`. Tests inject a closure
     * to avoid the registry's `HasResource` type-guard (it is `final`,
     * cannot be subclassed).
     *
     * @var (Closure(): array<int, class-string>)|null
     */
    private ?Closure $resolver;

    /**
     * @param (Closure(): array<int, class-string>)|null $resolver
     */
    public function __construct(?Closure $resolver = null)
    {
        $this->resolver = $resolver;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array{resources: array<int, array{class: string, model: string, slug: string, label: string, pluralLabel: string, navigationGroup: string|null}>}
     */
    public function __invoke(array $params): array
    {
        $classes = $this->resolver !== null
            ? ($this->resolver)()
            : Container::getInstance()->make(ResourceRegistry::class)->all();

        $resources = [];
        foreach ($classes as $class) {
            try {
                $resources[] = [
                    'class' => $class,
                    'model' => $class::getModel(),
                    'slug' => $class::getSlug(),
                    'label' => $class::getLabel(),
                    'pluralLabel' => $class::getPluralLabel(),
                    'navigationGroup' => $class::getNavigationGroup(),
                ];
            } catch (Throwable) {
                continue;
            }
        }

        return ['resources' => $resources];
    }

    /**
     * @return array{name: string, description: string, inputSchema: array{type: string, properties: array<string, mixed>}}
     */
    public function schema(): array
    {
        return [
            'name' => 'list_resources',
            'description' => 'List all Arqel Resources registered in the application',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [],
            ],
        ];
    }
}

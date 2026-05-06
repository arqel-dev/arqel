<?php

declare(strict_types=1);

namespace Arqel\Core\Console;

use Arqel\Core\Contracts\HasPolicies;
use Arqel\Core\Contracts\HasResource;
use Arqel\Core\Panel\PanelRegistry;
use Arqel\Core\Resources\ResourceRegistry;
use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * `arqel:introspect` — emit a JSON snapshot of registered Panels,
 * Resources, and Fields.
 *
 * The command is **read-only**: it never mutates state, never runs
 * queries against the database, and never throws on partial data. It
 * is the canonical data source consumed by `@arqel-dev/mcp-server`
 * (MCP-002 from PLANNING/13-pos-mvp-mcp-server.md), so the JSON
 * schema is intentionally stable: missing values are emitted as
 * `null` rather than omitted.
 *
 * Output schema:
 *
 *   {
 *     "version": "<arqel-dev/core composer version|null>",
 *     "scope": "all|panels|resources|fields",
 *     "panels": [{ "id", "path", "label" }],
 *     "resources": [{
 *       "class", "model", "label", "pluralLabel", "slug",
 *       "fields": [{ "name", "type" }],
 *       "policies": [class-string, ...]
 *     }],
 *     "fields": [{ "type", "class" }]
 *   }
 *
 * `--scope` filters which top-level sections appear in the output.
 * Sections excluded by the scope are emitted as empty arrays so the
 * shape stays stable for downstream consumers.
 */
final class IntrospectCommand extends Command
{
    private const string SCOPE_ALL = 'all';

    private const string SCOPE_PANELS = 'panels';

    private const string SCOPE_RESOURCES = 'resources';

    private const string SCOPE_FIELDS = 'fields';

    /** @var string */
    protected $signature = 'arqel:introspect
        {--json : Emit JSON output (default)}
        {--scope=all : Section to emit — panels|resources|fields|all}';

    /** @var string */
    protected $description = 'Emit a JSON snapshot of registered Panels, Resources, and Fields.';

    public function handle(): int
    {
        $scope = $this->normaliseScope($this->option('scope'));

        $payload = [
            'version' => $this->resolveCoreVersion(),
            'scope' => $scope,
            'panels' => $this->shouldEmit($scope, self::SCOPE_PANELS)
                ? $this->collectPanels()
                : [],
            'resources' => $this->shouldEmit($scope, self::SCOPE_RESOURCES)
                ? $this->collectResources()
                : [],
            'fields' => $this->shouldEmit($scope, self::SCOPE_FIELDS)
                ? $this->collectFields()
                : [],
        ];

        $this->line((string) json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));

        return self::SUCCESS;
    }

    private function normaliseScope(mixed $raw): string
    {
        if (! is_string($raw)) {
            return self::SCOPE_ALL;
        }

        return match ($raw) {
            self::SCOPE_PANELS,
            self::SCOPE_RESOURCES,
            self::SCOPE_FIELDS,
            self::SCOPE_ALL => $raw,
            default => self::SCOPE_ALL,
        };
    }

    private function shouldEmit(string $scope, string $section): bool
    {
        return $scope === self::SCOPE_ALL || $scope === $section;
    }

    private function resolveCoreVersion(): ?string
    {
        try {
            if (
                class_exists(InstalledVersions::class)
                && InstalledVersions::isInstalled('arqel-dev/core')
            ) {
                return InstalledVersions::getVersion('arqel-dev/core');
            }
        } catch (Throwable) {
            // Fall through to null.
        }

        return null;
    }

    /**
     * @return list<array{id: string, path: string, label: string}>
     */
    private function collectPanels(): array
    {
        try {
            $registry = $this->getLaravel()->make(PanelRegistry::class);
        } catch (BindingResolutionException) {
            return [];
        }

        $panels = [];
        foreach ($registry->all() as $panel) {
            $brand = $panel->getBrand();
            $label = $brand['name'] !== ''
                ? $brand['name']
                : $panel->id;

            $panels[] = [
                'id' => $panel->id,
                'path' => $panel->getPath(),
                'label' => $label,
            ];
        }

        return $panels;
    }

    /**
     * @return list<array{
     *   class: class-string<HasResource>,
     *   model: class-string|null,
     *   label: string|null,
     *   pluralLabel: string|null,
     *   slug: string|null,
     *   fields: list<array{name: string, type: string}>,
     *   policies: list<class-string>
     * }>
     */
    private function collectResources(): array
    {
        try {
            $registry = $this->getLaravel()->make(ResourceRegistry::class);
        } catch (BindingResolutionException) {
            return [];
        }

        $resources = [];
        foreach ($registry->all() as $resourceClass) {
            $resources[] = $this->describeResource($resourceClass);
        }

        return $resources;
    }

    /**
     * @param class-string<HasResource> $resourceClass
     *
     * @return array{
     *   class: class-string<HasResource>,
     *   model: class-string|null,
     *   label: string|null,
     *   pluralLabel: string|null,
     *   slug: string|null,
     *   fields: list<array{name: string, type: string}>,
     *   policies: list<class-string>
     * }
     */
    private function describeResource(string $resourceClass): array
    {
        $model = null;
        try {
            $model = $resourceClass::getModel();
        } catch (Throwable) {
            $model = null;
        }

        $label = $this->safeStringCall(static fn (): string => $resourceClass::getLabel());
        $pluralLabel = $this->safeStringCall(static fn (): string => $resourceClass::getPluralLabel());
        $slug = $this->safeStringCall(static fn (): string => $resourceClass::getSlug());

        return [
            'class' => $resourceClass,
            'model' => $model,
            'label' => $label,
            'pluralLabel' => $pluralLabel,
            'slug' => $slug,
            'fields' => $this->describeResourceFields($resourceClass),
            'policies' => $this->describeResourcePolicies($resourceClass, $model),
        ];
    }

    /**
     * @param class-string<HasResource> $resourceClass
     *
     * @return list<array{name: string, type: string}>
     */
    private function describeResourceFields(string $resourceClass): array
    {
        /** @var object $instance */
        $instance = new $resourceClass;

        if (! method_exists($instance, 'fields')) {
            return [];
        }

        try {
            /** @var mixed $fields */
            $fields = $instance->fields();
        } catch (Throwable) {
            return [];
        }

        if (! is_array($fields)) {
            return [];
        }

        $serialised = [];
        foreach ($fields as $field) {
            if (! is_object($field)) {
                continue;
            }
            if (! method_exists($field, 'getName') || ! method_exists($field, 'getType')) {
                continue;
            }

            try {
                $name = $field->getName();
                $type = $field->getType();
            } catch (Throwable) {
                continue;
            }

            if (! is_string($name) || ! is_string($type)) {
                continue;
            }

            $serialised[] = ['name' => $name, 'type' => $type];
        }

        return $serialised;
    }

    /**
     * @param class-string<HasResource> $resourceClass
     * @param class-string|null $model
     *
     * @return list<class-string>
     */
    private function describeResourcePolicies(string $resourceClass, ?string $model): array
    {
        $policies = [];

        if (is_subclass_of($resourceClass, HasPolicies::class)) {
            try {
                $declared = $resourceClass::getPolicy();
                if (is_string($declared) && class_exists($declared)) {
                    $policies[] = $declared;
                }
            } catch (Throwable) {
                // Ignore — fall through to Laravel auto-discovery.
            }
        }

        if ($model !== null && class_exists($model)) {
            try {
                $resolved = Gate::getPolicyFor($model);
            } catch (Throwable) {
                $resolved = null;
            }

            if (is_object($resolved)) {
                $resolvedClass = $resolved::class;
                if (! in_array($resolvedClass, $policies, true)) {
                    $policies[] = $resolvedClass;
                }
            }
        }

        return $policies;
    }

    /**
     * @return list<array{type: string, class: class-string}>
     */
    private function collectFields(): array
    {
        $factoryClass = 'Arqel\\Fields\\FieldFactory';

        // `arqel-dev/fields` is an optional companion package. Skip
        // when it is not installed so the command stays usable in
        // apps that only depend on `arqel-dev/core`.
        if (! class_exists($factoryClass)) {
            return [];
        }

        try {
            /** @var mixed $registered */
            $registered = $factoryClass::getRegisteredTypes();
        } catch (Throwable) {
            return [];
        }

        if (! is_array($registered)) {
            return [];
        }

        $fields = [];
        foreach ($registered as $type => $class) {
            if (! is_string($type) || ! is_string($class)) {
                continue;
            }
            /** @var class-string $class */
            $fields[] = ['type' => $type, 'class' => $class];
        }

        return $fields;
    }

    /**
     * @param callable(): string $callback
     */
    private function safeStringCall(callable $callback): ?string
    {
        try {
            $value = $callback();

            return $value;
        } catch (Throwable) {
            return null;
        }
    }
}

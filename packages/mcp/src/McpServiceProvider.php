<?php

declare(strict_types=1);

namespace Arqel\Mcp;

use Arqel\Mcp\Resources\SkillResource;
use Arqel\Mcp\Tools\DescribeResourceTool;
use Arqel\Mcp\Tools\GenerateResourceTool;
use Arqel\Mcp\Tools\ListResourcesTool;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Auto-discovered provider for `arqel/mcp`.
 *
 * Binds `McpServer` as a singleton and auto-registers built-in tools
 * (e.g. `list_resources`, `describe_resource`) once the application has
 * booted.
 */
final class McpServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('arqel-mcp');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(McpServer::class);
        $this->app->singleton(SkillResource::class);
    }

    public function packageBooted(): void
    {
        /** @var McpServer $server */
        $server = $this->app->make(McpServer::class);

        $listResources = new ListResourcesTool;
        $listSchema = $listResources->schema();
        $server->registerTool(
            $listSchema['name'],
            $listSchema['description'],
            $listSchema['inputSchema'],
            static fn (array $params): array => $listResources($params),
        );

        $describeResource = new DescribeResourceTool;
        $describeSchema = $describeResource->schema();
        $server->registerTool(
            $describeSchema['name'],
            $describeSchema['description'],
            $describeSchema['inputSchema'],
            static fn (array $params): array => $describeResource($params),
        );

        $generateResource = new GenerateResourceTool;
        $generateSchema = $generateResource->schema();
        $server->registerTool(
            $generateSchema['name'],
            $generateSchema['description'],
            $generateSchema['inputSchema'],
            static fn (array $params): array => $generateResource($params),
        );

        // Register one McpServer resource per discovered SKILL.md. The
        // discovery is pre-flattened: `SkillResource::list()` is called
        // ONCE here and each entry is wired into McpServer's standard
        // `resources/list` + `resources/read` dispatch. If new SKILL.md
        // files appear at runtime, restart the MCP server.
        /** @var SkillResource $skillResource */
        $skillResource = $this->app->make(SkillResource::class);
        foreach ($skillResource->list() as $entry) {
            $uri = $entry['uri'];
            $server->registerResource(
                $uri,
                $entry['name'],
                $entry['description'],
                static function (string $resourceUri) use ($skillResource): array {
                    return $skillResource->read($resourceUri);
                },
            );
        }
    }
}

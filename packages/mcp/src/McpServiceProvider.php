<?php

declare(strict_types=1);

namespace Arqel\Mcp;

use Arqel\Mcp\Tools\DescribeResourceTool;
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
    }
}

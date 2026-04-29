<?php

declare(strict_types=1);

namespace Arqel\Mcp;

use Arqel\Mcp\Tools\ListResourcesTool;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Auto-discovered provider for `arqel/mcp`.
 *
 * Binds `McpServer` as a singleton and auto-registers built-in tools
 * (e.g. `list_resources`) once the application has booted.
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
        $schema = $listResources->schema();
        $server->registerTool(
            $schema['name'],
            $schema['description'],
            $schema['inputSchema'],
            static fn (array $params): array => $listResources($params),
        );
    }
}

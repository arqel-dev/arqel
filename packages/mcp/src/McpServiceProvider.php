<?php

declare(strict_types=1);

namespace Arqel\Mcp;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Auto-discovered provider for `arqel/mcp`.
 *
 * Phase 2 scaffold (MCP-001). Binds `McpServer` as a singleton so
 * downstream packages can type-hint a stable instance even before
 * the real JSON-RPC handler lands in MCP-002. The current
 * `McpServer` is a stub — see its docblock for the migration path.
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
}

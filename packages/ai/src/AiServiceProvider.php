<?php

declare(strict_types=1);

namespace Arqel\Ai;

use Arqel\Ai\Contracts\AiProvider;
use Arqel\Ai\Mcp\Tools\AnalyzeResourceTool;
use Arqel\Ai\Mcp\Tools\GenerateResourceFromDescriptionTool;
use Arqel\Ai\Mcp\Tools\SuggestFieldsTool;
use Illuminate\Contracts\Foundation\Application;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Throwable;

/**
 * Auto-discovered provider for `arqel/ai`.
 *
 * Bootstraps the `AiManager` singleton, the `CostTracker` and `AiCache`
 * services, and publishes both the config file and the `ai_usage`
 * migration. Provider concretes (Claude/OpenAi/Ollama) are resolved
 * lazily from `arqel-ai.providers` — each entry's `driver` key holds
 * the FQCN, and any extra keys are passed to the constructor.
 */
final class AiServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('arqel-ai')
            ->hasConfigFile('arqel-ai')
            ->hasMigration('create_ai_usage_table')
            ->hasRoute('web');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(AiCache::class);
        $this->app->singleton(CostTracker::class);

        $this->app->singleton(AiManager::class, function (Application $app): AiManager {
            /** @var array<string, array<string, mixed>> $providersConfig */
            $providersConfig = (array) config('arqel-ai.providers', []);

            $resolved = [];
            foreach ($providersConfig as $name => $entry) {
                if (! is_string($name) || ! is_array($entry)) {
                    continue;
                }

                $driver = $entry['driver'] ?? null;
                if (! is_string($driver) || ! class_exists($driver)) {
                    continue;
                }

                $args = $entry;
                unset($args['driver']);

                try {
                    /** @var AiProvider $instance */
                    $instance = $app->make($driver, $args);
                } catch (Throwable) {
                    continue;
                }

                $resolved[$name] = $instance;
            }

            return new AiManager(
                providers: $resolved,
                costTracker: $app->make(CostTracker::class),
                cache: $app->make(AiCache::class),
            );
        });
    }

    /**
     * Cross-package hook: when `arqel/mcp` está presente e o `McpServer`
     * está bound no container, regista as 3 tools AI-MCP (AI-013).
     *
     * Defensive: nunca adicionamos `arqel/mcp` como hard-dep — o registo
     * é silenciosamente ignorado se o pacote não estiver instalado ou se
     * algo na introspecção falhar. Idempotente: chamar múltiplas vezes
     * sobrescreve com o mesmo nome.
     */
    public function packageBooted(): void
    {
        $mcpServerClass = 'Arqel\\Mcp\\McpServer';

        if (! class_exists($mcpServerClass) || ! $this->app->bound($mcpServerClass)) {
            return;
        }

        try {
            $server = $this->app->make($mcpServerClass);

            if (! is_object($server) || ! method_exists($server, 'registerTool')) {
                return;
            }

            $manager = $this->app->make(AiManager::class);

            foreach ([
                new GenerateResourceFromDescriptionTool($manager),
                new AnalyzeResourceTool($manager),
                new SuggestFieldsTool($manager),
            ] as $tool) {
                $schema = $tool->schema();
                $server->registerTool(
                    $schema['name'],
                    $schema['description'],
                    $schema['inputSchema'],
                    $tool,
                );
            }
        } catch (Throwable $e) {
            if (function_exists('logger')) {
                logger()->warning('arqel/ai: failed to register MCP tools: '.$e->getMessage());
            }
        }
    }
}

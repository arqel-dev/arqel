<?php

declare(strict_types=1);

namespace Arqel\Mcp\Resources;

use Closure;
use Illuminate\Container\Container;
use RuntimeException;
use Throwable;

/**
 * MCP resource: `arqel-skill://<package>`.
 *
 * Exposes each package's `SKILL.md` as an MCP Resource so MCP clients can
 * fetch the canonical AI-agent context for a given Arqel package.
 *
 * URI scheme: `arqel-skill://<package>` where `<package>` matches
 * `[a-z0-9-]+` and refers to a directory under `packages/` in the monorepo
 * (e.g. `arqel-skill://core` → `packages/core/SKILL.md`).
 *
 * Both the package discovery and the file-content read are pluggable via
 * Closures injected through the constructor — this keeps the class
 * `final` while allowing tests to swap the filesystem entirely.
 *
 * Auto-registration semantics: `McpServiceProvider` calls
 * {@see list()} once at boot and registers ONE McpServer resource per
 * discovered package via `registerResource()`. The set is therefore
 * effectively static for the lifetime of the server process — if a new
 * SKILL.md is added at runtime, the server must be restarted to pick it
 * up.
 *
 * Path resolution: the default resolver uses `base_path('packages')`
 * (the host application's path). When the package is consumed from the
 * monorepo via path-repositories, the running app under Testbench has
 * its own `base_path` — so we fall back to a relative
 * `__DIR__/../../../../` walk to the monorepo root in that case.
 */
final class SkillResource
{
    /** @var (Closure(): array<int, string>)|null */
    private ?Closure $packagesResolver;

    /** @var (Closure(string): string)|null */
    private ?Closure $contentReader;

    /**
     * @param (Closure(): array<int, string>)|null $packagesResolver Returns list of package names (no `arqel/` prefix).
     * @param (Closure(string): string)|null $contentReader Returns SKILL.md contents for a given package, or throws.
     */
    public function __construct(?Closure $packagesResolver = null, ?Closure $contentReader = null)
    {
        $this->packagesResolver = $packagesResolver;
        $this->contentReader = $contentReader;
    }

    /**
     * @return array<int, array{uri: string, name: string, description: string, mimeType: string}>
     */
    public function list(): array
    {
        $packages = $this->packagesResolver !== null
            ? ($this->packagesResolver)()
            : $this->discoverPackages();

        $entries = [];
        foreach ($packages as $package) {
            $entries[] = [
                'uri' => "arqel-skill://{$package}",
                'name' => "SKILL.md for arqel/{$package}",
                'description' => "AI agent context for the {$package} package",
                'mimeType' => 'text/markdown',
            ];
        }

        return $entries;
    }

    /**
     * @return array{contents: array<int, array{uri: string, mimeType: string, text: string}>}
     */
    public function read(string $uri): array
    {
        if (preg_match('#^arqel-skill://([a-z0-9-]+)$#', $uri, $matches) !== 1) {
            throw new RuntimeException("Invalid URI: {$uri}");
        }

        $package = $matches[1];

        try {
            $contents = $this->contentReader !== null
                ? ($this->contentReader)($package)
                : $this->defaultRead($package);
        } catch (Throwable $e) {
            throw new RuntimeException("SKILL.md not found for arqel/{$package}", 0, $e);
        }

        return [
            'contents' => [[
                'uri' => $uri,
                'mimeType' => 'text/markdown',
                'text' => $contents,
            ]],
        ];
    }

    /**
     * Default package discovery: scan the monorepo's `packages/` dir for
     * subdirectories that contain a `SKILL.md`.
     *
     * @return array<int, string>
     */
    private function discoverPackages(): array
    {
        $root = $this->resolveMonorepoRoot();
        if ($root === null) {
            return [];
        }

        $packages = [];
        $matches = glob($root.'/packages/*/SKILL.md');
        if ($matches === false) {
            return [];
        }

        foreach ($matches as $skillPath) {
            $packages[] = basename(dirname($skillPath));
        }

        sort($packages);

        return $packages;
    }

    private function defaultRead(string $package): string
    {
        $root = $this->resolveMonorepoRoot();
        if ($root === null) {
            throw new RuntimeException('Monorepo root not resolvable');
        }

        $path = $root.'/packages/'.$package.'/SKILL.md';
        $real = realpath($path);
        if ($real === false) {
            throw new RuntimeException("File not found: {$path}");
        }

        $contents = file_get_contents($real);
        if ($contents === false) {
            throw new RuntimeException("Failed to read: {$real}");
        }

        return $contents;
    }

    /**
     * Resolve the monorepo root: try `base_path('packages')` first,
     * fall back to the relative path from this file's location.
     */
    private function resolveMonorepoRoot(): ?string
    {
        try {
            /** @var string $base */
            $base = Container::getInstance()->make('path.base');
            if (is_dir($base.'/packages')) {
                return $base;
            }
        } catch (Throwable) {
            // Container not bootstrapped; fall through to relative path.
        }

        $fallback = realpath(__DIR__.'/../../../..');
        if ($fallback !== false && is_dir($fallback.'/packages')) {
            return $fallback;
        }

        return null;
    }
}

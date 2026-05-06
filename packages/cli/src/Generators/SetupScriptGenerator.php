<?php

declare(strict_types=1);

namespace Arqel\Cli\Generators;

use InvalidArgumentException;

/**
 * Renders the bash / PowerShell setup script that the user reviews and runs.
 *
 * Generation is deterministic and side-effect free so that the resulting
 * scripts can be asserted against in tests without spawning real processes.
 */
final readonly class SetupScriptGenerator
{
    public const array STARTERS = ['react', 'vue', 'livewire', 'none'];

    /**
     * Legacy starter names kept for backwards-compat with older docs / CI configs.
     * Maps to the closest current Laravel installer flag. `breeze` and `jetstream`
     * were removed from `laravel new` in 2025 — both implied an Inertia + React
     * setup with auth scaffolding, which is what `--react` provides today.
     */
    public const array STARTER_ALIASES = [
        'breeze' => 'react',
        'jetstream' => 'react',
    ];

    public const array TENANCIES = ['none', 'simple', 'stancl', 'spatie'];

    /**
     * Packages required when expanding `arqel-dev/framework` against a local monorepo.
     * Mirrors the meta-package contents — keep in sync when adding new packages.
     *
     * @var list<string>
     */
    public const array MONOREPO_PACKAGES = [
        'arqel-dev/core',
        'arqel-dev/fields',
        'arqel-dev/table',
        'arqel-dev/form',
        'arqel-dev/actions',
        'arqel-dev/auth',
        'arqel-dev/nav',
        'arqel-dev/widgets',
    ];

    public string $starter;

    public ?string $monorepoPath;

    public function __construct(
        public string $appName,
        string $starter = 'react',
        public string $tenancy = 'none',
        public ?string $firstResource = null,
        public bool $darkMode = true,
        public bool $mcpIntegration = false,
        ?string $monorepoPath = null,
    ) {
        if ($appName === '' || preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $appName) !== 1) {
            throw new InvalidArgumentException(
                "Invalid app name '{$appName}'. Must start with a letter and contain only letters, numbers, dashes or underscores.",
            );
        }

        $resolved = self::STARTER_ALIASES[$starter] ?? $starter;

        if (! in_array($resolved, self::STARTERS, true)) {
            throw new InvalidArgumentException("Unknown starter '{$starter}'.");
        }

        $this->starter = $resolved;

        if (! in_array($tenancy, self::TENANCIES, true)) {
            throw new InvalidArgumentException("Unknown tenancy '{$tenancy}'.");
        }

        if ($monorepoPath !== null) {
            if (! is_dir($monorepoPath) || ! is_file($monorepoPath.'/packages/core/composer.json')) {
                throw new InvalidArgumentException(
                    "Invalid monorepoPath '{$monorepoPath}'. Expected a directory containing packages/core/composer.json.",
                );
            }
            $this->monorepoPath = rtrim($monorepoPath, '/');
        } else {
            $this->monorepoPath = null;
        }
    }

    public function forBash(): string
    {
        $lines = [
            '#!/usr/bin/env bash',
            '#',
            "# Arqel setup script for `{$this->appName}`.",
            '# Review every command before running.',
            '',
            'set -euo pipefail',
            '',
            'echo "==> Creating Laravel application: '.$this->appName.'"',
            $this->laravelNewCommand(),
            "cd {$this->appName}",
            '',
        ];

        if ($this->monorepoPath !== null) {
            $packagesPath = $this->monorepoPath.'/packages/*';
            $lines[] = "echo \"==> Wiring local Arqel monorepo path repository ({$this->monorepoPath})\"";
            $lines[] = "composer config repositories.arqel '{\"type\":\"path\",\"url\":\"{$packagesPath}\",\"options\":{\"symlink\":true}}'";
            $lines[] = 'composer config minimum-stability dev';
            $lines[] = 'composer config prefer-stable true';
            $lines[] = '';
            $lines[] = 'echo "==> Installing Arqel packages from local monorepo"';
            $monorepoSpec = implode(' ', array_map(static fn (string $p): string => "{$p}:dev-main", self::MONOREPO_PACKAGES));
            $lines[] = "composer require {$monorepoSpec} -W";
        } else {
            $lines[] = 'echo "==> Installing arqel-dev/framework"';
            $lines[] = 'composer require arqel-dev/framework';
        }

        foreach ($this->extraComposerRequires() as $pkg) {
            $suffix = $this->monorepoPath !== null && str_starts_with($pkg, 'arqel-dev/') ? ':dev-main' : '';
            $lines[] = "composer require {$pkg}{$suffix}";
        }

        $lines[] = '';
        $lines[] = 'echo "==> Running arqel:install"';
        $lines[] = 'php artisan arqel:install';

        if ($this->firstResource !== null) {
            $lines[] = "php artisan arqel:resource {$this->firstResource}";
        }

        $lines[] = '';
        $lines[] = 'echo "==> Installing JS deps"';
        $lines[] = 'pnpm install';

        if ($this->darkMode) {
            $lines[] = "echo '==> Dark mode preset enabled (configure via config/arqel.php)'";
        }

        if ($this->mcpIntegration) {
            $lines[] = $this->monorepoPath !== null
                ? 'composer require arqel-dev/mcp:dev-main'
                : 'composer require arqel-dev/mcp';
            $lines[] = 'php artisan arqel:mcp:install';
        }

        $lines[] = '';
        $lines[] = "echo \"==> Done. Next: cd {$this->appName} && php artisan serve\"";
        $lines[] = '';

        return implode("\n", $lines);
    }

    public function forPowershell(): string
    {
        $lines = [
            '# Arqel setup script for '.$this->appName.'.',
            '# Review every command before running.',
            '',
            '$ErrorActionPreference = "Stop"',
            '',
            'Write-Host "==> Creating Laravel application: '.$this->appName.'"',
            $this->laravelNewCommand(),
            "Set-Location {$this->appName}",
            '',
        ];

        if ($this->monorepoPath !== null) {
            $packagesPath = str_replace('/', '\\', $this->monorepoPath).'\\packages\\*';
            $lines[] = "Write-Host \"==> Wiring local Arqel monorepo path repository ({$this->monorepoPath})\"";
            $lines[] = "composer config repositories.arqel '{\\\"type\\\":\\\"path\\\",\\\"url\\\":\\\"{$packagesPath}\\\",\\\"options\\\":{\\\"symlink\\\":true}}'";
            $lines[] = 'composer config minimum-stability dev';
            $lines[] = 'composer config prefer-stable true';
            $lines[] = '';
            $lines[] = 'Write-Host "==> Installing Arqel packages from local monorepo"';
            $monorepoSpec = implode(' ', array_map(static fn (string $p): string => "{$p}:dev-main", self::MONOREPO_PACKAGES));
            $lines[] = "composer require {$monorepoSpec} -W";
        } else {
            $lines[] = 'Write-Host "==> Installing arqel-dev/framework"';
            $lines[] = 'composer require arqel-dev/framework';
        }

        foreach ($this->extraComposerRequires() as $pkg) {
            $suffix = $this->monorepoPath !== null && str_starts_with($pkg, 'arqel-dev/') ? ':dev-main' : '';
            $lines[] = "composer require {$pkg}{$suffix}";
        }

        $lines[] = '';
        $lines[] = 'Write-Host "==> Running arqel:install"';
        $lines[] = 'php artisan arqel:install';

        if ($this->firstResource !== null) {
            $lines[] = "php artisan arqel:resource {$this->firstResource}";
        }

        $lines[] = '';
        $lines[] = 'Write-Host "==> Installing JS deps"';
        $lines[] = 'pnpm install';

        if ($this->darkMode) {
            $lines[] = 'Write-Host "==> Dark mode preset enabled (configure via config/arqel.php)"';
        }

        if ($this->mcpIntegration) {
            $lines[] = $this->monorepoPath !== null
                ? 'composer require arqel-dev/mcp:dev-main'
                : 'composer require arqel-dev/mcp';
            $lines[] = 'php artisan arqel:mcp:install';
        }

        $lines[] = '';
        $lines[] = "Write-Host \"==> Done. Next: Set-Location {$this->appName}; php artisan serve\"";
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function laravelNewCommand(): string
    {
        return match ($this->starter) {
            'react' => "laravel new {$this->appName} --react --pest --pnpm --no-interaction",
            'vue' => "laravel new {$this->appName} --vue --pest --pnpm --no-interaction",
            'livewire' => "laravel new {$this->appName} --livewire --pest --pnpm --no-interaction",
            default => "laravel new {$this->appName} --pest --pnpm --no-interaction",
        };
    }

    /**
     * @return list<string>
     */
    private function extraComposerRequires(): array
    {
        return match ($this->tenancy) {
            'stancl' => ['stancl/tenancy'],
            'spatie' => ['spatie/laravel-multitenancy'],
            'simple' => ['arqel-dev/tenant'],
            default => [],
        };
    }
}

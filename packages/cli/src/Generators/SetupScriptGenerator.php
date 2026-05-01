<?php

declare(strict_types=1);

namespace Arqel\Cli\Generators;

/**
 * Renders the bash / PowerShell setup script that the user reviews and runs.
 *
 * Generation is deterministic and side-effect free so that the resulting
 * scripts can be asserted against in tests without spawning real processes.
 */
final readonly class SetupScriptGenerator
{
    public const array STARTERS = ['breeze', 'jetstream', 'none'];

    public const array TENANCIES = ['none', 'simple', 'stancl', 'spatie'];

    public function __construct(
        public string $appName,
        public string $starter = 'breeze',
        public string $tenancy = 'none',
        public ?string $firstResource = null,
        public bool $darkMode = true,
        public bool $mcpIntegration = false,
    ) {
        if ($appName === '' || preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $appName) !== 1) {
            throw new \InvalidArgumentException(
                "Invalid app name '{$appName}'. Must start with a letter and contain only letters, numbers, dashes or underscores.",
            );
        }

        if (! in_array($starter, self::STARTERS, true)) {
            throw new \InvalidArgumentException("Unknown starter '{$starter}'.");
        }

        if (! in_array($tenancy, self::TENANCIES, true)) {
            throw new \InvalidArgumentException("Unknown tenancy '{$tenancy}'.");
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
            'echo "==> Installing arqel/arqel"',
            'composer require arqel/arqel',
        ];

        foreach ($this->extraComposerRequires() as $pkg) {
            $lines[] = "composer require {$pkg}";
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
            $lines[] = 'composer require arqel/mcp';
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
            'Write-Host "==> Installing arqel/arqel"',
            'composer require arqel/arqel',
        ];

        foreach ($this->extraComposerRequires() as $pkg) {
            $lines[] = "composer require {$pkg}";
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
            $lines[] = 'composer require arqel/mcp';
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
            'jetstream' => "laravel new {$this->appName} --jet",
            'breeze' => "laravel new {$this->appName} --breeze",
            default => "laravel new {$this->appName}",
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
            'simple' => ['arqel/tenant'],
            default => [],
        };
    }
}

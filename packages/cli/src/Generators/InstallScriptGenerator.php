<?php

declare(strict_types=1);

namespace Arqel\Cli\Generators;

use Arqel\Cli\Models\PluginMetadata;

/**
 * Renders bash / PowerShell scripts that install a marketplace plugin.
 *
 * Generation is deterministic and side-effect-free so resulting scripts
 * can be asserted against in tests without spawning real processes.
 */
final readonly class InstallScriptGenerator
{
    public function __construct(
        public PluginMetadata $plugin,
        public bool $runArtisanInstaller = true,
        public bool $runArtisanMigrate = false,
    ) {}

    public function forBash(): string
    {
        $name = $this->plugin->name;
        $lines = [
            '#!/usr/bin/env bash',
            '#',
            "# Arqel install script for plugin `{$name}`.",
            '# Review every command before running.',
            '',
            'set -euo pipefail',
            '',
            "echo \"==> Installing composer package: {$this->plugin->composerPackage}\"",
            "composer require {$this->plugin->composerPackage}",
        ];

        if ($this->plugin->npmPackage !== null) {
            $lines[] = '';
            $lines[] = "echo \"==> Installing npm package: {$this->plugin->npmPackage}\"";
            $lines[] = "npm install {$this->plugin->npmPackage}";
        }

        if ($this->runArtisanInstaller && $this->plugin->installerCommand !== null) {
            $lines[] = '';
            $lines[] = "echo \"==> Running plugin installer: php artisan {$this->plugin->installerCommand}\"";
            $lines[] = "php artisan {$this->plugin->installerCommand}";
        }

        if ($this->runArtisanMigrate) {
            $lines[] = '';
            $lines[] = 'echo "==> Running database migrations"';
            $lines[] = 'php artisan migrate';
        }

        $lines[] = '';
        $lines[] = "echo \"==> Done. Plugin {$name} installed.\"";
        $lines[] = '';

        return implode("\n", $lines);
    }

    public function forPowershell(): string
    {
        $name = $this->plugin->name;
        $lines = [
            "# Arqel install script for plugin {$name}.",
            '# Review every command before running.',
            '',
            '$ErrorActionPreference = "Stop"',
            '',
            "Write-Host \"==> Installing composer package: {$this->plugin->composerPackage}\"",
            "composer require {$this->plugin->composerPackage}",
        ];

        if ($this->plugin->npmPackage !== null) {
            $lines[] = '';
            $lines[] = "Write-Host \"==> Installing npm package: {$this->plugin->npmPackage}\"";
            $lines[] = "npm install {$this->plugin->npmPackage}";
        }

        if ($this->runArtisanInstaller && $this->plugin->installerCommand !== null) {
            $lines[] = '';
            $lines[] = "Write-Host \"==> Running plugin installer: php artisan {$this->plugin->installerCommand}\"";
            $lines[] = "php artisan {$this->plugin->installerCommand}";
        }

        if ($this->runArtisanMigrate) {
            $lines[] = '';
            $lines[] = 'Write-Host "==> Running database migrations"';
            $lines[] = 'php artisan migrate';
        }

        $lines[] = '';
        $lines[] = "Write-Host \"==> Done. Plugin {$name} installed.\"";
        $lines[] = '';

        return implode("\n", $lines);
    }
}

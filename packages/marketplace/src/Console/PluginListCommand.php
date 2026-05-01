<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Console;

use Arqel\Marketplace\Services\PluginConventionValidator;
use Composer\InstalledVersions;
use Illuminate\Console\Command;

/**
 * Lista plugins Arqel instalados no projeto host (MKTPLC-003).
 *
 * Usa `Composer\InstalledVersions::getInstalledPackagesByType('arqel-plugin')`
 * para descobrir plugins. Quando rodado com `--validate`, também executa o
 * `PluginConventionValidator` em cada `composer.json` encontrado e mostra os
 * checks com seu status agregado.
 */
final class PluginListCommand extends Command
{
    /** @var string */
    protected $signature = 'arqel:plugin:list {--validate : Run convention validator on each installed plugin}';

    /** @var string */
    protected $description = 'List installed Arqel plugins (composer type=arqel-plugin) and optionally validate metadata.';

    public function handle(PluginConventionValidator $validator): int
    {
        $packages = $this->discoverPlugins();

        if ($packages === []) {
            $this->info('No Arqel plugins installed.');

            return self::SUCCESS;
        }

        $rows = [];
        $detailed = [];

        foreach ($packages as $package) {
            $composerData = $this->loadComposerJson($package);
            $arqel = $this->extractArqelMeta($composerData);

            $status = '-';
            if ($this->option('validate')) {
                $result = $validator->validateComposerJson($composerData);
                if (! $result->passed) {
                    $status = 'fail';
                } elseif ($result->warnings !== []) {
                    $status = 'warn';
                } else {
                    $status = 'ok';
                }
                $detailed[$package] = $result;
            }

            $rows[] = [
                'name' => $package,
                'version' => $this->safeVersion($package),
                'plugin-type' => $arqel['plugin-type'] ?? '-',
                'category' => $arqel['category'] ?? '-',
                'status' => $status,
            ];
        }

        $this->table(['Name', 'Version', 'Plugin Type', 'Category', 'Status'], $rows);

        if ($this->option('validate')) {
            foreach ($detailed as $name => $result) {
                $this->line('');
                $this->line("<info>{$name}</info>");
                foreach ($result->checks as $check) {
                    $marker = match ($check['status']) {
                        'ok' => '<fg=green>ok</>',
                        'warn' => '<fg=yellow>warn</>',
                        'fail' => '<fg=red>fail</>',
                        default => $check['status'],
                    };
                    $this->line("  [{$marker}] {$check['name']}: {$check['message']}");
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function discoverPlugins(): array
    {
        if (! class_exists(InstalledVersions::class)) {
            return [];
        }

        /** @var list<string> $packages */
        $packages = InstalledVersions::getInstalledPackagesByType('arqel-plugin');

        sort($packages);

        return $packages;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadComposerJson(string $package): array
    {
        try {
            $installPath = InstalledVersions::getInstallPath($package);
        } catch (\Throwable) {
            return [];
        }

        if (! is_string($installPath) || $installPath === '') {
            return [];
        }

        $path = rtrim($installPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'composer.json';
        if (! is_file($path)) {
            return [];
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return [];
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $composerData
     * @return array<string, mixed>
     */
    private function extractArqelMeta(array $composerData): array
    {
        $extra = $composerData['extra'] ?? [];
        if (! is_array($extra)) {
            return [];
        }
        $arqel = $extra['arqel'] ?? [];

        if (! is_array($arqel)) {
            return [];
        }

        /** @var array<string, mixed> $arqel */
        return $arqel;
    }

    private function safeVersion(string $package): string
    {
        try {
            $version = InstalledVersions::getPrettyVersion($package);
        } catch (\Throwable) {
            return '-';
        }

        return is_string($version) && $version !== '' ? $version : '-';
    }
}

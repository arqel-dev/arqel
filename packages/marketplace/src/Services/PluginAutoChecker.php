<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Services;

use Arqel\Marketplace\Models\Plugin;

/**
 * Auto-checks defensivos para submissão de plugin (MKTPLC-002).
 *
 * Não realiza chamadas de rede — todas as validações são locais sobre o
 * payload já persistido. Cada check retorna um array com `name`, `status`
 * (`ok|warn|fail`) e `message`. O resultado agregado expõe `passed` (true
 * se nenhum check estiver `fail`) para uso no controller de submissão.
 */
final readonly class PluginAutoChecker
{
    public function __construct() {}

    /**
     * @return array{checks: list<array{name: string, status: string, message: string}>, passed: bool}
     */
    public function check(Plugin $plugin): array
    {
        $checks = [
            $this->checkComposerPackageFormat($plugin),
            $this->checkGithubUrlFormat($plugin),
            $this->checkDescriptionLength($plugin),
            $this->checkScreenshotsCount($plugin),
            $this->checkNameUniqueness($plugin),
        ];

        $passed = true;
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $passed = false;
                break;
            }
        }

        return [
            'checks' => $checks,
            'passed' => $passed,
        ];
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function checkComposerPackageFormat(Plugin $plugin): array
    {
        $package = $plugin->composer_package;

        if (! is_string($package) || preg_match('/^[a-z0-9-]+\/[a-z0-9-]+$/', $package) !== 1) {
            return [
                'name' => 'composer_package_format',
                'status' => 'fail',
                'message' => (string) __('arqel::messages.marketplace.auto_check.composer_package_invalid'),
            ];
        }

        return [
            'name' => 'composer_package_format',
            'status' => 'ok',
            'message' => (string) __('arqel::messages.marketplace.auto_check.composer_package_ok'),
        ];
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function checkGithubUrlFormat(Plugin $plugin): array
    {
        $host = parse_url($plugin->github_url, PHP_URL_HOST);

        if (! is_string($host) || strtolower($host) !== 'github.com') {
            return [
                'name' => 'github_url_format',
                'status' => 'fail',
                'message' => (string) __('arqel::messages.marketplace.auto_check.github_url_invalid'),
            ];
        }

        return [
            'name' => 'github_url_format',
            'status' => 'ok',
            'message' => (string) __('arqel::messages.marketplace.auto_check.github_url_ok'),
        ];
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function checkDescriptionLength(Plugin $plugin): array
    {
        $length = mb_strlen($plugin->description);

        if ($length < 50) {
            return [
                'name' => 'description_length',
                'status' => 'warn',
                'message' => (string) __('arqel::messages.marketplace.auto_check.description_short'),
            ];
        }

        return [
            'name' => 'description_length',
            'status' => 'ok',
            'message' => (string) __('arqel::messages.marketplace.auto_check.description_ok'),
        ];
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function checkScreenshotsCount(Plugin $plugin): array
    {
        $screenshots = $plugin->screenshots ?? [];
        $count = is_array($screenshots) ? count($screenshots) : 0;

        if ($count === 0) {
            return [
                'name' => 'screenshots_count',
                'status' => 'warn',
                'message' => (string) __('arqel::messages.marketplace.auto_check.screenshots_missing'),
            ];
        }

        return [
            'name' => 'screenshots_count',
            'status' => 'ok',
            'message' => (string) trans_choice('arqel::messages.marketplace.screenshots_count', $count, ['count' => $count]),
        ];
    }

    /**
     * @return array{name: string, status: string, message: string}
     */
    private function checkNameUniqueness(Plugin $plugin): array
    {
        $duplicates = Plugin::query()
            ->where('name', $plugin->name)
            ->where('id', '!=', $plugin->id)
            ->count();

        if ($duplicates > 0) {
            return [
                'name' => 'name_uniqueness',
                'status' => 'warn',
                'message' => (string) __('arqel::messages.marketplace.auto_check.name_duplicate'),
            ];
        }

        return [
            'name' => 'name_uniqueness',
            'status' => 'ok',
            'message' => (string) __('arqel::messages.marketplace.auto_check.name_unique'),
        ];
    }
}

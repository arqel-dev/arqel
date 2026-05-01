<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Services;

/**
 * Valida metadados de plugins Arqel contra a convention oficial (MKTPLC-003).
 *
 * Checa o `composer.json` (campos `type`, `extra.arqel.*`, `keywords`) e,
 * opcionalmente, o `package.json` quando o plugin tem peer npm. Não realiza
 * I/O — recebe arrays já decodificados para manter os checks puros e fáceis
 * de testar.
 */
final readonly class PluginConventionValidator
{
    private const ALLOWED_PLUGIN_TYPES = [
        'field-pack',
        'widget-pack',
        'theme',
        'integration',
        'language-pack',
        'tool',
    ];

    public function __construct() {}

    /**
     * Valida um array decodificado a partir de `composer.json`.
     *
     * @param array<string, mixed> $composerData
     */
    public function validateComposerJson(array $composerData): ConventionValidationResult
    {
        $checks = [];

        // type === 'arqel-plugin'
        $type = $composerData['type'] ?? null;
        if ($type !== 'arqel-plugin') {
            $checks[] = [
                'name' => 'composer_type',
                'status' => 'fail',
                'message' => 'composer.json must declare "type": "arqel-plugin".',
            ];
        } else {
            $checks[] = [
                'name' => 'composer_type',
                'status' => 'ok',
                'message' => 'Composer type is "arqel-plugin".',
            ];
        }

        $extra = $composerData['extra'] ?? [];
        $arqel = is_array($extra) ? ($extra['arqel'] ?? null) : null;

        if (! is_array($arqel)) {
            $checks[] = [
                'name' => 'extra_arqel',
                'status' => 'fail',
                'message' => 'composer.json must define extra.arqel object with plugin metadata.',
            ];

            return ConventionValidationResult::failed($checks);
        }

        $checks[] = [
            'name' => 'extra_arqel',
            'status' => 'ok',
            'message' => 'extra.arqel object present.',
        ];

        // plugin-type
        $pluginType = $arqel['plugin-type'] ?? null;
        if (! is_string($pluginType) || ! in_array($pluginType, self::ALLOWED_PLUGIN_TYPES, true)) {
            $allowed = implode(', ', self::ALLOWED_PLUGIN_TYPES);
            $checks[] = [
                'name' => 'plugin_type',
                'status' => 'fail',
                'message' => "extra.arqel.plugin-type must be one of: {$allowed}.",
            ];
        } else {
            $checks[] = [
                'name' => 'plugin_type',
                'status' => 'ok',
                'message' => "Plugin type is \"{$pluginType}\".",
            ];
        }

        // compat.arqel — semver constraint
        $compat = $arqel['compat'] ?? null;
        $compatArqel = is_array($compat) ? ($compat['arqel'] ?? null) : null;
        if (! is_string($compatArqel) || ! $this->isValidSemverConstraint($compatArqel)) {
            $checks[] = [
                'name' => 'compat_arqel',
                'status' => 'fail',
                'message' => 'extra.arqel.compat.arqel must be a semver constraint (e.g. "^1.0", "~2.5", ">=1.0 <2.0").',
            ];
        } else {
            $checks[] = [
                'name' => 'compat_arqel',
                'status' => 'ok',
                'message' => "Compat constraint \"{$compatArqel}\" is valid.",
            ];
        }

        // category
        $category = $arqel['category'] ?? null;
        if (! is_string($category) || trim($category) === '') {
            $checks[] = [
                'name' => 'category',
                'status' => 'fail',
                'message' => 'extra.arqel.category must be a non-empty string.',
            ];
        } else {
            $checks[] = [
                'name' => 'category',
                'status' => 'ok',
                'message' => "Category is \"{$category}\".",
            ];
        }

        // installation-instructions (optional, warn)
        $instructions = $arqel['installation-instructions'] ?? null;
        if (! is_string($instructions) || trim($instructions) === '') {
            $checks[] = [
                'name' => 'installation_instructions',
                'status' => 'warn',
                'message' => 'extra.arqel.installation-instructions is recommended (point to README or docs).',
            ];
        } else {
            $checks[] = [
                'name' => 'installation_instructions',
                'status' => 'ok',
                'message' => 'Installation instructions provided.',
            ];
        }

        // keywords contains 'arqel' AND 'plugin'
        $keywords = $composerData['keywords'] ?? [];
        if (! is_array($keywords)) {
            $keywords = [];
        }
        $hasArqel = in_array('arqel', $keywords, true);
        $hasPlugin = in_array('plugin', $keywords, true);
        if (! $hasArqel || ! $hasPlugin) {
            $missing = [];
            if (! $hasArqel) {
                $missing[] = '"arqel"';
            }
            if (! $hasPlugin) {
                $missing[] = '"plugin"';
            }
            $missingStr = implode(' and ', $missing);
            $checks[] = [
                'name' => 'keywords',
                'status' => 'warn',
                'message' => "keywords should include {$missingStr} for marketplace discoverability.",
            ];
        } else {
            $checks[] = [
                'name' => 'keywords',
                'status' => 'ok',
                'message' => 'Keywords include "arqel" and "plugin".',
            ];
        }

        $hasFailure = false;
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $hasFailure = true;
                break;
            }
        }

        return $hasFailure
            ? ConventionValidationResult::failed($checks)
            : ConventionValidationResult::success($checks);
    }

    /**
     * Valida um array decodificado a partir de `package.json` quando o plugin
     * tem peer npm. Aceita seja um campo `arqel.plugin-type` no root, seja
     * a presença de `@arqel/types` em `peerDependencies`.
     *
     * @param array<string, mixed> $packageData
     */
    public function validateNpmPackageJson(array $packageData): ConventionValidationResult
    {
        $checks = [];

        $arqel = $packageData['arqel'] ?? null;
        $pluginType = is_array($arqel) ? ($arqel['plugin-type'] ?? null) : null;

        $peers = $packageData['peerDependencies'] ?? [];
        $hasArqelPeer = is_array($peers) && array_key_exists('@arqel/types', $peers);

        if (is_string($pluginType) && in_array($pluginType, self::ALLOWED_PLUGIN_TYPES, true)) {
            $checks[] = [
                'name' => 'npm_plugin_type',
                'status' => 'ok',
                'message' => "package.json declares arqel.plugin-type \"{$pluginType}\".",
            ];
        } elseif ($hasArqelPeer) {
            $checks[] = [
                'name' => 'npm_plugin_type',
                'status' => 'ok',
                'message' => 'package.json peerDependencies include "@arqel/types".',
            ];
        } else {
            $allowed = implode(', ', self::ALLOWED_PLUGIN_TYPES);
            $checks[] = [
                'name' => 'npm_plugin_type',
                'status' => 'fail',
                'message' => "package.json must define arqel.plugin-type (one of: {$allowed}) or peerDependency \"@arqel/types\".",
            ];
        }

        // name kebab-case (warn)
        $name = $packageData['name'] ?? null;
        if (! is_string($name) || $name === '') {
            $checks[] = [
                'name' => 'npm_name',
                'status' => 'warn',
                'message' => 'package.json should declare a "name".',
            ];
        } else {
            $checks[] = [
                'name' => 'npm_name',
                'status' => 'ok',
                'message' => "package.json name is \"{$name}\".",
            ];
        }

        $hasFailure = false;
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $hasFailure = true;
                break;
            }
        }

        return $hasFailure
            ? ConventionValidationResult::failed($checks)
            : ConventionValidationResult::success($checks);
    }

    /**
     * Heurística semver: `^x.y`, `~x.y`, `>=x.y`, `>x`, `<=x`, `<x`,
     * `x.y.z`, `x.y.*`, ranges separados por espaço, alternativas por `||`.
     */
    private function isValidSemverConstraint(string $constraint): bool
    {
        $trimmed = trim($constraint);
        if ($trimmed === '') {
            return false;
        }

        // Aceita pipes alternativos (`^1.0 || ^2.0`).
        $alternatives = preg_split('/\s*\|\|\s*/', $trimmed) ?: [$trimmed];

        foreach ($alternatives as $alt) {
            $tokens = preg_split('/\s+/', trim($alt)) ?: [];
            if ($tokens === []) {
                return false;
            }
            foreach ($tokens as $token) {
                if (preg_match('/^([\^~]|>=|<=|>|<|=)?\d+(\.\d+){0,2}(\.[x*])?$/', $token) !== 1
                    && preg_match('/^\d+(\.\d+){0,2}$/', $token) !== 1) {
                    return false;
                }
            }
        }

        return true;
    }
}

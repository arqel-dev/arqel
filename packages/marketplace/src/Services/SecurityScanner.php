<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Services;

use Arqel\Marketplace\Contracts\VulnerabilityDatabase;
use Arqel\Marketplace\Events\PluginAutoDelistedEvent;
use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\SecurityScan;

/**
 * Scanner defensivo de segurança para plugins do marketplace (MKTPLC-009).
 *
 * Executa N checks **sem acesso a rede direto** (a fonte de vulnerabilidades
 * é abstraída por {@see VulnerabilityDatabase}). Persiste o resultado em
 * `arqel_plugin_security_scans` e auto-delista plugins com finding `critical`.
 *
 * Severidade rollup: pega o máximo encontrado nos findings.
 *
 * - `critical` → status `failed` + auto-delist (`status=archived`) + dispatch
 *   {@see PluginAutoDelistedEvent}.
 * - `high` ou `medium` → status `flagged`.
 * - `low` ou nenhum finding → status `passed`.
 */
final readonly class SecurityScanner
{
    private const SCANNER_VERSION = '1.0';

    /** @var list<string> */
    private const ALLOWED_LICENSES = ['MIT', 'Apache-2.0', 'BSD-2-Clause', 'BSD-3-Clause'];

    /** @var array<string, int> */
    private const SEVERITY_RANK = [
        'low' => 1,
        'medium' => 2,
        'high' => 3,
        'critical' => 4,
    ];

    public function __construct(
        private VulnerabilityDatabase $vulnDb,
    ) {}

    public function scan(Plugin $plugin): SecurityScan
    {
        /** @var SecurityScan $scan */
        $scan = SecurityScan::query()->create([
            'plugin_id' => $plugin->id,
            'scan_started_at' => now(),
            'status' => 'running',
            'scanner_version' => self::SCANNER_VERSION,
        ]);

        $findings = [];

        foreach ($this->lookupVulnerabilities($plugin) as $finding) {
            $findings[] = $finding;
        }

        $licenseFinding = $this->checkLicense($plugin);
        if ($licenseFinding !== null) {
            $findings[] = $licenseFinding;
        }

        // Check 3 — suspicious patterns (TODO MKTPLC-009-static-analysis):
        // estática de código real exigirá clonar o repositório do plugin e
        // analisar AST/regex. Fora do escopo desta versão.

        $severity = $this->rollupSeverity($findings);
        $status = $this->statusFor($severity);

        $scan->forceFill([
            'findings' => $findings,
            'severity' => $severity,
            'status' => $status,
            'scan_completed_at' => now(),
        ])->save();

        if ($severity === 'critical' && $plugin->status === 'published') {
            $plugin->forceFill(['status' => 'archived'])->save();
            PluginAutoDelistedEvent::dispatch($plugin, $scan);
        }

        return $scan;
    }

    /**
     * @return list<array{type: string, severity: string, advisory_id: string, summary: string, package: string, ecosystem: string}>
     */
    private function lookupVulnerabilities(Plugin $plugin): array
    {
        $findings = [];

        if (is_string($plugin->composer_package) && $plugin->composer_package !== '') {
            foreach ($this->vulnDb->lookup($plugin->composer_package, 'composer') as $advisory) {
                $findings[] = [
                    'type' => 'vulnerability',
                    'severity' => $advisory->severity,
                    'advisory_id' => $advisory->id,
                    'summary' => $advisory->summary,
                    'package' => $plugin->composer_package,
                    'ecosystem' => 'composer',
                ];
            }
        }

        if (is_string($plugin->npm_package) && $plugin->npm_package !== '') {
            foreach ($this->vulnDb->lookup($plugin->npm_package, 'npm') as $advisory) {
                $findings[] = [
                    'type' => 'vulnerability',
                    'severity' => $advisory->severity,
                    'advisory_id' => $advisory->id,
                    'summary' => $advisory->summary,
                    'package' => $plugin->npm_package,
                    'ecosystem' => 'npm',
                ];
            }
        }

        return $findings;
    }

    /**
     * @return array{type: string, severity: string, license: string, summary: string}|null
     */
    private function checkLicense(Plugin $plugin): ?array
    {
        $license = $plugin->license;

        if (! is_string($license) || $license === '') {
            return [
                'type' => 'license-warning',
                'severity' => 'low',
                'license' => '',
                'summary' => 'Plugin has no declared license.',
            ];
        }

        if (in_array($license, self::ALLOWED_LICENSES, true)) {
            return null;
        }

        return [
            'type' => 'license-warning',
            'severity' => 'low',
            'license' => $license,
            'summary' => "License '{$license}' is not in the recommended allow-list.",
        ];
    }

    /**
     * @param list<array<string, mixed>> $findings
     */
    private function rollupSeverity(array $findings): ?string
    {
        $max = 0;
        $label = null;

        foreach ($findings as $finding) {
            $severity = $finding['severity'] ?? null;
            if (! is_string($severity)) {
                continue;
            }

            $rank = self::SEVERITY_RANK[$severity] ?? 0;
            if ($rank > $max) {
                $max = $rank;
                $label = $severity;
            }
        }

        return $label;
    }

    private function statusFor(?string $severity): string
    {
        return match ($severity) {
            'critical' => 'failed',
            'high', 'medium' => 'flagged',
            default => 'passed',
        };
    }
}

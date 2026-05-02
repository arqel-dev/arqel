<?php

declare(strict_types=1);

namespace Arqel\Core\Console;

use FilesystemIterator;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * `arqel:audit` — verifica a prontidão do monorepo Arqel para release.
 *
 * Diferente de `arqel:doctor` (que diagnostica a instalação local da
 * app hospedeira), este comando valida o **monorepo** em si: cada
 * pacote `arqel/*` precisa ter `SKILL.md` canônico, `composer.json`
 * válido, entrada no `CHANGELOG.md` raiz e o suite de testes precisa
 * estar acima de um threshold sanity. Os checks são read-only e
 * defensivos — qualquer falha individual degrada para `warn`.
 *
 * Output modes:
 *   - default: emoji + colour, um check por linha
 *   - `--json`: linha única `{checks: [...], summary: {...}}`
 *
 * Exit code:
 *   - 0 quando não há `fail` (e nem `warn` em `--strict`)
 *   - 1 caso contrário
 */
final class AuditCommand extends Command
{
    private const string STATUS_OK = 'ok';

    private const string STATUS_WARN = 'warn';

    private const string STATUS_FAIL = 'fail';

    private const string STATUS_NEUTRAL = 'neutral';

    /** @var string */
    protected $signature = 'arqel:audit {--json : Output as JSON} {--strict : Exit non-zero on warnings}';

    /** @var string */
    protected $description = 'Audit Arqel monorepo readiness for release.';

    public function handle(): int
    {
        $root = $this->detectMonorepoRoot();

        /** @var list<array{name: string, status: string, message: string, details?: mixed}> $checks */
        $checks = [
            $this->checkPackagesSkillMd($root),
            $this->checkPackagesComposerJson($root),
            $this->checkChangelogEntry($root),
            $this->checkSkillCanonicalLayout($root),
            $this->checkTestSuiteSize($root),
        ];

        $summary = $this->summarise($checks);

        if ($this->option('json') === true) {
            $this->line((string) json_encode([
                'checks' => $checks,
                'summary' => $summary,
                'root' => $root,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderHuman($checks, $summary, $root);
        }

        $strict = $this->option('strict') === true;

        if ($summary['fail'] > 0) {
            return self::FAILURE;
        }

        if ($strict && $summary['warn'] > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Tenta localizar a raiz do monorepo subindo a partir do pacote
     * core. Quando o pacote roda dentro de uma app hospedeira (em vez
     * de dentro do monorepo), `null` é retornado e os checks degradam
     * graciosamente.
     */
    private function detectMonorepoRoot(): ?string
    {
        $candidates = [
            // packages/core/src/Console -> ../../../../
            dirname(__DIR__, 4),
            // worktree variants
            dirname(__DIR__, 5),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || ! is_dir($candidate)) {
                continue;
            }
            if (is_dir($candidate.'/packages')
                && is_file($candidate.'/CHANGELOG.md')
                && is_file($candidate.'/composer.json')) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function listPackagePaths(?string $root): array
    {
        if ($root === null) {
            return [];
        }

        $paths = [];
        foreach (['packages', 'packages-js'] as $namespace) {
            $base = $root.'/'.$namespace;
            if (! is_dir($base)) {
                continue;
            }
            $entries = scandir($base);
            if ($entries === false) {
                continue;
            }
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = $base.'/'.$entry;
                if (is_dir($path)) {
                    $paths[] = $path;
                }
            }
        }

        sort($paths);

        return $paths;
    }

    /**
     * @return array{name: string, status: string, message: string, details?: mixed}
     */
    private function checkPackagesSkillMd(?string $root): array
    {
        try {
            $packages = $this->listPackagePaths($root);

            if ($packages === []) {
                return [
                    'name' => 'packages.skill_md',
                    'status' => self::STATUS_NEUTRAL,
                    'message' => 'Monorepo root not detected — skipping SKILL.md sweep.',
                ];
            }

            $missing = [];
            foreach ($packages as $path) {
                if (! is_file($path.'/SKILL.md')) {
                    $missing[] = basename($path);
                }
            }

            return [
                'name' => 'packages.skill_md',
                'status' => $missing === [] ? self::STATUS_OK : self::STATUS_FAIL,
                'message' => $missing === []
                    ? count($packages).' packages have SKILL.md.'
                    : 'Missing SKILL.md in: '.implode(', ', $missing).'.',
                'details' => ['total' => count($packages), 'missing' => $missing],
            ];
        } catch (Throwable $e) {
            return $this->fromThrowable('packages.skill_md', $e);
        }
    }

    /**
     * @return array{name: string, status: string, message: string, details?: mixed}
     */
    private function checkPackagesComposerJson(?string $root): array
    {
        try {
            if ($root === null) {
                return [
                    'name' => 'packages.composer_json',
                    'status' => self::STATUS_NEUTRAL,
                    'message' => 'Monorepo root not detected.',
                ];
            }

            $base = $root.'/packages';
            $invalid = [];
            $checked = 0;

            if (is_dir($base)) {
                $entries = scandir($base) ?: [];
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }
                    $jsonPath = $base.'/'.$entry.'/composer.json';
                    if (! is_file($jsonPath)) {
                        continue;
                    }

                    $checked++;
                    $raw = file_get_contents($jsonPath);
                    if ($raw === false) {
                        $invalid[] = $entry.' (unreadable)';

                        continue;
                    }

                    $data = json_decode($raw, true);
                    if (! is_array($data)) {
                        $invalid[] = $entry.' (invalid JSON)';

                        continue;
                    }

                    $issues = [];
                    if (! isset($data['name']) || ! is_string($data['name'])) {
                        $issues[] = 'name';
                    }
                    if (! isset($data['type']) || ! is_string($data['type'])) {
                        $issues[] = 'type';
                    }
                    if (! isset($data['license']) || $data['license'] !== 'MIT') {
                        $issues[] = 'license!=MIT';
                    }
                    $require = $data['require'] ?? null;
                    if (! is_array($require) || ! isset($require['php'])) {
                        $issues[] = 'require.php';
                    }

                    if ($issues !== []) {
                        $invalid[] = $entry.' ('.implode(', ', $issues).')';
                    }
                }
            }

            return [
                'name' => 'packages.composer_json',
                'status' => $invalid === [] ? self::STATUS_OK : self::STATUS_FAIL,
                'message' => $invalid === []
                    ? "{$checked} composer.json files are valid."
                    : 'Invalid composer.json: '.implode('; ', $invalid).'.',
                'details' => ['checked' => $checked, 'invalid' => $invalid],
            ];
        } catch (Throwable $e) {
            return $this->fromThrowable('packages.composer_json', $e);
        }
    }

    /**
     * @return array{name: string, status: string, message: string, details?: mixed}
     */
    private function checkChangelogEntry(?string $root): array
    {
        try {
            if ($root === null) {
                return [
                    'name' => 'packages.changelog_entry',
                    'status' => self::STATUS_NEUTRAL,
                    'message' => 'Monorepo root not detected.',
                ];
            }

            $path = $root.'/CHANGELOG.md';
            if (! is_file($path)) {
                return [
                    'name' => 'packages.changelog_entry',
                    'status' => self::STATUS_FAIL,
                    'message' => 'CHANGELOG.md missing at monorepo root.',
                ];
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                return [
                    'name' => 'packages.changelog_entry',
                    'status' => self::STATUS_WARN,
                    'message' => 'CHANGELOG.md unreadable.',
                ];
            }

            $hasUnreleased = str_contains($contents, '## [Unreleased]');
            $hasRcEntry = (bool) preg_match('/##\s*\[0\.8\.0-rc\.1[^\]]*\]/', $contents);

            $ok = $hasUnreleased || $hasRcEntry;

            return [
                'name' => 'packages.changelog_entry',
                'status' => $ok ? self::STATUS_OK : self::STATUS_WARN,
                'message' => $ok
                    ? 'CHANGELOG.md has Unreleased / 0.8.0-rc.1 entry.'
                    : 'CHANGELOG.md missing Unreleased and 0.8.0-rc.1 entries.',
                'details' => ['unreleased' => $hasUnreleased, 'rc1' => $hasRcEntry],
            ];
        } catch (Throwable $e) {
            return $this->fromThrowable('packages.changelog_entry', $e);
        }
    }

    /**
     * @return array{name: string, status: string, message: string, details?: mixed}
     */
    private function checkSkillCanonicalLayout(?string $root): array
    {
        try {
            $packages = $this->listPackagePaths($root);

            if ($packages === []) {
                return [
                    'name' => 'docs.skill_canonical',
                    'status' => self::STATUS_NEUTRAL,
                    'message' => 'No packages discovered.',
                ];
            }

            $offenders = [];
            foreach ($packages as $path) {
                $skill = $path.'/SKILL.md';
                if (! is_file($skill)) {
                    continue;
                }
                $contents = file_get_contents($skill);
                if ($contents === false) {
                    continue;
                }
                $hasPurpose = str_contains($contents, '## Purpose');
                $hasStatus = str_contains($contents, '## Status');
                if (! ($hasPurpose && $hasStatus)) {
                    $offenders[] = basename($path);
                }
            }

            return [
                'name' => 'docs.skill_canonical',
                'status' => $offenders === [] ? self::STATUS_OK : self::STATUS_WARN,
                'message' => $offenders === []
                    ? 'All SKILL.md follow canonical layout (Purpose + Status).'
                    : count($offenders).' SKILL.md missing canonical headers: '.implode(', ', $offenders).'.',
                'details' => ['offenders' => $offenders],
            ];
        } catch (Throwable $e) {
            return $this->fromThrowable('docs.skill_canonical', $e);
        }
    }

    /**
     * @return array{name: string, status: string, message: string, details?: mixed}
     */
    private function checkTestSuiteSize(?string $root): array
    {
        try {
            if ($root === null) {
                return [
                    'name' => 'tests.suite_size',
                    'status' => self::STATUS_NEUTRAL,
                    'message' => 'Monorepo root not detected.',
                ];
            }

            $count = 0;
            $base = $root.'/packages';
            if (is_dir($base)) {
                $entries = scandir($base) ?: [];
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }
                    $testsDir = $base.'/'.$entry.'/tests';
                    if (! is_dir($testsDir)) {
                        continue;
                    }
                    $count += $this->countTestFiles($testsDir);
                }
            }

            // Threshold é o número de **arquivos** *Test.php; o suite
            // tem ~1.249 testes individuais, mas medimos arquivos
            // porque é mais barato e estável.
            $threshold = 100;

            return [
                'name' => 'tests.suite_size',
                'status' => $count >= $threshold ? self::STATUS_OK : self::STATUS_WARN,
                'message' => "{$count} Pest test files discovered (threshold: {$threshold}).",
                'details' => ['count' => $count, 'threshold' => $threshold],
            ];
        } catch (Throwable $e) {
            return $this->fromThrowable('tests.suite_size', $e);
        }
    }

    private function countTestFiles(string $dir): int
    {
        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo) {
                continue;
            }
            $name = $file->getFilename();
            if (str_ends_with($name, 'Test.php')) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array{name: string, status: string, message: string, details?: mixed}
     */
    private function fromThrowable(string $name, Throwable $e): array
    {
        return [
            'name' => $name,
            'status' => self::STATUS_WARN,
            'message' => "Check '{$name}' degraded: ".$e->getMessage(),
            'details' => ['exception' => $e::class],
        ];
    }

    /**
     * @param list<array{name: string, status: string, message: string, details?: mixed}> $checks
     *
     * @return array{ok: int, warn: int, fail: int, neutral: int}
     */
    private function summarise(array $checks): array
    {
        $summary = ['ok' => 0, 'warn' => 0, 'fail' => 0, 'neutral' => 0];
        foreach ($checks as $check) {
            $key = $check['status'];
            if ($key === self::STATUS_OK
                || $key === self::STATUS_WARN
                || $key === self::STATUS_FAIL
                || $key === self::STATUS_NEUTRAL) {
                $summary[$key]++;
            }
        }

        return $summary;
    }

    /**
     * @param list<array{name: string, status: string, message: string, details?: mixed}> $checks
     * @param array{ok: int, warn: int, fail: int, neutral: int} $summary
     */
    private function renderHuman(array $checks, array $summary, ?string $root): void
    {
        $this->line('<fg=cyan;options=bold>Arqel Audit</> — monorepo release readiness');
        if ($root !== null) {
            $this->line("<fg=gray>root:</> {$root}");
        }
        $this->line('');

        foreach ($checks as $check) {
            $icon = match ($check['status']) {
                self::STATUS_OK => '<fg=green>[ok]</>   ✅',
                self::STATUS_WARN => '<fg=yellow>[warn]</> ⚠️ ',
                self::STATUS_FAIL => '<fg=red>[fail]</> ❌',
                self::STATUS_NEUTRAL => '<fg=gray>[info]</> ℹ️ ',
                default => '[?]',
            };

            $this->line(sprintf('%s %s — %s', $icon, $check['name'], $check['message']));
        }

        $this->line('');
        $this->line(sprintf(
            '<options=bold>Summary:</> %d ok • %d warn • %d fail • %d info',
            $summary['ok'],
            $summary['warn'],
            $summary['fail'],
            $summary['neutral'],
        ));
    }
}

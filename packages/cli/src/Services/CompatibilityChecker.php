<?php

declare(strict_types=1);

namespace Arqel\Cli\Services;

use InvalidArgumentException;

/**
 * Lightweight Composer-style semver constraint checker.
 *
 * Implements a documented subset of Composer constraints sufficient for
 * marketplace `compat.arqel` strings:
 *
 *  - exact:   `1.2.3`
 *  - caret:   `^1.0` matches >=1.0.0,<2.0.0 (zero-major: `^0.2` matches >=0.2.0,<0.3.0)
 *  - tilde:   `~2.5` matches >=2.5.0,<3.0.0; `~2.5.1` matches >=2.5.1,<2.6.0
 *  - ge/gt/le/lt/eq prefixes: `>=1.0`, `>1.0`, `<=2.0`, `<2.0`, `=1.0`
 *  - composite (space- or comma-separated): `>=1.0 <2.0`
 *
 * Intentionally **does not** depend on `composer/semver` so the package
 * stays runtime-light and the rules are obvious in tests.
 */
final readonly class CompatibilityChecker
{
    public function check(string $constraint, string $version): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        $version = $this->normalizeVersion($version);

        // Composite constraints separated by commas or whitespace.
        $parts = preg_split('/[,\s]+/', $constraint) ?: [];
        if (count($parts) > 1) {
            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }
                if (! $this->checkSingle($part, $version)) {
                    return false;
                }
            }

            return true;
        }

        return $this->checkSingle($constraint, $version);
    }

    /**
     * @return array{int, int, int}
     */
    private function normalizeVersion(string $version): array
    {
        $clean = ltrim(trim($version), 'vV');
        // Strip pre-release / build metadata; we treat them as the base version.
        $clean = (string) preg_replace('/[-+].*$/', '', $clean);
        $segments = explode('.', $clean);

        $major = isset($segments[0]) ? (int) $segments[0] : 0;
        $minor = isset($segments[1]) ? (int) $segments[1] : 0;
        $patch = isset($segments[2]) ? (int) $segments[2] : 0;

        return [$major, $minor, $patch];
    }

    /**
     * @param  array{int, int, int}  $version
     */
    private function checkSingle(string $constraint, array $version): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '') {
            return true;
        }

        if ($constraint[0] === '^') {
            return $this->checkCaret(substr($constraint, 1), $version);
        }

        if ($constraint[0] === '~') {
            return $this->checkTilde(substr($constraint, 1), $version);
        }

        if (str_starts_with($constraint, '>=')) {
            return $this->compare($version, $this->normalizeVersion(substr($constraint, 2))) >= 0;
        }
        if (str_starts_with($constraint, '<=')) {
            return $this->compare($version, $this->normalizeVersion(substr($constraint, 2))) <= 0;
        }
        if ($constraint[0] === '>') {
            return $this->compare($version, $this->normalizeVersion(substr($constraint, 1))) > 0;
        }
        if ($constraint[0] === '<') {
            return $this->compare($version, $this->normalizeVersion(substr($constraint, 1))) < 0;
        }
        if ($constraint[0] === '=') {
            return $this->compare($version, $this->normalizeVersion(substr($constraint, 1))) === 0;
        }

        // Bare exact match.
        return $this->compare($version, $this->normalizeVersion($constraint)) === 0;
    }

    /**
     * @param  array{int, int, int}  $version
     */
    private function checkCaret(string $base, array $version): bool
    {
        if ($base === '') {
            throw new InvalidArgumentException("Empty caret constraint '^'.");
        }

        [$bMaj, $bMin, $bPatch] = $this->normalizeVersion($base);

        // Lower bound: base.
        if ($this->compare($version, [$bMaj, $bMin, $bPatch]) < 0) {
            return false;
        }

        // Upper bound:
        // - if major > 0: <(major+1).0.0
        // - if major == 0 and minor > 0: <0.(minor+1).0
        // - if base is 0.0.x: <0.0.(patch+1)
        if ($bMaj > 0) {
            return $version[0] < $bMaj + 1;
        }
        if ($bMin > 0) {
            return $version[0] === 0 && $version[1] < $bMin + 1;
        }

        return $version[0] === 0 && $version[1] === 0 && $version[2] < $bPatch + 1;
    }

    /**
     * @param  array{int, int, int}  $version
     */
    private function checkTilde(string $base, array $version): bool
    {
        if ($base === '') {
            throw new InvalidArgumentException("Empty tilde constraint '~'.");
        }

        $segmentCount = count(explode('.', $base));
        [$bMaj, $bMin, $bPatch] = $this->normalizeVersion($base);

        if ($this->compare($version, [$bMaj, $bMin, $bPatch]) < 0) {
            return false;
        }

        if ($segmentCount >= 3) {
            // ~2.5.1 → <2.6.0
            return $version[0] === $bMaj && $version[1] === $bMin;
        }

        // ~2.5 or ~2 → <(maj+1).0.0
        return $version[0] === $bMaj;
    }

    /**
     * @param  array{int, int, int}  $a
     * @param  array{int, int, int}  $b
     */
    private function compare(array $a, array $b): int
    {
        return $a[0] <=> $b[0] ?: ($a[1] <=> $b[1] ?: $a[2] <=> $b[2]);
    }
}

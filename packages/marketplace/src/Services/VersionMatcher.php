<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Services;

use Composer\Semver\Semver;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Decides whether a plugin's installed version falls within an advisory's
 * affected-version constraint.
 *
 * Fails safe to `true` (affected): an unknown installed version, an empty
 * constraint, or an unparseable input must never let a plugin escape the
 * security scanner. A data error flags for human review, it never silently
 * marks a plugin safe.
 */
final class VersionMatcher
{
    /**
     * @param string $affectedConstraint Composer constraint of affected
     *                                    versions (e.g. '<2.0', '>=1.0.1,<1.5').
     */
    public static function isAffected(?string $installed, string $affectedConstraint): bool
    {
        if ($installed === null || trim($installed) === '') {
            return true;
        }

        if (trim($affectedConstraint) === '') {
            return true;
        }

        try {
            return Semver::satisfies($installed, $affectedConstraint);
        } catch (UnexpectedValueException | InvalidArgumentException) {
            return true;
        }
    }
}

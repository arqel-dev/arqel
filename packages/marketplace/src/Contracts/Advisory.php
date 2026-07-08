<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Contracts;

/**
 * Value-object representando um advisory retornado por uma {@see VulnerabilityDatabase}.
 *
 * Imutável (`final readonly`). `severity` segue o vocabulário do scanner:
 * `low`, `medium`, `high`, `critical`.
 */
final readonly class Advisory
{
    /**
     * @param string $affectedVersions Composer constraint of the versions this
     *                                 advisory affects (e.g. '<2.0', '>=1.0.1,<1.5').
     *                                 A plugin is vulnerable when its installed
     *                                 version satisfies this constraint.
     */
    public function __construct(
        public string $id,
        public string $severity,
        public string $summary,
        public string $affectedVersions,
    ) {}
}

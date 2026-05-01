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
    public function __construct(
        public string $id,
        public string $severity,
        public string $summary,
        public string $fixedIn,
    ) {}
}

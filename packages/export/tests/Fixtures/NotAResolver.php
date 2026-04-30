<?php

declare(strict_types=1);

namespace Arqel\Export\Tests\Fixtures;

/**
 * Intentionally does NOT implement `RecordsResolver` — used to assert
 * that `ProcessExportJob` rejects misconfigured resolver classes.
 */
final class NotAResolver
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolve(): array
    {
        return [];
    }
}

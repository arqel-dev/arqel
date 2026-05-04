<?php

declare(strict_types=1);

namespace Arqel\Realtime\Tests\Fixtures;

/**
 * Mimics the `getSlug()` contract from `Arqel\Core\Resources\Resource`
 * without coupling the realtime package's tests to `arqel-dev/core` (the
 * core test runner has its own DB schema and fixture set, and pulling
 * it in would slow down `realtime`'s suite considerably).
 */
final class FakePostResource
{
    public static function getSlug(): string
    {
        return 'posts';
    }
}

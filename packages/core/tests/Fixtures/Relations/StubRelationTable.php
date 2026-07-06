<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Relations;

/**
 * Duck-typed stand-in for Arqel\Table\Table — mirrors the shape the
 * RelationManager serializer relies on (`toArray()`), without a hard dep
 * on arqel-dev/table (core stays dependency-free). Mirrors the existing
 * StubTableWithActions pattern in RowActionDispatchTest.php.
 */
final class StubRelationTable
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['columns' => []];
    }
}

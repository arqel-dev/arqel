<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Relations;

/**
 * Duck-typed stand-in for Arqel\Table\Table — mirrors the shape the
 * RelationManager serializer relies on (`toArray()`), without a hard dep
 * on arqel-dev/table (core stays dependency-free). Mirrors the existing
 * StubTableWithActions pattern in RowActionDispatchTest.php.
 *
 * Optionally carries a `getColumns()` list (duck-typed `StubRelationColumn`
 * instances) so `RelationController::index()` can be tested against the
 * same column-serialization pipeline (`InertiaDataBuilder::
 * applyColumnSerialization()`) the main resource index uses — review
 * finding I1. Defaults to `[]`, matching the pre-existing behaviour for
 * every other test in this suite (no columns declared).
 */
final class StubRelationTable
{
    /** @param array<int, mixed> $columns */
    public function __construct(private readonly array $columns = []) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['columns' => []];
    }

    /** @return array<int, mixed> */
    public function getColumns(): array
    {
        return $this->columns;
    }
}

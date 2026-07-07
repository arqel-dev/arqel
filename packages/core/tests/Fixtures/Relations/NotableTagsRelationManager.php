<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Relations;

use Arqel\Core\Relations\RelationManager;

/**
 * Same underlying `tags` belongsToMany relation as {@see TagsRelationManager},
 * but declares `pivotFields()` to allow the pivot `note` column — proves the
 * allowlist in `RelationController::attach()` lets through a client-declared
 * column while still dropping anything not on the list.
 */
final class NotableTagsRelationManager extends RelationManager
{
    public static string $relationship = 'tags';

    public function table(): mixed
    {
        return new StubRelationTable;
    }

    public function pivotFields(): array
    {
        return ['note'];
    }
}

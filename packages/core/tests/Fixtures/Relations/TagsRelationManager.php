<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Relations;

use Arqel\Core\Relations\RelationManager;

final class TagsRelationManager extends RelationManager
{
    public static string $relationship = 'tags';

    public function table(): mixed
    {
        return new StubRelationTable;
    }
}

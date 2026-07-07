<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Resources;

use Arqel\Core\Resources\Resource;
use Arqel\Core\Tests\Fixtures\Models\RelPost;
use Arqel\Core\Tests\Fixtures\Relations\NotableTagsRelationManager;

/**
 * Same underlying model as {@see RelPostResource} but wired to
 * {@see NotableTagsRelationManager} (declares `pivotFields()`) under a
 * distinct resource slug — `RelationManager::slug()` derives from
 * `$relationship`, so a manager sharing the `tags` relationship cannot
 * coexist with {@see \Arqel\Core\Tests\Fixtures\Relations\TagsRelationManager}
 * on the SAME resource's relation map (same array key). This fixture proves
 * the pivotFields() allow-path in isolation.
 */
final class RelPostNotableResource extends Resource
{
    public static string $model = RelPost::class;

    public function fields(): array
    {
        return [];
    }

    public function relations(): array
    {
        return [NotableTagsRelationManager::class];
    }
}

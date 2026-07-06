<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Resources;

use Arqel\Core\Resources\Resource;
use Arqel\Core\Tests\Fixtures\Models\RelPost;
use Arqel\Core\Tests\Fixtures\Relations\CommentsRelationManager;
use Arqel\Core\Tests\Fixtures\Relations\TagsRelationManager;

final class RelPostResource extends Resource
{
    public static string $model = RelPost::class;

    public function fields(): array
    {
        return [];
    }

    public function relations(): array
    {
        return [CommentsRelationManager::class, TagsRelationManager::class];
    }
}

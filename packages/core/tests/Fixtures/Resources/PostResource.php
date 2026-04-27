<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Resources;

use Arqel\Core\Resources\Resource;
use Arqel\Core\Tests\Fixtures\Models\Post;

final class PostResource extends Resource
{
    public static string $model = Post::class;

    public function fields(): array
    {
        return [];
    }
}

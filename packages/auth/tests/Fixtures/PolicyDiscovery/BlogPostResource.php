<?php

declare(strict_types=1);

namespace Arqel\Auth\Tests\Fixtures\PolicyDiscovery;

use Arqel\Auth\Tests\Fixtures\PolicyDiscovery\Models\BlogPost;

final class BlogPostResource
{
    public static function getModel(): string
    {
        return BlogPost::class;
    }
}

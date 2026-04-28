<?php

declare(strict_types=1);

namespace Arqel\Auth\Tests\Fixtures\PolicyDiscovery;

use Arqel\Auth\Tests\Fixtures\PolicyDiscovery\Models\BlogPost;
use Arqel\Auth\Tests\Fixtures\PolicyDiscovery\Policies\CustomBlogPostPolicy;

final class OverridePolicyResource
{
    public static ?string $policy = CustomBlogPostPolicy::class;

    public static function getModel(): string
    {
        return BlogPost::class;
    }
}

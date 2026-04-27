<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Resources;

use Arqel\Core\Contracts\HasResource;
use Arqel\Core\Tests\Fixtures\Models\Post;

final class PostResource implements HasResource
{
    public static function getModel(): string
    {
        return Post::class;
    }

    public static function getSlug(): string
    {
        return 'posts';
    }

    public static function getLabel(): string
    {
        return 'Post';
    }

    public static function getPluralLabel(): string
    {
        return 'Posts';
    }

    public static function getNavigationIcon(): ?string
    {
        return null;
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function getNavigationSort(): ?int
    {
        return null;
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Fields\Tests\Fixtures;

use Arqel\Core\Contracts\HasResource;

final class OtherStubResource implements HasResource
{
    public static function getModel(): string
    {
        return 'App\\Models\\Other';
    }

    public static function getSlug(): string
    {
        return 'others';
    }

    public static function getLabel(): string
    {
        return 'Other';
    }

    public static function getPluralLabel(): string
    {
        return 'Others';
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

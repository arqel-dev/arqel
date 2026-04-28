<?php

declare(strict_types=1);

namespace Arqel\Fields\Tests\Fixtures;

use Arqel\Core\Contracts\HasResource;

/**
 * Minimal Resource used to satisfy `is_subclass_of(..., HasResource::class)`
 * checks in BelongsTo/HasMany field tests without depending on the core
 * package's test fixtures (which are not autoloaded into this package).
 */
final class StubResource implements HasResource
{
    public static function getModel(): string
    {
        return 'App\\Models\\Stub';
    }

    public static function getSlug(): string
    {
        return 'stubs';
    }

    public static function getLabel(): string
    {
        return 'Stub';
    }

    public static function getPluralLabel(): string
    {
        return 'Stubs';
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

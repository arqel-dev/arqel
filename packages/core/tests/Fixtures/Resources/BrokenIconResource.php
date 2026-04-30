<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Resources;

use Arqel\Core\Contracts\HasResource;
use RuntimeException;

/**
 * Fixture Resource that throws from {@see getNavigationIcon()} only.
 *
 * Used by NavigationCommandProvider tests to assert the provider
 * still emits a Command (with `icon = null`) when icon resolution
 * blows up but the rest of the metadata is intact.
 */
final class BrokenIconResource implements HasResource
{
    public static function getModel(): string
    {
        return 'stdClass';
    }

    public static function getSlug(): string
    {
        return 'broken-icon';
    }

    public static function getLabel(): string
    {
        return 'Broken Icon';
    }

    public static function getPluralLabel(): string
    {
        return 'Broken Icons';
    }

    public static function getNavigationIcon(): ?string
    {
        throw new RuntimeException('icon read failed');
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

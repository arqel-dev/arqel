<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Resources;

use Arqel\Core\Contracts\HasResource;
use RuntimeException;

/**
 * Fixture Resource that throws from {@see getPluralLabel()}.
 *
 * Used by NavigationCommandProvider tests to assert the provider
 * silently skips Resources whose plural-label resolution fails —
 * the slug alone isn't enough to build a meaningful Command.
 */
final class BrokenPluralLabelResource implements HasResource
{
    public static function getModel(): string
    {
        return 'stdClass';
    }

    public static function getSlug(): string
    {
        return 'broken-plural';
    }

    public static function getLabel(): string
    {
        return 'Broken Plural';
    }

    public static function getPluralLabel(): string
    {
        throw new RuntimeException('plural label read failed');
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

<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Resources;

use Arqel\Core\Contracts\HasResource;
use RuntimeException;

/**
 * Fixture Resource that throws from {@see getSlug()}.
 *
 * Used by NavigationCommandProvider tests to assert that a
 * Resource which fails on the critical metadata path is silently
 * skipped — no exception escapes and no half-built Command is
 * emitted.
 */
final class BrokenSlugResource implements HasResource
{
    public static function getModel(): string
    {
        return 'stdClass';
    }

    public static function getSlug(): string
    {
        throw new RuntimeException('slug read failed');
    }

    public static function getLabel(): string
    {
        return 'Broken Slug';
    }

    public static function getPluralLabel(): string
    {
        return 'Broken Slugs';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'bug';
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

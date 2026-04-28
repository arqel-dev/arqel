<?php

declare(strict_types=1);

namespace Arqel\Nav\Tests\Fixtures;

/**
 * Minimal Resource fixture used by Navigation tests. Implements
 * just the static getters Navigation calls into — avoids pulling
 * `Arqel\Core\Resources\Resource` (which requires `$model`).
 */
final class UserResource
{
    public static function getSlug(): string
    {
        return 'users';
    }

    public static function getPluralLabel(): string
    {
        return 'Users';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'user';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'People';
    }

    public static function getNavigationSort(): int
    {
        return 5;
    }
}

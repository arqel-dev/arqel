<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Resources;

use Arqel\Core\Contracts\HasResource;
use Arqel\Core\Tests\Fixtures\Models\User;

final class UserResource implements HasResource
{
    public static function getModel(): string
    {
        return User::class;
    }

    public static function getSlug(): string
    {
        return 'users';
    }

    public static function getLabel(): string
    {
        return 'User';
    }

    public static function getPluralLabel(): string
    {
        return 'Users';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-user';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }
}

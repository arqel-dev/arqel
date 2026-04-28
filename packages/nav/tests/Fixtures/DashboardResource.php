<?php

declare(strict_types=1);

namespace Arqel\Nav\Tests\Fixtures;

final class DashboardResource
{
    public static function getSlug(): string
    {
        return 'dashboard';
    }

    public static function getPluralLabel(): string
    {
        return 'Dashboard';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'home';
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function getNavigationSort(): int
    {
        return 0;
    }
}

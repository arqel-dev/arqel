<?php

declare(strict_types=1);

namespace Arqel\Auth\Tests\Fixtures\PolicyDiscovery;

final class OrphanResource
{
    public static function getModel(): string
    {
        return OrphanModel::class;
    }
}

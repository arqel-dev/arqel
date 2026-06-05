<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Discoverable;

use Arqel\Core\Resources\Resource;
use Arqel\Core\Tests\Fixtures\Models\Stub;

final class DiscoverableResource extends Resource
{
    public static string $model = Stub::class;

    public static ?string $slug = 'discoverables';

    public function fields(): array
    {
        return [];
    }
}

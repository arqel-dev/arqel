<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\GlobalSearch;

use Arqel\Core\Resources\Resource;

class RsPersonResource extends Resource
{
    public static string $model = RsPerson::class;

    public static function getSlug(): string
    {
        return 'people';
    }

    public static function globallySearchable(): array
    {
        return ['name', 'email'];
    }

    public function fields(): array
    {
        return [];
    }
}

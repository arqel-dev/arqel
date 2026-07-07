<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\GlobalSearch;

use Arqel\Core\Resources\Resource;

// Registered resource whose globallySearchable() is left at the trait
// default ([]) — used to assert the provider skips resources that have
// not opted in to global search (security-by-default).
class RsSilentResource extends Resource
{
    public static string $model = RsPerson::class;

    public static function getSlug(): string
    {
        return 'silent';
    }

    public function fields(): array
    {
        return [];
    }
}

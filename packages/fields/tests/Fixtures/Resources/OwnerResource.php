<?php

declare(strict_types=1);

namespace Arqel\Fields\Tests\Fixtures\Resources;

use Arqel\Core\Resources\Resource;
use Arqel\Fields\Tests\Fixtures\Models\StubModel;
use Arqel\Fields\Types\TextField;

/**
 * Standalone "related" Resource referenced by `OwningResource`'s
 * BelongsTo field. Has a `recordTitle()` so the search controller
 * can render labels.
 */
final class OwnerResource extends Resource
{
    public static string $model = StubModel::class;

    public static ?string $slug = 'owners';

    public function fields(): array
    {
        return [new TextField('name')];
    }
}

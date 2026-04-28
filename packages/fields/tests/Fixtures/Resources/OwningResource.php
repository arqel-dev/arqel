<?php

declare(strict_types=1);

namespace Arqel\Fields\Tests\Fixtures\Resources;

use Arqel\Core\Resources\Resource;
use Arqel\Fields\Tests\Fixtures\Models\StubModel;
use Arqel\Fields\Types\BelongsToField;
use Arqel\Fields\Types\TextField;

/**
 * Fixture used by the search controller tests. Declares one
 * BelongsToField with search enabled (`owner_id`) and a second
 * with search explicitly disabled (`inactive_owner_id`) plus a
 * plain TextField (`name`) so the 400 branch is reachable.
 */
final class OwningResource extends Resource
{
    public static string $model = StubModel::class;

    public static ?string $slug = 'owning-resources';

    public function fields(): array
    {
        return [
            new TextField('name'),
            BelongsToField::make('owner_id', OwnerResource::class)->searchable(),
            BelongsToField::make('inactive_owner', OwnerResource::class)->searchable(false),
        ];
    }
}

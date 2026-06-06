<?php

declare(strict_types=1);

namespace Arqel\Fields\Tests\Fixtures\Resources;

use Arqel\Core\Resources\Resource;
use Arqel\Fields\Tests\Fixtures\Models\StubModel;
use Arqel\Fields\Types\BelongsToField;

/**
 * Regression fixture for #94: declares its searchable BelongsToField
 * ONLY inside form() (layout-aware), with an empty flat fields().
 *
 * The search controller must resolve the field via effectiveFields()
 * — reading fields() alone would 404 even though the rest of the
 * framework (validation + rendering) sees the field through form().
 */
final class FormOnlyOwningResource extends Resource
{
    public static string $model = StubModel::class;

    public static ?string $slug = 'form-only-owning-resources';

    public function fields(): array
    {
        return [];
    }

    public function form(): mixed
    {
        return new StubForm([
            BelongsToField::make('owner_id', OwnerResource::class)
                ->searchable()
                ->searchColumns(['name']),
        ]);
    }
}

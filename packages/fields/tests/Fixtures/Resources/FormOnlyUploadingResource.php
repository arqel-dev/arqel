<?php

declare(strict_types=1);

namespace Arqel\Fields\Tests\Fixtures\Resources;

use Arqel\Core\Resources\Resource;
use Arqel\Fields\Tests\Fixtures\Models\StubModel;
use Arqel\Fields\Types\FileField;

/**
 * Regression fixture for #94: declares its upload field ONLY inside
 * form() (layout-aware), with an empty flat fields().
 *
 * The upload controller must resolve the field via effectiveFields()
 * — reading fields() alone would 404 on POST/DELETE upload.
 */
final class FormOnlyUploadingResource extends Resource
{
    public static string $model = StubModel::class;

    public static ?string $slug = 'form-only-uploading-resources';

    public function fields(): array
    {
        return [];
    }

    public function form(): mixed
    {
        return new StubForm([
            (new FileField('avatar'))->disk('local'),
        ]);
    }
}

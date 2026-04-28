<?php

declare(strict_types=1);

namespace Arqel\Fields\Tests\Fixtures\Resources;

use Arqel\Core\Resources\Resource;
use Arqel\Fields\Tests\Fixtures\Models\StubModel;
use Arqel\Fields\Types\FileField;
use Arqel\Fields\Types\TextField;

/**
 * Fixture used by the upload controller tests. Exposes both an
 * upload-capable field (`avatar`) and a non-upload field (`name`)
 * so the 400 branch is reachable.
 */
final class UploadingResource extends Resource
{
    public static string $model = StubModel::class;

    public static ?string $slug = 'uploading-resources';

    public function fields(): array
    {
        return [
            new TextField('name'),
            (new FileField('avatar'))->disk('local'),
        ];
    }
}

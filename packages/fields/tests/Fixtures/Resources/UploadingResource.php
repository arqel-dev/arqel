<?php

declare(strict_types=1);

namespace Arqel\Fields\Tests\Fixtures\Resources;

use Arqel\Core\Resources\Resource;
use Arqel\Fields\Tests\Fixtures\Models\StubModel;
use Arqel\Fields\Types\FileField;
use Arqel\Fields\Types\ImageField;
use Arqel\Fields\Types\TextField;

/**
 * Fixture used by the upload controller tests. Exposes an
 * upload-capable field (`avatar`), a non-upload field (`name`)
 * so the 400 branch is reachable, two upload fields with an
 * explicit visibility (`public_avatar`, `private_avatar`) used to
 * assert the stored object's ACL matches the field config (#142),
 * plus an `ImageField` with its mime whitelist cleared
 * (`photo_no_mimes`) so the controller's reliance on the field's
 * own `image` rule — not just the `mimetypes` gate — is testable
 * (#166-B).
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
            (new FileField('public_avatar'))->disk('local')->visibility(FileField::VISIBILITY_PUBLIC),
            (new FileField('private_avatar'))->disk('local')->visibility(FileField::VISIBILITY_PRIVATE),
            (new ImageField('photo_no_mimes'))->disk('local')->acceptedFileTypes([]),
        ];
    }
}

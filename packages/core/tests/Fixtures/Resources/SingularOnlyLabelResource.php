<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Resources;

use Arqel\Core\Resources\Resource;
use Arqel\Core\Tests\Fixtures\Models\User;

/**
 * Resource that sets only an explicit singular `$label` (a translation key)
 * and relies on auto-derived plural. Proves the plural is NOT produced by
 * running the English Str::plural() inflector over the already-translated
 * singular (which would corrupt a non-English noun).
 */
final class SingularOnlyLabelResource extends Resource
{
    public static string $model = User::class;

    public static ?string $label = 'app::resources.category';

    public function fields(): array
    {
        return [];
    }
}

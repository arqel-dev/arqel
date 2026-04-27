<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\ResourcesExtras;

use Arqel\Core\Resources\Resource;

/**
 * Intentionally omits $model so we can test the error path on getModel().
 *
 * @phpstan-ignore-next-line We deliberately leave $model unset for tests.
 */
final class MissingModelResource extends Resource
{
    public function fields(): array
    {
        return [];
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Resources;

use Arqel\Core\Resources\Resource;
use Arqel\Core\Tests\Fixtures\Models\Stub;

/**
 * Non-final Resource subclass used by the controller tests so
 * Mockery can produce partial mocks of `runCreate`/`runUpdate`/
 * `runDelete`. The user-facing fixtures (`UserResource`,
 * `PostResource`) stay `final` per Arqel convention.
 */
class MockableResource extends Resource
{
    public static string $model = Stub::class;

    public static ?string $slug = 'mocks';

    public function fields(): array
    {
        return [];
    }
}

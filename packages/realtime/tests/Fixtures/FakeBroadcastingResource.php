<?php

declare(strict_types=1);

namespace Arqel\Realtime\Tests\Fixtures;

use Arqel\Realtime\Concerns\BroadcastsResourceUpdates;
use Illuminate\Database\Eloquent\Model;

/**
 * Stand-in for a user-land `Resource` subclass. We replicate just enough
 * of `Arqel\Core\Resources\Resource`'s lifecycle (a public `runUpdate`
 * that calls the protected `afterUpdate`) to exercise the trait without
 * pulling `arqel-dev/core` into the test runner.
 */
final class FakeBroadcastingResource
{
    use BroadcastsResourceUpdates;

    public static function getSlug(): string
    {
        return 'fake-records';
    }

    public function runUpdate(Model $record): void
    {
        $this->afterUpdate($record);
    }
}

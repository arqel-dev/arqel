<?php

declare(strict_types=1);

use Arqel\Realtime\Events\ResourceUpdated;
use Arqel\Realtime\Tests\Fixtures\FakeBroadcastingResource;
use Arqel\Realtime\Tests\Fixtures\FakeResourceRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

it('dispatches ResourceUpdated when afterUpdate fires', function (): void {
    Event::fake([ResourceUpdated::class]);

    $record = FakeResourceRecord::create(['name' => 'trait']);
    (new FakeBroadcastingResource)->runUpdate($record);

    Event::assertDispatched(
        ResourceUpdated::class,
        fn (ResourceUpdated $event): bool => $event->resourceClass === FakeBroadcastingResource::class
            && $event->record->is($record)
            && $event->updatedByUserId === null,
    );
});

it('skips dispatch when the auto_dispatch config flag is disabled', function (): void {
    config()->set('arqel-realtime.auto_dispatch.resource_updated', false);

    Event::fake([ResourceUpdated::class]);

    $record = FakeResourceRecord::create(['name' => 'silenced']);
    (new FakeBroadcastingResource)->runUpdate($record);

    Event::assertNotDispatched(ResourceUpdated::class);
});

it('captures the authenticated user id when available', function (): void {
    Event::fake([ResourceUpdated::class]);

    $record = FakeResourceRecord::create(['name' => 'auth']);

    Auth::shouldReceive('id')->andReturn(99);

    (new FakeBroadcastingResource)->runUpdate($record);

    Event::assertDispatched(
        ResourceUpdated::class,
        fn (ResourceUpdated $event): bool => $event->updatedByUserId === 99,
    );
});

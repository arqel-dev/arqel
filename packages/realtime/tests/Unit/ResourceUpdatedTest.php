<?php

declare(strict_types=1);

use Arqel\Realtime\Events\ResourceUpdated;
use Arqel\Realtime\Tests\Fixtures\FakePostResource;
use Arqel\Realtime\Tests\Fixtures\FakeResourceRecord;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts on the list and per-record private channels', function (): void {
    $record = FakeResourceRecord::create(['name' => 'Hello']);

    $event = new ResourceUpdated(
        resourceClass: FakePostResource::class,
        record: $record,
        updatedByUserId: 7,
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(2)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-arqel.posts')
        ->and($channels[1])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[1]->name)->toBe("private-arqel.posts.{$record->id}");
});

it('omits the per-record channel when the record has no key yet', function (): void {
    $record = new FakeResourceRecord(['name' => 'unsaved']);

    $event = new ResourceUpdated(
        resourceClass: FakePostResource::class,
        record: $record,
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0]->name)->toBe('private-arqel.posts');
});

it('falls back to a basename-derived slug when getSlug throws', function (): void {
    $record = FakeResourceRecord::create(['name' => 'fallback']);

    $event = new ResourceUpdated(
        resourceClass: BrokenSlugResource::class,
        record: $record,
    );

    $channels = $event->broadcastOn();

    // BrokenSlug -> beforeLast('Resource') -> 'BrokenSlug' -> snake('-') -> 'broken-slug' -> plural
    expect($channels[0]->name)->toBe('private-arqel.broken-slugs');
});

it('exposes id, updatedByUserId, and updatedAt in broadcastWith', function (): void {
    $record = FakeResourceRecord::create(['name' => 'payload']);

    $event = new ResourceUpdated(
        resourceClass: FakePostResource::class,
        record: $record,
        updatedByUserId: 42,
    );

    $payload = $event->broadcastWith();

    expect($payload)->toHaveKeys(['id', 'updatedByUserId', 'updatedAt'])
        ->and($payload['id'])->toBe($record->id)
        ->and($payload['updatedByUserId'])->toBe(42);
});

it('returns null user id and null updatedAt when missing', function (): void {
    $record = new FakeResourceRecord(['name' => 'transient']);

    $event = new ResourceUpdated(
        resourceClass: FakePostResource::class,
        record: $record,
    );

    $payload = $event->broadcastWith();

    expect($payload['id'])->toBeNull()
        ->and($payload['updatedByUserId'])->toBeNull()
        ->and($payload['updatedAt'])->toBeNull();
});

/**
 * Stand-in Resource that throws from `getSlug()` to exercise the
 * defensive try/catch branch in `ResourceUpdated::resolveSlug()`.
 */
final class BrokenSlugResource
{
    public static function getSlug(): string
    {
        throw new RuntimeException('boom');
    }
}

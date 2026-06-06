<?php

declare(strict_types=1);

use Arqel\Core\Resources\Resource;
use Arqel\Realtime\Events\ResourceUpdated;
use Arqel\Realtime\Tests\Fixtures\FakeResourceRecord;
use Arqel\Realtime\Workflow\BroadcastStateTransitionListener;
use Arqel\Workflow\Events\StateTransitioned;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

/**
 * Resource whose custom slug ('articles') diverges from the model-name
 * derivation of {@see FakeResourceRecord} ('fake-resource-records').
 */
final class SlugDivergingResource extends Resource
{
    /** @var class-string<Model> */
    public static string $model = FakeResourceRecord::class;

    public static ?string $slug = 'articles';

    public function fields(): array
    {
        return [];
    }
}

it('registers a listener for StateTransitioned when workflow class exists', function (): void {
    expect(class_exists(StateTransitioned::class))->toBeTrue();
    expect(Event::hasListeners(StateTransitioned::class))->toBeTrue();
});

it('dispatches ResourceUpdated when StateTransitioned fires', function (): void {
    Event::fake([ResourceUpdated::class]);

    $record = FakeResourceRecord::create(['name' => 'paid']);

    $listener = new BroadcastStateTransitionListener;
    $listener->handle(new StateTransitioned(
        record: $record,
        from: 'pending',
        to: 'paid',
        userId: 7,
        context: ['note' => 'manual review'],
    ));

    Event::assertDispatched(
        ResourceUpdated::class,
        fn (ResourceUpdated $event): bool => $event->resourceClass === FakeResourceRecord::class
            && $event->record->is($record)
            && $event->updatedByUserId === 7,
    );
});

it('skips dispatch when broadcast_state_transitions flag is false', function (): void {
    config()->set('arqel-realtime.workflow.broadcast_state_transitions', false);

    Event::fake([ResourceUpdated::class]);

    $record = FakeResourceRecord::create(['name' => 'silenced']);

    (new BroadcastStateTransitionListener)->handle(new StateTransitioned(
        record: $record,
        from: 'pending',
        to: 'paid',
    ));

    Event::assertNotDispatched(ResourceUpdated::class);
});

it('skips dispatch when event payload is not a StateTransitioned instance', function (): void {
    Event::fake([ResourceUpdated::class]);

    (new BroadcastStateTransitionListener)->handle(new stdClass);
    (new BroadcastStateTransitionListener)->handle('not-an-event');
    (new BroadcastStateTransitionListener)->handle(null);

    Event::assertNotDispatched(ResourceUpdated::class);
});

it('produces the expected slug-based channels via ResourceUpdated', function (): void {
    $record = FakeResourceRecord::create(['name' => 'broadcasted']);

    $event = new ResourceUpdated(FakeResourceRecord::class, $record, 1);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(2);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);

    $names = array_map(fn (PrivateChannel $c): string => $c->name, $channels);
    expect($names[0])->toStartWith('private-arqel.');
    /** @var int|string $key */
    $key = $record->getKey();
    expect($names[1])->toContain((string) $key);
});

it('dispatches ResourceUpdated with the registered Resource slug, not the model basename', function (): void {
    // Resource whose slug ('articles') diverges from the model-basename
    // derivation ('fake-resource-records'). Without the fix the listener
    // passes the model FQCN and ResourceUpdated falls back to the basename.
    app()->singleton('Arqel\\Core\\Resources\\ResourceRegistry');
    app('Arqel\\Core\\Resources\\ResourceRegistry')->register(SlugDivergingResource::class);

    Event::fake([ResourceUpdated::class]);

    $record = FakeResourceRecord::create(['name' => 'slugged']);

    (new BroadcastStateTransitionListener)->handle(new StateTransitioned(
        record: $record,
        from: 'draft',
        to: 'published',
        userId: 3,
    ));

    Event::assertDispatched(
        ResourceUpdated::class,
        function (ResourceUpdated $event): bool {
            // First arg must be the Resource class so resolveSlug() reads
            // the registered custom slug.
            expect($event->resourceClass)->toBe(SlugDivergingResource::class);

            $names = array_map(
                fn (PrivateChannel $c): string => $c->name,
                $event->broadcastOn(),
            );

            expect($names[0])->toBe('private-arqel.articles');

            return true;
        },
    );
});

it('forwards the user id from StateTransitioned to ResourceUpdated', function (): void {
    Event::fake([ResourceUpdated::class]);

    $record = FakeResourceRecord::create(['name' => 'audit']);

    (new BroadcastStateTransitionListener)->handle(new StateTransitioned(
        record: $record,
        from: 'a',
        to: 'b',
        userId: null,
    ));

    Event::assertDispatched(
        ResourceUpdated::class,
        fn (ResourceUpdated $event): bool => $event->updatedByUserId === null,
    );
});

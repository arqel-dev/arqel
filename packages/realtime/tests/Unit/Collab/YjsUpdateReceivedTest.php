<?php

declare(strict_types=1);

use Arqel\Realtime\Events\YjsUpdateReceived;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Queue\SerializesModels;

it('broadcastOn returns a single PrivateChannel with the correct name', function (): void {
    $event = new YjsUpdateReceived(
        modelType: 'orders',
        modelId: 'abc-123',
        field: 'notes',
        stateBase64: 'AQ==',
        version: 2,
        userId: 5,
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-arqel.collab.orders.abc-123.notes');
});

it('broadcastWith includes state, version and by_user_id', function (): void {
    $event = new YjsUpdateReceived(
        modelType: 'posts',
        modelId: 1,
        field: 'body',
        stateBase64: 'AQIDBAU=',
        version: 4,
        userId: null,
    );

    expect($event->broadcastWith())->toMatchArray([
        'state' => 'AQIDBAU=',
        'version' => 4,
        'by_user_id' => null,
    ]);
});

it('uses SerializesModels trait without breaking on non-model payload', function (): void {
    $event = new YjsUpdateReceived(
        modelType: 'posts',
        modelId: 1,
        field: 'body',
        stateBase64: 'AQ==',
        version: 1,
        userId: 1,
    );

    $traits = class_uses($event);

    expect($traits)->toContain(SerializesModels::class);

    // Round-trip via serialize() to ensure SerializesModels does not
    // explode on the scalar-only payload.
    $serialized = serialize($event);
    /** @var YjsUpdateReceived $hydrated */
    $hydrated = unserialize($serialized);

    expect($hydrated->modelType)->toBe('posts')
        ->and($hydrated->version)->toBe(1);
});

it('preserves modelId verbatim (supports string ids like UUIDs)', function (): void {
    $event = new YjsUpdateReceived(
        modelType: 'orders',
        modelId: '550e8400-e29b-41d4-a716-446655440000',
        field: 'body',
        stateBase64: 'AQ==',
        version: 1,
        userId: 1,
    );

    expect($event->broadcastOn()[0]->name)
        ->toBe('private-arqel.collab.orders.550e8400-e29b-41d4-a716-446655440000.body');
});

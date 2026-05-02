<?php

declare(strict_types=1);

use Arqel\Realtime\Collab\YjsDocument;
use Arqel\Realtime\Events\YjsUpdateReceived;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Event;

function authedYjsBroadcastUser(): AuthUser
{
    $user = new AuthUser;
    $user->forceFill(['id' => 7, 'name' => 'Editor', 'email' => 'e@x.dev']);

    return $user;
}

it('dispatches YjsUpdateReceived after creating a new collab document', function (): void {
    Event::fake([YjsUpdateReceived::class]);

    /** @var Arqel\Realtime\Tests\TestCase $this */
    $response = $this->actingAs(authedYjsBroadcastUser())
        ->postJson('/admin/posts/42/collab/body', [
            'state' => base64_encode('hello'),
            'version' => 0,
        ]);

    $response->assertStatus(201);

    Event::assertDispatched(YjsUpdateReceived::class, static function (YjsUpdateReceived $event): bool {
        return $event->modelType === 'posts'
            && (int) $event->modelId === 42
            && $event->field === 'body'
            && $event->version === 1;
    });
});

it('dispatches YjsUpdateReceived after updating an existing collab document', function (): void {
    YjsDocument::query()->create([
        'model_type' => 'posts',
        'model_id' => 42,
        'field' => 'body',
        'state' => 'old',
        'version' => 5,
        'last_user_id' => 1,
        'updated_at' => now(),
    ]);

    Event::fake([YjsUpdateReceived::class]);

    /** @var Arqel\Realtime\Tests\TestCase $this */
    $response = $this->actingAs(authedYjsBroadcastUser())
        ->postJson('/admin/posts/42/collab/body', [
            'state' => base64_encode('new'),
            'version' => 5,
        ]);

    $response->assertOk();

    Event::assertDispatched(YjsUpdateReceived::class, static function (YjsUpdateReceived $event): bool {
        return $event->version === 6
            && $event->stateBase64 === base64_encode('new')
            && $event->userId === 7;
    });
});

it('uses channel name arqel.collab.{modelType}.{modelId}.{field}', function (): void {
    $event = new YjsUpdateReceived(
        modelType: 'posts',
        modelId: 42,
        field: 'body',
        stateBase64: 'AQID',
        version: 1,
        userId: 7,
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0]->name)->toBe('private-arqel.collab.posts.42.body');
});

it('uses broadcastAs collab.update', function (): void {
    $event = new YjsUpdateReceived(
        modelType: 'posts',
        modelId: 1,
        field: 'body',
        stateBase64: 'AQ==',
        version: 1,
        userId: null,
    );

    expect($event->broadcastAs())->toBe('collab.update');
});

it('returns the expected broadcastWith payload shape', function (): void {
    $event = new YjsUpdateReceived(
        modelType: 'posts',
        modelId: 42,
        field: 'body',
        stateBase64: 'AQID',
        version: 9,
        userId: 7,
    );

    expect($event->broadcastWith())->toBe([
        'state' => 'AQID',
        'version' => 9,
        'by_user_id' => 7,
    ]);
});

it('does not dispatch the event on version conflict (409)', function (): void {
    YjsDocument::query()->create([
        'model_type' => 'posts',
        'model_id' => 42,
        'field' => 'body',
        'state' => 'current',
        'version' => 10,
        'last_user_id' => 1,
        'updated_at' => now(),
    ]);

    Event::fake([YjsUpdateReceived::class]);

    /** @var Arqel\Realtime\Tests\TestCase $this */
    $response = $this->actingAs(authedYjsBroadcastUser())
        ->postJson('/admin/posts/42/collab/body', [
            'state' => base64_encode('stale'),
            'version' => 3,
        ]);

    $response->assertStatus(409);

    Event::assertNotDispatched(YjsUpdateReceived::class);
});

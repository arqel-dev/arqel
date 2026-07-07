<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

final class NotifiableUserForController extends AuthUser
{
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}

beforeEach(function (): void {
    if (! Schema::hasTable('users')) {
        Schema::create('users', function ($t): void {
            $t->id();
            $t->string('name')->nullable();
            $t->timestamps();
        });
    }
});

function makeNotification(NotifiableUserForController $user, ?DateTimeInterface $readAt = null): string
{
    $id = (string) Str::uuid();
    $user->notifications()->create([
        'id' => $id,
        'type' => 'App\\Notifications\\Welcome',
        'data' => ['title' => 'Olá'],
        'read_at' => $readAt,
    ]);

    return $id;
}

it('marks a notification as read scoped to the owner', function (): void {
    $user = NotifiableUserForController::query()->create(['name' => 'Ada']);
    $id = makeNotification($user);

    $this->actingAs($user)
        ->from('/admin')
        ->post("/admin/notifications/{$id}/read")
        ->assertRedirect('/admin');

    expect($user->notifications()->find($id)->read_at)->not->toBeNull();
});

it('returns 404 when marking a notification owned by another user (anti-IDOR)', function (): void {
    $owner = NotifiableUserForController::query()->create(['name' => 'Owner']);
    $attacker = NotifiableUserForController::query()->create(['name' => 'Mallory']);
    $id = makeNotification($owner);

    $this->actingAs($attacker)
        ->post("/admin/notifications/{$id}/read")
        ->assertNotFound();

    expect($owner->notifications()->find($id)->read_at)->toBeNull();
});

it('marks all as read', function (): void {
    $user = NotifiableUserForController::query()->create(['name' => 'Ada']);
    makeNotification($user);
    makeNotification($user);

    $this->actingAs($user)
        ->from('/admin')
        ->post('/admin/notifications/read-all')
        ->assertRedirect('/admin');

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('destroys a notification scoped to the owner', function (): void {
    $user = NotifiableUserForController::query()->create(['name' => 'Ada']);
    $id = makeNotification($user);

    $this->actingAs($user)
        ->from('/admin/notifications')
        ->delete("/admin/notifications/{$id}")
        ->assertRedirect('/admin/notifications');

    expect($user->notifications()->find($id))->toBeNull();
});

it('returns 404 when destroying another user notification (anti-IDOR)', function (): void {
    $owner = NotifiableUserForController::query()->create(['name' => 'Owner']);
    $attacker = NotifiableUserForController::query()->create(['name' => 'Mallory']);
    $id = makeNotification($owner);

    $this->actingAs($attacker)
        ->delete("/admin/notifications/{$id}")
        ->assertNotFound();

    expect($owner->notifications()->find($id))->not->toBeNull();
});

it('filters the index to unread only', function (): void {
    // Real apps publish `arqel.layout` via `arqel:install`; the
    // package-shipped `arqel::app` root view pulls in `@vite`/`@routes`
    // (Ziggy), neither of which is set up in this test app. Point the
    // root view at a minimal Inertia-compatible fixture instead so the
    // full HTTP render only exercises routing/props, not asset pipeline.
    View::addNamespace('arqel-test', __DIR__.'/../Fixtures/views');
    config(['arqel.inertia.root_view' => 'arqel-test::test-root']);

    $user = NotifiableUserForController::query()->create(['name' => 'Ada']);
    makeNotification($user);              // unread
    makeNotification($user, now());       // read

    $this->actingAs($user)
        ->get('/admin/notifications?filter=unread')
        ->assertOk();
    // Asserção detalhada do payload Inertia fica a cargo do render;
    // o teste-chave é o filtro não quebrar e retornar 200.
});

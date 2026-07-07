<?php

declare(strict_types=1);

use Arqel\Core\Http\Middleware\HandleArqelInertiaRequests;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

/** User Notifiable de teste, tabela in-memory. */
final class NotifiableUserForShare extends AuthUser
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

it('emits null notifications when there is no authenticated user', function (): void {
    $mw = new HandleArqelInertiaRequests;
    $ref = new ReflectionMethod($mw, 'notificationsPayload');
    $ref->setAccessible(true);

    expect($ref->invoke($mw, null))->toBeNull();
});

it('emits unread_count and recent items for an authenticated user', function (): void {
    $user = NotifiableUserForShare::query()->create(['name' => 'Ada']);
    // Duas notificações: uma não-lida, uma lida. Timestamps explícitos e
    // distintos (o teste pode rodar mais rápido que a resolução de
    // segundo do `now()`, o que empataria o `latest()`).
    $user->notifications()->create([
        'id' => (string) Illuminate\Support\Str::uuid(),
        'type' => 'App\\Notifications\\Welcome',
        'data' => ['title' => 'Bem-vinda'],
        'read_at' => null,
        'created_at' => now()->subMinute(),
    ]);
    $user->notifications()->create([
        'id' => (string) Illuminate\Support\Str::uuid(),
        'type' => 'App\\Notifications\\Old',
        'data' => ['title' => 'Antiga'],
        'read_at' => now(),
        'created_at' => now(),
    ]);

    $mw = new HandleArqelInertiaRequests;
    $ref = new ReflectionMethod($mw, 'notificationsPayload');
    $ref->setAccessible(true);

    $payload = $ref->invoke($mw, $user);

    expect($payload['unread_count'])->toBe(1)
        ->and($payload['recent'])->toHaveCount(2)
        ->and($payload['recent'][0])->toHaveKeys(['id', 'type', 'data', 'read_at', 'created_at'])
        ->and($payload['recent'][0]['type'])->toBe('Old'); // class_basename, latest first
});

it('emits null (does not throw) when the notifications table is missing', function (): void {
    // Um app consumidor que ainda não publicou/rodou a migration não tem a
    // tabela. A prop roda em toda request autenticada, então precisa degradar
    // em silêncio em vez de derrubar o painel com "relation does not exist".
    $user = NotifiableUserForShare::query()->create(['name' => 'Ada']);

    Schema::dropIfExists('notifications');

    $mw = new HandleArqelInertiaRequests;
    $ref = new ReflectionMethod($mw, 'notificationsPayload');
    $ref->setAccessible(true);

    expect($ref->invoke($mw, $user))->toBeNull();
});

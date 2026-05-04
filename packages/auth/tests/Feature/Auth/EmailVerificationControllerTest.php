<?php

declare(strict_types=1);

use Arqel\Auth\Routes;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

class VerifyUser extends Authenticatable implements MustVerifyEmail
{
    use Illuminate\Auth\MustVerifyEmail;
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function (): void {
    Routes::reset();

    Schema::dropIfExists('users');
    Schema::create('users', function ($table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->rememberToken()->nullable();
    });

    config()->set('auth.providers.users.driver', 'eloquent');
    config()->set('auth.providers.users.model', VerifyUser::class);

    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login()->emailVerification();
    $registry->setCurrent('admin');

    Routes::register($panel);
});

it('renders the Inertia notice page when unverified', function (): void {
    $user = VerifyUser::create([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => Hash::make('secret-pass-123'),
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->get('/admin/email/verify');

    $response->assertOk();
    $payload = json_decode($response->getContent() ?: '', true);
    expect($payload['component'] ?? null)->toBe('arqel-dev/auth/VerifyEmailNotice');
});

it('marks email as verified and dispatches Verified event with valid signed URL', function (): void {
    Event::fake([Verified::class]);

    $user = VerifyUser::create([
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'password' => Hash::make('secret-pass-123'),
    ]);

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->getKey(), 'hash' => sha1((string) $user->getEmailForVerification())],
    );

    $response = $this->actingAs($user)->get($url);

    $response->assertRedirect();
    expect($user->fresh()?->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
});

it('returns 403 with invalid hash on verify route', function (): void {
    $user = VerifyUser::create([
        'name' => 'Carol',
        'email' => 'carol@example.com',
        'password' => Hash::make('secret-pass-123'),
    ]);

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->getKey(), 'hash' => 'invalid-hash'],
    );

    $response = $this->actingAs($user)->get($url);

    $response->assertForbidden();
    expect($user->fresh()?->hasVerifiedEmail())->toBeFalse();
});

it('resends verification notification when user is not yet verified', function (): void {
    Notification::fake();

    $user = VerifyUser::create([
        'name' => 'Dave',
        'email' => 'dave@example.com',
        'password' => Hash::make('secret-pass-123'),
    ]);

    $response = $this->actingAs($user)
        ->from('/admin/email/verify')
        ->post('/admin/email/verify/resend');

    $response->assertRedirect('/admin/email/verify');
    Notification::assertSentTo($user, Illuminate\Auth\Notifications\VerifyEmail::class);
});

it('skips resending when user is already verified', function (): void {
    Notification::fake();

    $user = VerifyUser::create([
        'name' => 'Erin',
        'email' => 'erin@example.com',
        'email_verified_at' => now(),
        'password' => Hash::make('secret-pass-123'),
    ]);

    $response = $this->actingAs($user)->post('/admin/email/verify/resend');

    $response->assertRedirect();
    Notification::assertNothingSent();
});

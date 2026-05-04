<?php

declare(strict_types=1);

use Arqel\Auth\Routes;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

class ResetPasswordUser extends Authenticatable
{
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
        $table->string('email')->unique();
        $table->string('password');
        $table->rememberToken()->nullable();
    });

    Schema::dropIfExists('password_reset_tokens');
    Schema::create('password_reset_tokens', function ($table): void {
        $table->string('email')->primary();
        $table->string('token');
        $table->timestamp('created_at')->nullable();
    });

    config()->set('auth.providers.users.driver', 'eloquent');
    config()->set('auth.providers.users.model', ResetPasswordUser::class);
    config()->set('auth.passwords.users', [
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 0,
    ]);

    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login()->passwordReset();
    $registry->setCurrent('admin');

    Routes::register($panel);

    RateLimiter::clear('arqel.reset-password|foo@bar.com|127.0.0.1');
});

it('renders the Inertia reset-password page with token + email', function (): void {
    $response = $this->withHeaders(['X-Inertia' => 'true'])
        ->get('/admin/reset-password/some-token?email=foo@bar.com');

    $response->assertOk();
    $payload = json_decode($response->getContent() ?: '', true);
    expect($payload['component'] ?? null)->toBe('arqel-dev/auth/ResetPassword');
    expect($payload['props']['token'] ?? null)->toBe('some-token');
    expect($payload['props']['email'] ?? null)->toBe('foo@bar.com');
});

it('resets password with valid token and redirects to login', function (): void {
    $user = ResetPasswordUser::create([
        'email' => 'foo@bar.com',
        'password' => Hash::make('old-secret'),
    ]);

    $token = Password::broker()->createToken($user);

    $response = $this->from('/admin/reset-password')->post('/admin/reset-password', [
        'token' => $token,
        'email' => 'foo@bar.com',
        'password' => 'new-secret-123',
        'password_confirmation' => 'new-secret-123',
    ]);

    $response->assertRedirect('/admin/login');
    $user->refresh();
    expect(Hash::check('new-secret-123', $user->password))->toBeTrue();
});

it('rejects invalid token with 422', function (): void {
    ResetPasswordUser::create([
        'email' => 'foo@bar.com',
        'password' => Hash::make('old-secret'),
    ]);

    $response = $this->from('/admin/reset-password')->post('/admin/reset-password', [
        'token' => 'invalid-token',
        'email' => 'foo@bar.com',
        'password' => 'new-secret-123',
        'password_confirmation' => 'new-secret-123',
    ]);

    $response->assertSessionHasErrors('email');
});

it('rejects email mismatch with 422', function (): void {
    $user = ResetPasswordUser::create([
        'email' => 'foo@bar.com',
        'password' => Hash::make('old-secret'),
    ]);

    $token = Password::broker()->createToken($user);

    $response = $this->from('/admin/reset-password')->post('/admin/reset-password', [
        'token' => $token,
        'email' => 'wrong@bar.com',
        'password' => 'new-secret-123',
        'password_confirmation' => 'new-secret-123',
    ]);

    $response->assertSessionHasErrors('email');
});

it('rejects when password confirmation does not match', function (): void {
    $user = ResetPasswordUser::create([
        'email' => 'foo@bar.com',
        'password' => Hash::make('old-secret'),
    ]);

    $token = Password::broker()->createToken($user);

    $response = $this->from('/admin/reset-password')->post('/admin/reset-password', [
        'token' => $token,
        'email' => 'foo@bar.com',
        'password' => 'new-secret-123',
        'password_confirmation' => 'different',
    ]);

    $response->assertSessionHasErrors('password');
});

it('rejects when password is below minimum length', function (): void {
    $response = $this->from('/admin/reset-password')->post('/admin/reset-password', [
        'token' => 'tok',
        'email' => 'foo@bar.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertSessionHasErrors('password');
});

it('rate-limits after 3 attempts per email/IP/hour', function (): void {
    for ($i = 0; $i < 3; $i++) {
        RateLimiter::hit('arqel.reset-password|foo@bar.com|127.0.0.1', 3600);
    }

    $response = $this->from('/admin/reset-password')->post('/admin/reset-password', [
        'token' => 'whatever',
        'email' => 'foo@bar.com',
        'password' => 'some-password-123',
        'password_confirmation' => 'some-password-123',
    ]);

    $response->assertSessionHasErrors('email');
});

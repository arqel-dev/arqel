<?php

declare(strict_types=1);

use Arqel\Auth\Routes;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

class ForgotPasswordUser extends Authenticatable
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
    config()->set('auth.providers.users.model', ForgotPasswordUser::class);
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

    RateLimiter::clear('arqel.forgot-password|foo@bar.com|127.0.0.1');
});

it('renders the Inertia forgot-password page on GET', function (): void {
    $response = $this->withHeaders(['X-Inertia' => 'true'])->get('/admin/forgot-password');

    $response->assertOk();
    $payload = json_decode($response->getContent() ?: '', true);
    expect($payload['component'] ?? null)->toBe('arqel-dev/auth/ForgotPassword');
});

it('sends reset link notification for an existing email', function (): void {
    Notification::fake();

    ForgotPasswordUser::create([
        'email' => 'foo@bar.com',
        'password' => bcrypt('secret123'),
    ]);

    $response = $this->from('/admin/forgot-password')->post('/admin/forgot-password', [
        'email' => 'foo@bar.com',
    ]);

    $response->assertRedirect('/admin/forgot-password');
    $response->assertSessionHas('status');

    Notification::assertSentTo(
        ForgotPasswordUser::where('email', 'foo@bar.com')->first(),
        ResetPassword::class,
    );
});

it('returns generic status without revealing whether email exists', function (): void {
    Notification::fake();

    $response = $this->from('/admin/forgot-password')->post('/admin/forgot-password', [
        'email' => 'unknown@bar.com',
    ]);

    $response->assertRedirect('/admin/forgot-password');
    $response->assertSessionHas('status');

    Notification::assertNothingSent();
});

it('rate-limits after 3 attempts per email/IP/hour', function (): void {
    for ($i = 0; $i < 3; $i++) {
        RateLimiter::hit('arqel.forgot-password|foo@bar.com|127.0.0.1', 3600);
    }

    $response = $this->from('/admin/forgot-password')->post('/admin/forgot-password', [
        'email' => 'foo@bar.com',
    ]);

    $response->assertSessionHasErrors('email');
});

it('validates that email is required', function (): void {
    $response = $this->from('/admin/forgot-password')->post('/admin/forgot-password', []);

    $response->assertSessionHasErrors('email');
});

it('validates that email format is correct', function (): void {
    $response = $this->from('/admin/forgot-password')->post('/admin/forgot-password', [
        'email' => 'not-an-email',
    ]);

    $response->assertSessionHasErrors('email');
});

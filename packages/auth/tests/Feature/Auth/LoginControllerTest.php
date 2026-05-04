<?php

declare(strict_types=1);

use Arqel\Auth\Routes;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

class LoginUser extends Authenticatable
{
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

    config()->set('auth.providers.users.driver', 'eloquent');
    config()->set('auth.providers.users.model', LoginUser::class);

    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login();
    $registry->setCurrent('admin');

    Routes::register($panel);

    RateLimiter::clear('foo@bar.com|127.0.0.1');
});

it('renders the Inertia login page on GET /admin/login', function (): void {
    $response = $this->withHeaders(['X-Inertia' => 'true'])->get('/admin/login');

    $response->assertOk();
    expect($response->headers->get('X-Inertia'))->toBe('true');
    $payload = json_decode($response->getContent() ?: '', true);
    expect($payload['component'] ?? null)->toBe('arqel-dev/auth/Login');
});

it('authenticates with valid credentials and redirects', function (): void {
    LoginUser::create([
        'email' => 'foo@bar.com',
        'password' => Hash::make('secret123'),
    ]);

    $response = $this->post('/admin/login', [
        'email' => 'foo@bar.com',
        'password' => 'secret123',
    ]);

    $response->assertRedirect('/admin');
    expect(Auth::check())->toBeTrue();
});

it('returns 422 with validation error on invalid credentials', function (): void {
    LoginUser::create([
        'email' => 'foo@bar.com',
        'password' => Hash::make('secret123'),
    ]);

    $response = $this->from('/admin/login')->post('/admin/login', [
        'email' => 'foo@bar.com',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    expect(Auth::check())->toBeFalse();
});

it('rate-limits after 5 failed attempts', function (): void {
    LoginUser::create([
        'email' => 'foo@bar.com',
        'password' => Hash::make('secret123'),
    ]);

    // Hit the throttle key directly to simulate 5 prior failed attempts.
    for ($i = 0; $i < 5; $i++) {
        RateLimiter::hit('foo@bar.com|127.0.0.1');
    }

    $response = $this->from('/admin/login')->post('/admin/login', [
        'email' => 'foo@bar.com',
        'password' => 'secret123',
    ]);

    // LoginRequest throttle (5/min/email+ip) returns 422 with a throttled message.
    $response->assertSessionHasErrors('email');
    expect(Auth::check())->toBeFalse();
});

it('logs out, invalidates session and redirects to login', function (): void {
    $user = LoginUser::create([
        'email' => 'foo@bar.com',
        'password' => Hash::make('secret123'),
    ]);

    $this->actingAs($user);
    expect(Auth::check())->toBeTrue();

    $response = $this->post('/admin/logout');

    $response->assertRedirect('/admin/login');
    expect(Auth::check())->toBeFalse();
});

it('redirects to panel afterLoginRedirectTo on success', function (): void {
    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login()->afterLoginRedirectTo('/admin/dashboard');
    $registry->setCurrent('admin');

    LoginUser::create([
        'email' => 'foo@bar.com',
        'password' => Hash::make('secret123'),
    ]);

    $response = $this->post('/admin/login', [
        'email' => 'foo@bar.com',
        'password' => 'secret123',
    ]);

    $response->assertRedirect('/admin/dashboard');
});

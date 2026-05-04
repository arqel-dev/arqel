<?php

declare(strict_types=1);

use Arqel\Auth\Routes;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

class RegisterUser extends Authenticatable
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
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->rememberToken()->nullable();
    });

    config()->set('auth.providers.users.driver', 'eloquent');
    config()->set('auth.providers.users.model', RegisterUser::class);

    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login()->registration();
    $registry->setCurrent('admin');

    Routes::register($panel);

    RateLimiter::clear('arqel-register|127.0.0.1');
});

it('renders the Inertia register page on GET /admin/register', function (): void {
    $response = $this->withHeaders(['X-Inertia' => 'true'])->get('/admin/register');

    $response->assertOk();
    $payload = json_decode($response->getContent() ?: '', true);
    expect($payload['component'] ?? null)->toBe('arqel-dev/auth/Register');
});

it('creates user, dispatches Registered event, auto-logs in and redirects', function (): void {
    Event::fake([Registered::class]);

    $response = $this->post('/admin/register', [
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => 'secret-pass-123',
        'password_confirmation' => 'secret-pass-123',
    ]);

    $response->assertRedirect('/admin');
    expect(Auth::check())->toBeTrue();
    expect(RegisterUser::where('email', 'alice@example.com')->exists())->toBeTrue();

    $user = RegisterUser::where('email', 'alice@example.com')->first();
    expect($user)->not->toBeNull();
    expect(Hash::check('secret-pass-123', (string) $user->password))->toBeTrue();

    Event::assertDispatched(Registered::class);
});

it('returns 422 when email is already taken', function (): void {
    RegisterUser::create([
        'name' => 'Existing',
        'email' => 'existing@example.com',
        'password' => Hash::make('whatever'),
    ]);

    $response = $this->from('/admin/register')->post('/admin/register', [
        'name' => 'New',
        'email' => 'existing@example.com',
        'password' => 'secret-pass-123',
        'password_confirmation' => 'secret-pass-123',
    ]);

    $response->assertSessionHasErrors('email');
    expect(Auth::check())->toBeFalse();
});

it('returns 422 when password confirmation does not match', function (): void {
    $response = $this->from('/admin/register')->post('/admin/register', [
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'password' => 'secret-pass-123',
        'password_confirmation' => 'mismatch-456',
    ]);

    $response->assertSessionHasErrors('password');
    expect(Auth::check())->toBeFalse();
    expect(RegisterUser::where('email', 'bob@example.com')->exists())->toBeFalse();
});

it('rate-limits after 3 successful attempts from same IP', function (): void {
    for ($i = 0; $i < 3; $i++) {
        RateLimiter::hit('arqel-register|127.0.0.1', 3600);
    }

    $response = $this->from('/admin/register')->post('/admin/register', [
        'name' => 'Carol',
        'email' => 'carol@example.com',
        'password' => 'secret-pass-123',
        'password_confirmation' => 'secret-pass-123',
    ]);

    $response->assertSessionHasErrors('email');
    expect(RegisterUser::where('email', 'carol@example.com')->exists())->toBeFalse();
});

it('resolves user model from auth.providers.users.model config', function (): void {
    expect((string) config('auth.providers.users.model'))->toBe(RegisterUser::class);

    $response = $this->post('/admin/register', [
        'name' => 'Dave',
        'email' => 'dave@example.com',
        'password' => 'secret-pass-123',
        'password_confirmation' => 'secret-pass-123',
    ]);

    $response->assertRedirect();
    $user = RegisterUser::where('email', 'dave@example.com')->first();
    expect($user)->toBeInstanceOf(RegisterUser::class);
});

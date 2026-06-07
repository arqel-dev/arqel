<?php

declare(strict_types=1);

use Arqel\Auth\Http\Middleware\EnsureUserCanAccessPanel;
use Arqel\Auth\Routes;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Regression coverage for #139 — Panel::authGuard() was dead config.
 *
 * A panel configured with ->authGuard('admin') must authenticate,
 * register, log out and gate against the `admin` guard, never the
 * default `web` guard.
 */
class GuardUser extends Authenticatable
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
        $table->string('name')->nullable();
        $table->string('email')->unique();
        $table->string('password');
        $table->rememberToken()->nullable();
    });

    // A second, non-default guard `admin` backed by its own provider.
    config()->set('auth.providers.users.driver', 'eloquent');
    config()->set('auth.providers.users.model', GuardUser::class);
    config()->set('auth.providers.admins', [
        'driver' => 'eloquent',
        'model' => GuardUser::class,
    ]);
    config()->set('auth.guards.admin', [
        'driver' => 'session',
        'provider' => 'admins',
    ]);

    RateLimiter::clear('foo@bar.com|127.0.0.1');
});

function registerPanelWithGuard(string $guard): void
{
    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login()->registration()->authGuard($guard);
    $registry->setCurrent('admin');

    Routes::register($panel);
}

it('logs in against the panel guard, not the default web guard', function (): void {
    registerPanelWithGuard('admin');

    GuardUser::create([
        'email' => 'foo@bar.com',
        'password' => Hash::make('secret123'),
    ]);

    $response = $this->post('/admin/login', [
        'email' => 'foo@bar.com',
        'password' => 'secret123',
    ]);

    $response->assertRedirect('/admin');
    expect(Auth::guard('admin')->check())->toBeTrue();
    expect(Auth::guard('web')->check())->toBeFalse();
});

it('logs out the panel guard', function (): void {
    registerPanelWithGuard('admin');

    $user = GuardUser::create([
        'email' => 'foo@bar.com',
        'password' => Hash::make('secret123'),
    ]);

    $this->actingAs($user, 'admin');
    expect(Auth::guard('admin')->check())->toBeTrue();

    $response = $this->post('/admin/logout');

    $response->assertRedirect('/admin/login');
    expect(Auth::guard('admin')->check())->toBeFalse();
});

it('registers a new user against the panel guard', function (): void {
    registerPanelWithGuard('admin');

    $response = $this->post('/admin/register', [
        'name' => 'Foo Bar',
        'email' => 'foo@bar.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertRedirect();
    expect(Auth::guard('admin')->check())->toBeTrue();
    expect(Auth::guard('web')->check())->toBeFalse();
});

it('gates the panel using the configured guard user', function (): void {
    config()->set('arqel.auth.guard', 'admin');

    $user = new GuardUser(['id' => 1]);
    $request = Request::create('/admin');
    $request->setUserResolver(function (?string $guard = null) use ($user) {
        // Only the `admin` guard resolves a user; the default guard is empty.
        return $guard === 'admin' ? $user : null;
    });

    $result = (new EnsureUserCanAccessPanel)->handle($request, fn ($req) => 'pass');

    expect($result)->toBe('pass');
});

it('aborts 401 when the configured guard has no user even if another guard does', function (): void {
    config()->set('arqel.auth.guard', 'admin');

    $request = Request::create('/admin');
    $request->setUserResolver(function (?string $guard = null) {
        // The default (web) guard has a user, but the admin guard does not.
        return $guard === null ? new GuardUser(['id' => 1]) : null;
    });

    (new EnsureUserCanAccessPanel)->handle($request, fn ($req) => 'pass');
})->throws(HttpException::class);

it('emits guard-scoped auth/guest route middleware', function (): void {
    registerPanelWithGuard('admin');
    Route::getRoutes()->refreshNameLookups();

    $login = Route::getRoutes()->getByName('login');
    $logout = Route::getRoutes()->getByName('logout');

    expect($login?->gatherMiddleware() ?? [])->toContain('guest:admin');
    expect($logout?->gatherMiddleware() ?? [])->toContain('auth:admin');
});

it('keeps the default web guard for a panel without authGuard (no regression)', function (): void {
    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login();
    $registry->setCurrent('admin');
    Routes::register($panel);

    GuardUser::create([
        'email' => 'foo@bar.com',
        'password' => Hash::make('secret123'),
    ]);

    $response = $this->post('/admin/login', [
        'email' => 'foo@bar.com',
        'password' => 'secret123',
    ]);

    $response->assertRedirect('/admin');
    expect(Auth::guard('web')->check())->toBeTrue();

    Route::getRoutes()->refreshNameLookups();
    $login = Route::getRoutes()->getByName('login');
    expect($login?->gatherMiddleware() ?? [])->toContain('guest:web');
});

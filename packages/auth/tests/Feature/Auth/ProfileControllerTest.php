<?php

declare(strict_types=1);

use Arqel\Auth\Routes;
use Arqel\Core\Panel\Panel;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class ProfileUser extends Authenticatable
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
    config()->set('auth.providers.users.model', ProfileUser::class);

    $registry = app(PanelRegistry::class);
    $registry->clear();

    // NOTE: profile routes are NOT registered here (see Change C). Each
    // route-exercising test calls registerProfileRoutes() itself, so the
    // gating test below can assert against a genuinely clean Router.
});

/**
 * Registers an admin panel WITH profile() enabled and wires the bundled
 * routes. Called explicitly by every test that exercises a profile route.
 */
function registerProfileRoutes(): Panel
{
    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login()->profile();
    $registry->setCurrent('admin');
    Routes::register($panel);

    return $panel;
}

function makeProfileUser(): ProfileUser
{
    return ProfileUser::create([
        'name' => 'Original',
        'email' => 'original@example.com',
        'password' => Hash::make('secret-password'),
    ]);
}

it('renders the Inertia profile page on GET /admin/profile', function (): void {
    registerProfileRoutes();
    $user = makeProfileUser();

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->get('/admin/profile');

    $response->assertOk();
    $payload = json_decode($response->getContent() ?: '', true);
    expect($payload['component'] ?? null)->toBe('arqel-dev/auth/Profile');
    expect($payload['props']['user']['email'] ?? null)->toBe('original@example.com');
});

it('redirects a guest away from GET /admin/profile', function (): void {
    registerProfileRoutes();

    $response = $this->get('/admin/profile');
    expect($response->getStatusCode())->toBe(302);
});

it('updates name and email', function (): void {
    registerProfileRoutes();
    $user = makeProfileUser();

    $response = $this->actingAs($user)->put('/admin/profile', [
        'name' => 'Renamed',
        'email' => 'renamed@example.com',
    ]);

    $response->assertRedirect();
    $user->refresh();
    expect($user->name)->toBe('Renamed');
    expect($user->email)->toBe('renamed@example.com');
});

it('rejects a blank name', function (): void {
    registerProfileRoutes();
    $user = makeProfileUser();

    $response = $this->actingAs($user)->put('/admin/profile', [
        'name' => '',
        'email' => 'renamed@example.com',
    ]);

    $response->assertSessionHasErrors('name');
});

it('rejects a duplicate email', function (): void {
    registerProfileRoutes();
    $user = makeProfileUser();
    ProfileUser::create([
        'name' => 'Other',
        'email' => 'taken@example.com',
        'password' => Hash::make('x'),
    ]);

    $response = $this->actingAs($user)->put('/admin/profile', [
        'name' => 'Original',
        'email' => 'taken@example.com',
    ]);

    $response->assertSessionHasErrors('email');
});

it('allows keeping your own email (unique ignore self)', function (): void {
    registerProfileRoutes();
    $user = makeProfileUser();

    $response = $this->actingAs($user)->put('/admin/profile', [
        'name' => 'Original',
        'email' => 'original@example.com',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

it('updates the password when current_password is correct', function (): void {
    registerProfileRoutes();
    $user = makeProfileUser();

    $response = $this->actingAs($user)->put('/admin/profile/password', [
        'current_password' => 'secret-password',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ]);

    $response->assertRedirect();
    $user->refresh();
    expect(Hash::check('new-secret-password', $user->password))->toBeTrue();
});

it('rejects a wrong current_password', function (): void {
    registerProfileRoutes();
    $user = makeProfileUser();

    $response = $this->actingAs($user)->put('/admin/profile/password', [
        'current_password' => 'wrong-password',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ]);

    $response->assertSessionHasErrors('current_password');
});

it('throttles repeated password-change attempts', function (): void {
    registerProfileRoutes();
    $user = makeProfileUser();
    RateLimiter::clear('');

    $last = null;
    for ($i = 0; $i < 7; $i++) {
        $last = $this->actingAs($user)->put('/admin/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ]);
    }

    expect($last->getStatusCode())->toBe(429);
});

it('regenerates the session id after a successful password change', function (): void {
    registerProfileRoutes();
    $user = makeProfileUser();

    // Prime a session by hitting a route first, capturing its id.
    $this->actingAs($user)->get('/admin/profile');
    $idBefore = $this->app['session']->getId();

    $response = $this->actingAs($user)->put('/admin/profile/password', [
        'current_password' => 'secret-password',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ]);

    $idAfter = $this->app['session']->getId();

    $response->assertRedirect();
    expect($idAfter)->not->toBe($idBefore);
});

it('does NOT register profile routes when profileEnabled() is false', function (): void {
    // beforeEach did NOT register profile routes, so the Router is clean
    // here: this is a genuine assertion about the profileEnabled() gate,
    // not a tautology (see Change C in the fix brief).
    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login(); // NO ->profile()
    $registry->setCurrent('admin');
    Routes::register($panel);

    expect(Route::has('arqel.auth.profile.show'))->toBeFalse();
    expect(Routes::isProfileRegistered())->toBeFalse();
});

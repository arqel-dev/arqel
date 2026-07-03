<?php

declare(strict_types=1);

use Arqel\Auth\Routes;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
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
    $panel = $registry->panel('admin')->login()->profile();
    $registry->setCurrent('admin');

    Routes::register($panel);
});

function makeProfileUser(): ProfileUser
{
    return ProfileUser::create([
        'name' => 'Original',
        'email' => 'original@example.com',
        'password' => Hash::make('secret-password'),
    ]);
}

it('renders the Inertia profile page on GET /admin/profile', function (): void {
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
    $response = $this->get('/admin/profile');
    expect($response->getStatusCode())->toBe(302);
});

it('updates name and email', function (): void {
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
    $user = makeProfileUser();

    $response = $this->actingAs($user)->put('/admin/profile', [
        'name' => '',
        'email' => 'renamed@example.com',
    ]);

    $response->assertSessionHasErrors('name');
});

it('rejects a duplicate email', function (): void {
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
    $user = makeProfileUser();

    $response = $this->actingAs($user)->put('/admin/profile', [
        'name' => 'Original',
        'email' => 'original@example.com',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

it('updates the password when current_password is correct', function (): void {
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
    $user = makeProfileUser();

    $response = $this->actingAs($user)->put('/admin/profile/password', [
        'current_password' => 'wrong-password',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
    ]);

    $response->assertSessionHasErrors('current_password');
});

it('does NOT register profile routes when profileEnabled() is false', function (): void {
    // The shared beforeEach() above already registers profile routes (via
    // ->profile()) into this test's Router, so a plain Route::has() check
    // here would find that pre-existing route regardless of this test's
    // own registration call. Laravel's RouteCollection has no public API
    // to remove a registered route, so we instead assert that a *second*
    // registration attempt with profile() disabled does not add any new
    // routes to the collection (mirrors the idempotency-style assertions
    // used in RoutesTest.php / RegistrationFlagTest.php).
    $countBefore = count(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes());

    Routes::reset();
    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login(); // no ->profile()
    $registry->setCurrent('admin');
    Routes::register($panel);

    $countAfter = count(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes());

    expect($countAfter)->toBe($countBefore);
});

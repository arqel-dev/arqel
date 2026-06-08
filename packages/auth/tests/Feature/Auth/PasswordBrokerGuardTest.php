<?php

declare(strict_types=1);

use Arqel\Auth\Http\Requests\RegisterRequest;
use Arqel\Auth\Routes;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

/**
 * Regression coverage for #191 — the password reset/forgot flow was
 * hardwired to the default `users` broker and ignored the panel's
 * configured guard (`Panel::authGuard('admin')`).
 *
 * login/logout/verify/register already honour the panel guard via
 * ResolvesPanelGuard (#139/#153); the password path was missed. Here a
 * panel runs on a non-default `admin` guard backed by its own provider
 * (`admins`) and password broker (`admins`) against a separate
 * `admins`/`admin_password_reset_tokens` table. Forgot/reset must resolve
 * the `admins` broker, not `users`.
 */
class BrokerGuardUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'admins';

    protected $guarded = [];

    public $timestamps = false;
}

class BrokerUsersUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function (): void {
    Routes::reset();

    // Default `users` table + broker stay present and UNTOUCHED so the
    // regression assertions can prove the default path is byte-identical.
    Schema::dropIfExists('users');
    Schema::create('users', function ($table): void {
        $table->id();
        $table->string('email')->unique();
        $table->string('password');
        $table->rememberToken()->nullable();
    });

    Schema::dropIfExists('admins');
    Schema::create('admins', function ($table): void {
        $table->id();
        $table->string('name')->nullable();
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

    Schema::dropIfExists('admin_password_reset_tokens');
    Schema::create('admin_password_reset_tokens', function ($table): void {
        $table->string('email')->primary();
        $table->string('token');
        $table->timestamp('created_at')->nullable();
    });

    // A second, non-default guard `admin` backed by its own provider
    // (`admins`) and its own password broker (`admins`).
    config()->set('auth.providers.users.driver', 'eloquent');
    config()->set('auth.providers.users.model', BrokerUsersUser::class);
    config()->set('auth.providers.admins', [
        'driver' => 'eloquent',
        'model' => BrokerGuardUser::class,
    ]);
    config()->set('auth.guards.admin', [
        'driver' => 'session',
        'provider' => 'admins',
    ]);
    config()->set('auth.passwords.users', [
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 0,
    ]);
    config()->set('auth.passwords.admins', [
        'provider' => 'admins',
        'table' => 'admin_password_reset_tokens',
        'expire' => 60,
        'throttle' => 0,
    ]);

    RateLimiter::clear('arqel.forgot-password|foo@bar.com|127.0.0.1');
    RateLimiter::clear('arqel.reset-password|foo@bar.com|127.0.0.1');
});

function registerPasswordPanelWithGuard(string $guard): void
{
    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login()->registration()->passwordReset()->authGuard($guard);
    $registry->setCurrent('admin');

    Routes::register($panel);
}

it('forgot-password resolves the admins broker and tokenises the admins table', function (): void {
    Notification::fake();
    registerPasswordPanelWithGuard('admin');

    BrokerGuardUser::create([
        'email' => 'foo@bar.com',
        'password' => bcrypt('secret123'),
    ]);

    $response = $this->from('/admin/forgot-password')->post('/admin/forgot-password', [
        'email' => 'foo@bar.com',
    ]);

    $response->assertRedirect('/admin/forgot-password');
    $response->assertSessionHas('status');

    Notification::assertSentTo(
        BrokerGuardUser::where('email', 'foo@bar.com')->first(),
        ResetPassword::class,
    );

    // The token must land in the admins broker table, not the default users one.
    expect(DB::table('admin_password_reset_tokens')->where('email', 'foo@bar.com')->exists())->toBeTrue();
    expect(DB::table('password_reset_tokens')->where('email', 'foo@bar.com')->exists())->toBeFalse();
});

it('reset-password resolves the admins broker', function (): void {
    registerPasswordPanelWithGuard('admin');

    $user = BrokerGuardUser::create([
        'email' => 'foo@bar.com',
        'password' => Hash::make('old-secret'),
    ]);

    // Token created on the admins broker — the controller must use the
    // SAME broker to validate it; a 'users'-broker controller would reject it.
    $token = Password::broker('admins')->createToken($user);

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

it('register validates email-unique against the admins provider table', function (): void {
    registerPasswordPanelWithGuard('admin');

    config()->set('arqel.auth.guard', 'admin');

    $request = RegisterRequest::create('/admin/register', 'POST');
    $request->setContainer(app());

    $rules = $request->rules();

    // The unique rule must target the `admins` table, not `users`.
    expect((string) $rules['email'][4])->toContain('admins');
});

// --- Regression: the DEFAULT web/users panel must be byte-identical. ---

it('keeps the users broker for a default-guard panel (forgot)', function (): void {
    Notification::fake();

    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')->login()->passwordReset();
    $registry->setCurrent('admin');
    Routes::register($panel);

    // For the default (web) guard the users provider model resolves the
    // users broker -> password_reset_tokens table.
    config()->set('auth.providers.users.model', BrokerUsersUser::class);

    BrokerUsersUser::create([
        'email' => 'foo@bar.com',
        'password' => bcrypt('secret123'),
    ]);

    $response = $this->from('/admin/forgot-password')->post('/admin/forgot-password', [
        'email' => 'foo@bar.com',
    ]);

    $response->assertRedirect('/admin/forgot-password');

    // Default path tokenises the users broker table.
    expect(DB::table('password_reset_tokens')->where('email', 'foo@bar.com')->exists())->toBeTrue();
    expect(DB::table('admin_password_reset_tokens')->where('email', 'foo@bar.com')->exists())->toBeFalse();
});

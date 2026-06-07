<?php

declare(strict_types=1);

use Arqel\Auth\Http\Controllers\EmailVerificationController;
use Arqel\Auth\Routes;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

/**
 * Regression coverage for #153 — EmailVerificationController read
 * `$request->user()` on the default web guard, ignoring the panel's
 * configured guard (`Panel::authGuard('admin')`).
 *
 * The #139 fix threaded the panel guard through
 * login/logout/register/middleware/routes via `ResolvesPanelGuard`, but
 * MISSED this controller. End-to-end, the route's `auth:{guard}`
 * middleware happens to promote the panel guard to the request default
 * (Authenticate::authenticate → shouldUse), so the routed flow worked by
 * side effect. But the controller itself read a bare `$request->user()`,
 * so the moment it is invoked without that implicit promotion (the
 * direct-invocation tests below — and any future middleware reorder) it
 * resolves the wrong (web) guard → null. After the fix the controller
 * resolves the panel guard explicitly, matching the #139 invariant.
 */
class VerifyGuardUser extends Authenticatable implements MustVerifyEmail
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

    // A second, non-default guard `admin` backed by its own provider.
    config()->set('auth.providers.users.driver', 'eloquent');
    config()->set('auth.providers.users.model', VerifyGuardUser::class);
    config()->set('auth.providers.admins', [
        'driver' => 'eloquent',
        'model' => VerifyGuardUser::class,
    ]);
    config()->set('auth.guards.admin', [
        'driver' => 'session',
        'provider' => 'admins',
    ]);

    $registry = app(PanelRegistry::class);
    $registry->clear();
    $panel = $registry->panel('admin')
        ->login()
        ->emailVerification()
        ->authGuard('admin');
    $registry->setCurrent('admin');

    Routes::register($panel);
});

/**
 * Authenticate $user on the `admin` guard ONLY (no `shouldUse`), so the
 * default `web` guard stays empty. A bare `$request->user()` /
 * `Auth::user()` then resolves null, while `Auth::guard('admin')->user()`
 * resolves the user — exactly the #153 production split where the panel
 * runs on a custom guard.
 */
function authenticateOnlyAdminGuard(VerifyGuardUser $user): void
{
    Auth::guard('admin')->setUser($user);
    // Leave the application default guard as web (do NOT shouldUse).
}

/**
 * A bare Request whose default userResolver reads the application
 * default guard (web) — null in this scenario.
 */
function plainAdminRequest(string $uri = '/admin/email/verify'): Request
{
    return Request::create($uri);
}

/**
 * Resolve an Inertia\Response's props without rendering the host view:
 * feed it an X-Inertia request so it serialises to JSON.
 *
 * @return array<string, mixed>
 */
function inertiaProps(Inertia\Response $response): array
{
    $request = Request::create('/admin/email/verify');
    $request->headers->set('X-Inertia', 'true');

    $data = json_decode($response->toResponse($request)->getContent() ?: '', true);

    return is_array($data['props'] ?? null) ? $data['props'] : [];
}

// --- Direct controller invocation: the body-level guard bug. ---

it('notice() reads the admin-guard user, not the null default-guard user', function (): void {
    $user = VerifyGuardUser::create([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => Hash::make('secret-pass-123'),
    ]);

    authenticateOnlyAdminGuard($user);

    $response = (new EmailVerificationController)->notice(plainAdminRequest());

    // Unverified admin user → renders the notice with their email,
    // not a null-user render.
    expect($response)->toBeInstanceOf(Inertia\Response::class);
    /** @var Inertia\Response $response */
    expect(inertiaProps($response)['email'] ?? null)->toBe('alice@example.com');
});

it('notice() short-circuits an already-verified admin-guard user', function (): void {
    $user = VerifyGuardUser::create([
        'name' => 'Erin',
        'email' => 'erin@example.com',
        'email_verified_at' => now(),
        'password' => Hash::make('secret-pass-123'),
    ]);

    authenticateOnlyAdminGuard($user);

    $response = (new EmailVerificationController)->notice(plainAdminRequest());

    // Verified → redirect away (would NOT happen if the user read null).
    expect($response)->toBeInstanceOf(RedirectResponse::class);
});

it('resend() sends to the admin-guard user instead of bouncing to login', function (): void {
    Notification::fake();

    $user = VerifyGuardUser::create([
        'name' => 'Dave',
        'email' => 'dave@example.com',
        'password' => Hash::make('secret-pass-123'),
    ]);

    authenticateOnlyAdminGuard($user);

    $request = plainAdminRequest('/admin/email/verify/resend');
    $request->setLaravelSession(app('session.store'));

    $response = (new EmailVerificationController)->resend($request);

    // Before the fix: null user → redirect('/admin/login').
    // After the fix: resends and goes back with status.
    expect($response->getTargetUrl())->not->toBe(url('/admin/login'));
    Notification::assertSentTo($user, Illuminate\Auth\Notifications\VerifyEmail::class);
});

// --- End-to-end routed flow: regression guard for the invariant. ---

it('renders the routed notice page for the admin-guard user', function (): void {
    $user = VerifyGuardUser::create([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => Hash::make('secret-pass-123'),
    ]);

    $response = $this->actingAs($user, 'admin')
        ->withHeaders(['X-Inertia' => 'true'])
        ->get('/admin/email/verify');

    $response->assertOk();
    $payload = json_decode($response->getContent() ?: '', true);
    expect($payload['component'] ?? null)->toBe('arqel-dev/auth/VerifyEmailNotice');
    expect($payload['props']['email'] ?? null)->toBe('alice@example.com');
});

it('verifies the admin-guard user end-to-end and dispatches Verified', function (): void {
    Event::fake([Verified::class]);

    $user = VerifyGuardUser::create([
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'password' => Hash::make('secret-pass-123'),
    ]);

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->getKey(), 'hash' => sha1((string) $user->getEmailForVerification())],
    );

    $response = $this->actingAs($user, 'admin')->get($url);

    $response->assertRedirect();
    expect($user->fresh()?->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
});

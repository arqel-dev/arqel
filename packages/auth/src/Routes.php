<?php

declare(strict_types=1);

namespace Arqel\Auth;

use Arqel\Auth\Http\Controllers\EmailVerificationController;
use Arqel\Auth\Http\Controllers\ForgotPasswordController;
use Arqel\Auth\Http\Controllers\LoginController;
use Arqel\Auth\Http\Controllers\LogoutController;
use Arqel\Auth\Http\Controllers\RegisterController;
use Arqel\Auth\Http\Controllers\ResetPasswordController;
use Arqel\Core\Http\Middleware\HandleArqelInertiaRequests;
use Arqel\Core\Panel\Panel;
use Illuminate\Support\Facades\Route;

/**
 * Registo idempotente das rotas bundled de auth.
 *
 * Cobre login + logout (AUTH-006), registration + email verification
 * (AUTH-007), e forgot/reset password (AUTH-008), todos opt-in via
 * flags no `Panel`. Skipa o registo de login quando o app host já
 * tem uma rota chamada `login` (e.g. Breeze/Jetstream/Fortify),
 * preservando compatibilidade.
 */
final class Routes
{
    private static bool $registered = false;

    private static bool $registrationRegistered = false;

    private static bool $verificationRegistered = false;

    private static bool $passwordResetRegistered = false;

    public static function register(?Panel $panel = null): void
    {
        self::registerLogin($panel);
        self::registerRegistration($panel);
        self::registerEmailVerification($panel);
        self::registerPasswordReset($panel);
    }

    /**
     * Resolve the guard the bundled routes should gate on (#139).
     *
     * Prefers the panel's `authGuard(...)`, then the `arqel.auth.guard`
     * config, then `web`. Emitting `auth:web`/`guest:web` is equivalent
     * to plain `auth`/`guest` for the default guard, so always scoping
     * the middleware is safe and keeps the default path unchanged.
     */
    private static function guardFor(?Panel $panel): string
    {
        if ($panel !== null) {
            return $panel->getAuthGuard();
        }

        $configured = config('arqel.auth.guard', 'web');

        return is_string($configured) && $configured !== '' ? $configured : 'web';
    }

    public static function registerLogin(?Panel $panel = null): void
    {
        if (self::$registered) {
            return;
        }

        Route::getRoutes()->refreshNameLookups();
        if (Route::has('login')) {
            self::$registered = true;

            return;
        }

        $loginUrl = $panel?->getLoginUrl() ?? '/admin/login';
        $logoutUrl = self::deriveLogoutUrl($loginUrl);
        $guard = self::guardFor($panel);

        Route::get($loginUrl, [LoginController::class, 'showForm'])
            ->middleware(['web', HandleArqelInertiaRequests::class, "guest:{$guard}"])
            ->name('login');

        Route::post($loginUrl, LoginController::class)
            ->middleware(['web', HandleArqelInertiaRequests::class, "guest:{$guard}", 'throttle:5,1'])
            ->name('arqel.auth.login.attempt');

        Route::post($logoutUrl, LogoutController::class)
            ->middleware(['web', HandleArqelInertiaRequests::class, "auth:{$guard}"])
            ->name('logout');

        self::$registered = true;
    }

    public static function registerRegistration(?Panel $panel = null): void
    {
        if (self::$registrationRegistered) {
            return;
        }

        if ($panel === null || ! $panel->registrationEnabled()) {
            return;
        }

        Route::getRoutes()->refreshNameLookups();
        if (Route::has('register')) {
            self::$registrationRegistered = true;

            return;
        }

        $registerUrl = self::deriveSiblingUrl($panel->getLoginUrl(), 'register');
        $guard = self::guardFor($panel);

        Route::get($registerUrl, [RegisterController::class, 'showForm'])
            ->middleware(['web', HandleArqelInertiaRequests::class, "guest:{$guard}"])
            ->name('register');

        Route::post($registerUrl, RegisterController::class)
            ->middleware(['web', HandleArqelInertiaRequests::class, "guest:{$guard}", 'throttle:10,60'])
            ->name('arqel.auth.register.attempt');

        self::$registrationRegistered = true;
    }

    public static function registerEmailVerification(?Panel $panel = null): void
    {
        if (self::$verificationRegistered) {
            return;
        }

        if ($panel === null || ! $panel->emailVerificationEnabled()) {
            return;
        }

        Route::getRoutes()->refreshNameLookups();
        if (Route::has('verification.notice')) {
            self::$verificationRegistered = true;

            return;
        }

        $base = self::deriveSiblingUrl($panel->getLoginUrl(), 'email/verify');
        $guard = self::guardFor($panel);

        Route::get($base, [EmailVerificationController::class, 'notice'])
            ->middleware(['web', HandleArqelInertiaRequests::class, "auth:{$guard}"])
            ->name('verification.notice');

        Route::get($base.'/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['web', HandleArqelInertiaRequests::class, "auth:{$guard}", 'signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::post($base.'/resend', [EmailVerificationController::class, 'resend'])
            ->middleware(['web', HandleArqelInertiaRequests::class, "auth:{$guard}", 'throttle:6,1'])
            ->name('verification.send');

        self::$verificationRegistered = true;
    }

    /**
     * Regista as rotas de forgot-password + reset-password (idempotente).
     */
    public static function registerPasswordReset(?Panel $panel = null): void
    {
        if (self::$passwordResetRegistered) {
            return;
        }

        if (! ($panel?->passwordResetEnabled() ?? false)) {
            return;
        }

        Route::getRoutes()->refreshNameLookups();
        if (Route::has('password.request') || Route::has('password.reset')) {
            self::$passwordResetRegistered = true;

            return;
        }

        $base = self::deriveBasePath($panel?->getLoginUrl() ?? '/admin/login');
        $guard = self::guardFor($panel);

        Route::get($base.'/forgot-password', [ForgotPasswordController::class, 'showForm'])
            ->middleware(['web', HandleArqelInertiaRequests::class, "guest:{$guard}"])
            ->name('password.request');

        Route::post($base.'/forgot-password', ForgotPasswordController::class)
            ->middleware(['web', HandleArqelInertiaRequests::class, "guest:{$guard}", 'throttle:6,1'])
            ->name('password.email');

        Route::post($base.'/reset-password', ResetPasswordController::class)
            ->middleware(['web', HandleArqelInertiaRequests::class, "guest:{$guard}", 'throttle:6,1'])
            ->name('password.update');

        Route::get($base.'/reset-password/{token}', [ResetPasswordController::class, 'showForm'])
            ->middleware(['web', HandleArqelInertiaRequests::class, "guest:{$guard}"])
            ->name('password.reset');

        self::$passwordResetRegistered = true;
    }

    /**
     * @internal Used by tests to reset the singleton flags.
     */
    public static function reset(): void
    {
        self::$registered = false;
        self::$registrationRegistered = false;
        self::$verificationRegistered = false;
        self::$passwordResetRegistered = false;
    }

    public static function isRegistered(): bool
    {
        return self::$registered;
    }

    public static function isRegistrationRegistered(): bool
    {
        return self::$registrationRegistered;
    }

    public static function isVerificationRegistered(): bool
    {
        return self::$verificationRegistered;
    }

    public static function isPasswordResetRegistered(): bool
    {
        return self::$passwordResetRegistered;
    }

    private static function deriveLogoutUrl(string $loginUrl): string
    {
        if (str_ends_with($loginUrl, '/login')) {
            return substr($loginUrl, 0, -6).'/logout';
        }

        return rtrim($loginUrl, '/').'/logout';
    }

    private static function deriveSiblingUrl(string $loginUrl, string $sibling): string
    {
        $sibling = '/'.ltrim($sibling, '/');

        if (str_ends_with($loginUrl, '/login')) {
            return substr($loginUrl, 0, -6).$sibling;
        }

        return rtrim($loginUrl, '/').$sibling;
    }

    private static function deriveBasePath(string $loginUrl): string
    {
        if (str_ends_with($loginUrl, '/login')) {
            return rtrim(substr($loginUrl, 0, -6), '/');
        }

        return rtrim($loginUrl, '/');
    }
}

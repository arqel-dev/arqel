<?php

declare(strict_types=1);

namespace Arqel\Auth;

use Arqel\Auth\Http\Controllers\ForgotPasswordController;
use Arqel\Auth\Http\Controllers\LoginController;
use Arqel\Auth\Http\Controllers\LogoutController;
use Arqel\Auth\Http\Controllers\ResetPasswordController;
use Arqel\Core\Panel\Panel;
use Illuminate\Support\Facades\Route;

/**
 * Registo idempotente das rotas bundled de auth.
 *
 * Skipa o registo quando o app host já tem uma rota chamada `login`
 * (e.g. Breeze/Jetstream/Fortify), preservando compatibilidade.
 */
final class Routes
{
    private static bool $registered = false;

    private static bool $passwordResetRegistered = false;

    public static function register(?Panel $panel = null): void
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

        Route::get($loginUrl, [LoginController::class, 'showForm'])
            ->middleware(['web'])
            ->name('login');

        Route::post($loginUrl, LoginController::class)
            ->middleware(['web', 'throttle:5,1'])
            ->name('arqel.auth.login.attempt');

        Route::post($logoutUrl, LogoutController::class)
            ->middleware(['web', 'auth'])
            ->name('logout');

        self::registerPasswordReset($panel);

        self::$registered = true;
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

        Route::get($base.'/forgot-password', [ForgotPasswordController::class, 'showForm'])
            ->middleware(['web', 'guest'])
            ->name('password.request');

        Route::post($base.'/forgot-password', ForgotPasswordController::class)
            ->middleware(['web', 'guest', 'throttle:6,1'])
            ->name('password.email');

        Route::post($base.'/reset-password', ResetPasswordController::class)
            ->middleware(['web', 'guest', 'throttle:6,1'])
            ->name('password.update');

        Route::get($base.'/reset-password/{token}', [ResetPasswordController::class, 'showForm'])
            ->middleware(['web', 'guest'])
            ->name('password.reset');

        self::$passwordResetRegistered = true;
    }

    /**
     * @internal Used by tests to reset the singleton flags.
     */
    public static function reset(): void
    {
        self::$registered = false;
        self::$passwordResetRegistered = false;
    }

    public static function isRegistered(): bool
    {
        return self::$registered;
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

    private static function deriveBasePath(string $loginUrl): string
    {
        if (str_ends_with($loginUrl, '/login')) {
            return rtrim(substr($loginUrl, 0, -6), '/');
        }

        return rtrim($loginUrl, '/');
    }
}

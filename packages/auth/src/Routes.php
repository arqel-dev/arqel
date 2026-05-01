<?php

declare(strict_types=1);

namespace Arqel\Auth;

use Arqel\Auth\Http\Controllers\LoginController;
use Arqel\Auth\Http\Controllers\LogoutController;
use Arqel\Core\Panel\Panel;
use Illuminate\Support\Facades\Route;

/**
 * Registo idempotente das rotas bundled de auth (login + logout).
 *
 * Skipa o registo quando o app host já tem uma rota chamada `login`
 * (e.g. Breeze/Jetstream/Fortify), preservando compatibilidade.
 */
final class Routes
{
    private static bool $registered = false;

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

        self::$registered = true;
    }

    /**
     * @internal Used by tests to reset the singleton flag.
     */
    public static function reset(): void
    {
        self::$registered = false;
    }

    public static function isRegistered(): bool
    {
        return self::$registered;
    }

    private static function deriveLogoutUrl(string $loginUrl): string
    {
        // Replace trailing /login with /logout, preserving any prefix.
        if (str_ends_with($loginUrl, '/login')) {
            return substr($loginUrl, 0, -6).'/logout';
        }

        return rtrim($loginUrl, '/').'/logout';
    }
}

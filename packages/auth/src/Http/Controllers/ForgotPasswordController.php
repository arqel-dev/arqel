<?php

declare(strict_types=1);

namespace Arqel\Auth\Http\Controllers;

use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Forgot-password bundled de Arqel.
 *
 * Renderiza o componente Inertia `arqel-dev/auth/ForgotPassword` e dispara o
 * envio do reset link via `Password::sendResetLink`. A resposta é genérica
 * (não revela se o e-mail existe) — flash `status` constante.
 */
final class ForgotPasswordController
{
    /**
     * POST /admin/forgot-password — envia reset link (resposta genérica).
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $this->ensureIsNotRateLimited($request, (string) $data['email']);

        RateLimiter::hit($this->throttleKey($request, (string) $data['email']), 3600);

        Password::broker()->sendResetLink(['email' => $data['email']]);

        return back()->with('status', __('A reset link has been sent if the email exists.'));
    }

    /**
     * GET /admin/forgot-password — renderiza a página Inertia.
     */
    public function showForm(): Response
    {
        return Inertia::render('arqel-dev/auth/ForgotPassword', [
            'loginUrl' => $this->currentPanel()?->getLoginUrl() ?? '/admin/login',
            'forgotPasswordUrl' => $this->currentPanel()?->getForgotPasswordUrl() ?? '/admin/forgot-password',
        ]);
    }

    private function ensureIsNotRateLimited(Request $request, string $email): void
    {
        $key = $this->throttleKey($request, $email);

        if (! RateLimiter::tooManyAttempts($key, 3)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(Request $request, string $email): string
    {
        return 'arqel.forgot-password|'.Str::transliterate(Str::lower($email)).'|'.$request->ip();
    }

    private function currentPanel(): ?\Arqel\Core\Panel\Panel
    {
        if (! app()->bound(PanelRegistry::class)) {
            return null;
        }

        return app(PanelRegistry::class)->getCurrent();
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Auth\Http\Controllers;

use Arqel\Auth\Http\Requests\ResetPasswordRequest;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reset-password bundled de Arqel.
 *
 * Renderiza o componente Inertia `arqel/auth/ResetPassword` (com token na
 * rota e email vindo da query string) e processa o submit validando token
 * via `Password::reset`.
 */
final class ResetPasswordController
{
    /**
     * POST /admin/reset-password — processa o reset.
     */
    public function __invoke(ResetPasswordRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Authenticatable $user, string $password): void {
                /** @var \Illuminate\Database\Eloquent\Model&Authenticatable $user */
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                Event::dispatch(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            $request->hitRateLimiter();

            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return redirect($this->currentPanel()?->getLoginUrl() ?? '/admin/login')
            ->with('status', __($status));
    }

    /**
     * GET /admin/reset-password/{token} — renderiza a página Inertia.
     */
    public function showForm(Request $request, string $token): Response
    {
        return Inertia::render('arqel/auth/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
            'loginUrl' => $this->currentPanel()?->getLoginUrl() ?? '/admin/login',
        ]);
    }

    private function currentPanel(): ?\Arqel\Core\Panel\Panel
    {
        if (! app()->bound(PanelRegistry::class)) {
            return null;
        }

        return app(PanelRegistry::class)->getCurrent();
    }
}

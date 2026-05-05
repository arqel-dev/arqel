<?php

declare(strict_types=1);

namespace Arqel\Auth\Http\Controllers;

use Arqel\Auth\Http\Requests\LoginRequest;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Login bundled de Arqel.
 *
 * Renderiza o componente Inertia `arqel-dev/auth/Login` (servido pelo
 * pacote npm `@arqel-dev/auth`) e processa o submit via `LoginRequest`.
 */
final class LoginController
{
    /**
     * POST /admin/login — autentica e redireciona.
     */
    public function __invoke(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended($this->afterLoginUrl());
    }

    /**
     * GET /admin/login — renderiza a página Inertia.
     */
    public function showForm(): Response
    {
        $panel = $this->currentPanel();

        return Inertia::render('arqel-dev/auth/Login', [
            'canRegister' => $panel?->registrationEnabled() ?? false,
            'canResetPassword' => $panel?->passwordResetEnabled() ?? false,
            'loginUrl' => $panel?->getLoginUrl() ?? '/admin/login',
            'registerUrl' => Route::has('register') ? route('register') : '/admin/register',
            'forgotPasswordUrl' => $panel?->getForgotPasswordUrl() ?? '/admin/forgot-password',
        ]);
    }

    private function currentPanel(): ?\Arqel\Core\Panel\Panel
    {
        if (! app()->bound(PanelRegistry::class)) {
            return null;
        }

        return app(PanelRegistry::class)->getCurrent();
    }

    private function afterLoginUrl(): string
    {
        return $this->currentPanel()?->getAfterLoginUrl() ?? '/admin';
    }
}

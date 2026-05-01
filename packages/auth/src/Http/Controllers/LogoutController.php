<?php

declare(strict_types=1);

namespace Arqel\Auth\Http\Controllers;

use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Logout bundled de Arqel.
 *
 * Invalida a sessão, rotaciona CSRF e redireciona para a URL de login
 * configurada no painel atual.
 */
final class LogoutController
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($this->loginUrl());
    }

    private function loginUrl(): string
    {
        if (! app()->bound(PanelRegistry::class)) {
            return '/admin/login';
        }

        return app(PanelRegistry::class)->getCurrent()?->getLoginUrl() ?? '/admin/login';
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Auth\Http\Controllers;

use Arqel\Auth\Concerns\ResolvesPanelGuard;
use Arqel\Auth\Http\Requests\PanelEmailVerificationRequest;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Email verification bundled de Arqel.
 *
 * Three actions:
 * - `notice`: GET /admin/email/verify — renderiza Inertia notice page.
 * - `verify`: GET /admin/email/verify/{id}/{hash} — signed URL handler.
 * - `resend`: POST /admin/email/verify/resend — re-envia notification.
 *
 * Todas as três ações resolvem o usuário contra o guard configurado do
 * painel (`Panel::authGuard(...)`) via `ResolvesPanelGuard`, em vez do
 * guard default `web` — completando o sweep do #139 (#153). A ação
 * `verify` injeta {@see PanelEmailVerificationRequest}, que sobrescreve
 * `user()` para que o `authorize()`/`fulfill()` da request leiam o mesmo
 * guard.
 */
final class EmailVerificationController
{
    use ResolvesPanelGuard;

    public function notice(Request $request): Response|RedirectResponse
    {
        $user = Auth::guard($this->resolvePanelGuard())->user();

        if ($user !== null && method_exists($user, 'hasVerifiedEmail') && $user->hasVerifiedEmail()) {
            return redirect()->intended($this->afterLoginUrl());
        }

        return Inertia::render('arqel-dev/auth/VerifyEmailNotice', [
            'email' => $user?->getAttribute('email'),
            'status' => session('status'),
        ]);
    }

    public function verify(PanelEmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && method_exists($user, 'hasVerifiedEmail') && $user->hasVerifiedEmail()) {
            return redirect()->intended($this->afterLoginUrl().'?verified=1');
        }

        // `fulfill()` marks the (panel-guard) user verified and dispatches
        // the `Verified` event itself; it returns void, so its result must
        // not be branched on.
        $request->fulfill();

        return redirect()->intended($this->afterLoginUrl().'?verified=1');
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = Auth::guard($this->resolvePanelGuard())->user();

        if ($user === null) {
            return redirect()->to('/admin/login');
        }

        if (method_exists($user, 'hasVerifiedEmail') && $user->hasVerifiedEmail()) {
            return redirect()->intended($this->afterLoginUrl());
        }

        if (method_exists($user, 'sendEmailVerificationNotification')) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('status', 'verification-link-sent');
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

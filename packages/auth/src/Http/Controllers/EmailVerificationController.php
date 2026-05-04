<?php

declare(strict_types=1);

namespace Arqel\Auth\Http\Controllers;

use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Email verification bundled de Arqel.
 *
 * Three actions:
 * - `notice`: GET /admin/email/verify — renderiza Inertia notice page.
 * - `verify`: GET /admin/email/verify/{id}/{hash} — signed URL handler.
 * - `resend`: POST /admin/email/verify/resend — re-envia notification.
 */
final class EmailVerificationController
{
    public function notice(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && method_exists($user, 'hasVerifiedEmail') && $user->hasVerifiedEmail()) {
            return redirect()->intended($this->afterLoginUrl());
        }

        return Inertia::render('arqel-dev/auth/VerifyEmailNotice', [
            'email' => $user?->getAttribute('email'),
            'status' => session('status'),
        ]);
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && method_exists($user, 'hasVerifiedEmail') && $user->hasVerifiedEmail()) {
            return redirect()->intended($this->afterLoginUrl().'?verified=1');
        }

        if ($request->fulfill()) {
            if ($user !== null) {
                Event::dispatch(new Verified($user));
            }
        }

        return redirect()->intended($this->afterLoginUrl().'?verified=1');
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

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

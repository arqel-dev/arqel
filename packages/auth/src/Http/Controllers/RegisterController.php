<?php

declare(strict_types=1);

namespace Arqel\Auth\Http\Controllers;

use Arqel\Auth\Http\Requests\RegisterRequest;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Registration bundled de Arqel.
 *
 * Renderiza o componente Inertia `arqel-dev/auth/Register` (servido pelo
 * pacote npm `@arqel-dev/auth`) e processa o submit via `RegisterRequest`.
 */
final class RegisterController
{
    /**
     * POST /admin/register — cria o user, dispara `Registered`, auto-login e redireciona.
     */
    public function __invoke(RegisterRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $validated = $request->validated();
        $request->recordAttempt();

        $modelClass = $this->resolveUserModel();

        /** @var \Illuminate\Contracts\Auth\Authenticatable $user */
        $user = $modelClass::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make((string) $validated['password']),
        ]);

        Event::dispatch(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended($this->afterLoginUrl());
    }

    /**
     * GET /admin/register — renderiza a página Inertia.
     */
    public function showForm(): Response
    {
        return Inertia::render('arqel-dev/auth/Register', [
            'canLogin' => true,
            'loginUrl' => $this->currentPanel()?->getLoginUrl() ?? '/admin/login',
        ]);
    }

    /**
     * @return class-string
     */
    private function resolveUserModel(): string
    {
        $model = (string) config('auth.providers.users.model', 'App\\Models\\User');

        return class_exists($model) ? $model : 'App\\Models\\User';
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

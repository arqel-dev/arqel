<?php

declare(strict_types=1);

namespace Arqel\Auth\Http\Controllers;

use Arqel\Auth\Http\Requests\UpdatePasswordRequest;
use Arqel\Auth\Http\Requests\UpdateProfileRequest;
use Arqel\Core\Panel\PanelRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página de perfil bundled de Arqel (Auth UI).
 *
 * Renderiza o componente Inertia `arqel-dev/auth/Profile` e processa os
 * dois formulários: dados de conta (nome/email) e troca de senha.
 */
final class ProfileController
{
    /**
     * GET {profileUrl} — renderiza a página Inertia com os dados do user.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('arqel-dev/auth/Profile', [
            'user' => $user?->only(['id', 'name', 'email']) ?? [],
            'updateUrl' => $this->profileUrl(),
            'passwordUrl' => $this->profileUrl().'/password',
        ]);
    }

    /**
     * PUT {profileUrl} — atualiza nome + email.
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->save();

        return back()->with('success', __('arqel::messages.flash.profile.updated'));
    }

    /**
     * PUT {profileUrl}/password — troca a senha.
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $user->password = Hash::make((string) $validated['password']);
        $user->save();

        return back()->with('success', __('arqel::messages.flash.profile.password_updated'));
    }

    private function profileUrl(): string
    {
        $panel = $this->currentPanel();
        $loginUrl = $panel?->getLoginUrl() ?? '/admin/login';

        if (str_ends_with($loginUrl, '/login')) {
            return substr($loginUrl, 0, -6).'/profile';
        }

        return rtrim($loginUrl, '/').'/profile';
    }

    private function currentPanel(): ?\Arqel\Core\Panel\Panel
    {
        if (! app()->bound(PanelRegistry::class)) {
            return null;
        }

        return app(PanelRegistry::class)->getCurrent();
    }
}

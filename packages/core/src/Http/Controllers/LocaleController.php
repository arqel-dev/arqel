<?php

declare(strict_types=1);

namespace Arqel\Core\Http\Controllers;

use Arqel\Core\Http\Middleware\SetLocaleMiddleware;
use Arqel\Core\I18n\TranslationLoader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Endpoint POST que persiste a escolha de locale do usuário.
 *
 * Grava em duas camadas para casar com a resolução do
 * {@see SetLocaleMiddleware}:
 *
 *  1. `session('locale')` — fonte tier-1 dentro da sessão;
 *  2. cookie `arqel_locale` — fonte tier-2 cross-session, lida
 *     pelo middleware quando a sessão expira.
 *
 * Validação por allowlist via {@see TranslationLoader::availableLocales()}.
 */
final class LocaleController
{
    public function __construct(
        private readonly TranslationLoader $loader,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        /** @var mixed $raw */
        $raw = $request->input('locale', $request->query('locale'));
        $locale = is_string($raw) ? $raw : '';

        $available = $this->loader->availableLocales();

        // Compara hífen/underscore como o mesmo locale: `pt-BR` casa com a
        // entrada `pt_BR` (e vice-versa). Persistimos a forma canónica do
        // allowlist para que o middleware a reconheça em requests futuros.
        $matched = $this->loader->matchAvailable($locale, $available);
        if ($matched === null) {
            abort(422, 'Invalid locale.');
        }

        if ($request->hasSession()) {
            $request->session()->put('locale', $matched);
        }

        return back()->withCookie(Cookie::forever('arqel_locale', $matched));
    }
}

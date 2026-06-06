<?php

declare(strict_types=1);

namespace Arqel\Core\Http\Controllers;

use Arqel\Core\I18n\TranslationLoader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Endpoint POST que persiste a escolha de locale do usuário.
 *
 * Grava em duas camadas para casar com a resolução do
 * {@see \Arqel\Core\Http\Middleware\SetLocaleMiddleware}:
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

        if (! in_array($locale, $available, true)) {
            abort(422, 'Invalid locale.');
        }

        if ($request->hasSession()) {
            $request->session()->put('locale', $locale);
        }

        return back()->withCookie(Cookie::forever('arqel_locale', $locale));
    }
}

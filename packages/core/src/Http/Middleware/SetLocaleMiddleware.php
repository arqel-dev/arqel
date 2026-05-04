<?php

declare(strict_types=1);

namespace Arqel\Core\Http\Middleware;

use Arqel\Core\I18n\TranslationLoader;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define o locale activo a cada request com base em (ordem):
 *
 *  1. `session('locale')` — escolha persistente do utilizador
 *  2. `cookie('arqel_locale')` — preferência cross-session
 *  3. `Accept-Language` — primeira língua aceite que esteja
 *      no allowlist do `TranslationLoader::availableLocales()`
 *  4. `TranslationLoader::defaultLocale()`
 *
 * Locales fora do allowlist são silenciosamente ignorados — nunca
 * passamos input não validado a `App::setLocale` para evitar
 * surpresas (e.g. injecção de path em loaders custom).
 */
final class SetLocaleMiddleware
{
    public function __construct(
        private readonly TranslationLoader $loader,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $available = $this->loader->availableLocales();
        $locale = $this->resolveLocale($request, $available);

        App::setLocale($locale);

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }

    /**
     * @param array<int, string> $available
     */
    private function resolveLocale(Request $request, array $available): string
    {
        $session = $request->hasSession() ? $request->session()->get('locale') : null;
        if (is_string($session) && in_array($session, $available, true)) {
            return $session;
        }

        $cookie = $request->cookie('arqel_locale');
        if (is_string($cookie) && in_array($cookie, $available, true)) {
            return $cookie;
        }

        $header = $request->header('Accept-Language');
        if (is_string($header) && $header !== '') {
            foreach ($this->parseAcceptLanguage($header) as $candidate) {
                if (in_array($candidate, $available, true)) {
                    return $candidate;
                }
            }
        }

        return $this->loader->defaultLocale();
    }

    /**
     * Quebra um header `Accept-Language` em lista ordenada de
     * locales tentativos. Aceita variantes como `pt-BR`, `pt_BR`,
     * `pt`, `en-US;q=0.9` — a normalização troca `-` por `_` para
     * casar com o padrão do Laravel.
     *
     * @return array<int, string>
     */
    private function parseAcceptLanguage(string $header): array
    {
        $parts = explode(',', $header);
        $candidates = [];

        foreach ($parts as $part) {
            $segment = trim(explode(';', $part)[0] ?? '');
            if ($segment === '') {
                continue;
            }

            $candidates[] = str_replace('-', '_', $segment);
        }

        return $candidates;
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Core\I18n;

use Illuminate\Contracts\Foundation\Application;

/**
 * Carrega traduções do namespace `arqel::*` agregadas para
 * consumo no front-end React via Inertia shared props.
 *
 * Combina cada ficheiro de lang publicado em `resources/lang/{locale}/`
 * (`arqel`, `messages`, `actions`, `table`, `form`, `validation`)
 * num único array nested. O `defaultLocale()` é sempre carregado como
 * base e o locale alvo é deep-merged por cima, de modo que um locale
 * parcialmente traduzido herda os valores default per-key em vez de
 * deixar a chave dotted crua vazar para a UI.
 */
final readonly class TranslationLoader
{
    /**
     * Ficheiros de lang carregados pelo loader. Ordem importa para
     * merge — chaves do primeiro ficheiro são sobrescritas pelos
     * seguintes em caso de colisão (raro, mas determinístico).
     *
     * @var array<int, string>
     */
    private const array LANG_FILES = ['arqel', 'messages', 'actions', 'table', 'form', 'validation', 'widgets', 'palette', 'pulse'];

    public function __construct(
        private Application $app,
    ) {}

    /**
     * Carrega traduções para um locale específico.
     *
     * O directório default (fallback_locale) é carregado como base e o locale
     * alvo é deep-merged por cima, chave a chave. Assim, um locale parcialmente
     * traduzido (directório existe mas omite chaves) herda o valor default em vez
     * de deixar a chave dotted crua vazar para a UI (o lado React devolve a chave
     * quando ela falta). Quando o locale resolvido É o default, ou está completo,
     * o resultado é idêntico ao comportamento anterior.
     *
     * @return array<string, mixed>
     */
    public function loadForLocale(string $locale): array
    {
        $resolved = $this->resolveLocale($locale);
        $default = self::normalize($this->defaultLocale());

        $merged = $this->loadDir($default);

        if ($resolved !== $default) {
            $merged = self::deepMerge($merged, $this->loadDir($resolved));
        }

        return $merged;
    }

    /**
     * Carrega e agrega os ficheiros de lang de um único directório de locale.
     *
     * @return array<string, mixed>
     */
    private function loadDir(string $locale): array
    {
        $base = $this->langPath();

        $merged = [];
        foreach (self::LANG_FILES as $file) {
            $path = $base.'/'.$locale.'/'.$file.'.php';
            if (! is_file($path)) {
                continue;
            }

            /** @var mixed $contents */
            $contents = require $path;
            if (! is_array($contents)) {
                continue;
            }

            /** @var array<string, mixed> $contents */
            $merged[$file] = $contents;
        }

        return $merged;
    }

    /**
     * Deep-merge recursivo: `$override` vence `$base` chave a chave. Sub-arrays
     * associativos são fundidos recursivamente; valores escalares (e a folha do
     * override) substituem o base. Chaves presentes só no base sobrevivem — é
     * isto que dá o fallback per-key para locales parciais.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private static function deepMerge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            $existing = $base[$key] ?? null;
            if (is_array($existing) && is_array($value)) {
                /** @var array<string, mixed> $existing */
                /** @var array<string, mixed> $value */
                $base[$key] = self::deepMerge($existing, $value);

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * Locales suportados pelo painel. Configurável via
     * `arqel.i18n.locales`; default `['en', 'pt_BR']`.
     *
     * Cada entrada é validada contra um directório de lang em disco
     * (na sua forma normalizada `pt-BR` → `pt_BR`), para que o switcher
     * só ofereça locales que realmente resolvem em traduções — caso
     * contrário o utilizador "muda" para um locale sem ficheiros e vê o
     * fallback default sem qualquer aviso. Se nenhuma entrada configurada
     * existir em disco, o default seguro `['en', 'pt_BR']` é devolvido.
     *
     * @return array<int, string>
     */
    public function availableLocales(): array
    {
        $configured = $this->app->make('config')->get('arqel.i18n.locales');

        if (! is_array($configured) || $configured === []) {
            return ['en', 'pt_BR'];
        }

        $base = $this->langPath();

        $clean = [];
        foreach ($configured as $value) {
            if (is_string($value) && $value !== '' && is_dir($base.'/'.self::normalize($value))) {
                $clean[] = $value;
            }
        }

        return $clean === [] ? ['en', 'pt_BR'] : $clean;
    }

    /**
     * Resolve um locale candidato (sessão, cookie, header, input do switcher)
     * para a entrada equivalente no allowlist, comparando ambas as formas em
     * versão normalizada (`-` ↔ `_`). Devolve a forma exacta tal como aparece
     * em `availableLocales()` — ou `null` se nenhuma variante casar.
     *
     * Isto fecha a divergência hífen/underscore: configurar `['en', 'pt-BR']`
     * mais um header `Accept-Language: pt-BR` (normalizado para `pt_BR`) deixa
     * de falhar o `in_array` estrito; ambas resolvem para a mesma entrada.
     *
     * @param array<int, string> $available
     */
    public function matchAvailable(string $candidate, array $available): ?string
    {
        $normalizedCandidate = self::normalize($candidate);
        if ($normalizedCandidate === '') {
            return null;
        }

        foreach ($available as $entry) {
            if (self::normalize($entry) === $normalizedCandidate) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Normaliza um locale para a convenção em disco do Laravel, trocando o
     * separador BCP-47 (`-`) pelo underscore (`pt-BR` → `pt_BR`).
     */
    public static function normalize(string $locale): string
    {
        return str_replace('-', '_', $locale);
    }

    /**
     * Locale default lido de `arqel.i18n.default` ou, em fallback,
     * de `app.locale`.
     */
    public function defaultLocale(): string
    {
        $config = $this->app->make('config');

        $configured = $config->get('arqel.i18n.default');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $appLocale = $config->get('app.locale');

        return is_string($appLocale) && $appLocale !== '' ? $appLocale : 'en';
    }

    /**
     * Resolve um locale para a sua versão em disco. Retorna o
     * `defaultLocale()` se o directório não existir.
     *
     * O locale é normalizado (`-` → `_`) antes do lookup em disco — espelha a
     * normalização do `SetLocaleMiddleware` para o header `Accept-Language` —
     * de modo que a variante hifenizada `pt-BR` (aceite pelo allowlist e pelos
     * `DEFAULT_LABELS` do `LocaleSwitcher`) resolva para o mesmo directório
     * `pt_BR` em vez de cair silenciosamente no default (#250).
     */
    private function resolveLocale(string $locale): string
    {
        $base = $this->langPath();
        $normalized = self::normalize($locale);
        if ($normalized !== '' && is_dir($base.'/'.$normalized)) {
            return $normalized;
        }

        $default = self::normalize($this->defaultLocale());
        if (is_dir($base.'/'.$default)) {
            return $default;
        }

        return 'en';
    }

    private function langPath(): string
    {
        return dirname(__DIR__, 2).'/resources/lang';
    }
}

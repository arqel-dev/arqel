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
 * num único array nested. Quando o locale alvo não existe em disco,
 * usa silenciosamente o `defaultLocale()` como fallback para evitar
 * payloads vazios na UI.
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
    private const array LANG_FILES = ['arqel', 'messages', 'actions', 'table', 'form'];

    public function __construct(
        private Application $app,
    ) {}

    /**
     * Carrega traduções para um locale específico. Faz fallback
     * para `defaultLocale()` se o directório não existir.
     *
     * @return array<string, mixed>
     */
    public function loadForLocale(string $locale): array
    {
        $resolved = $this->resolveLocale($locale);
        $base = $this->langPath();

        $merged = [];
        foreach (self::LANG_FILES as $file) {
            $path = $base.'/'.$resolved.'/'.$file.'.php';
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
     * Locales suportados pelo painel. Configurável via
     * `arqel.i18n.locales`; default `['en', 'pt_BR']`.
     *
     * @return array<int, string>
     */
    public function availableLocales(): array
    {
        $configured = $this->app->make('config')->get('arqel.i18n.locales');

        if (! is_array($configured) || $configured === []) {
            return ['en', 'pt_BR'];
        }

        $clean = [];
        foreach ($configured as $value) {
            if (is_string($value) && $value !== '') {
                $clean[] = $value;
            }
        }

        return $clean === [] ? ['en', 'pt_BR'] : $clean;
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
     */
    private function resolveLocale(string $locale): string
    {
        $base = $this->langPath();
        if ($locale !== '' && is_dir($base.'/'.$locale)) {
            return $locale;
        }

        $default = $this->defaultLocale();
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

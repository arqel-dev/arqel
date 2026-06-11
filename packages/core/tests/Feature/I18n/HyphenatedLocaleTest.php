<?php

declare(strict_types=1);

use Arqel\Core\I18n\TranslationLoader;

/*
 * #250 — footgun 1: locale allowlist-vs-disk mismatch.
 *
 * An app may configure (and `LocaleSwitcher`'s DEFAULT_LABELS advertises) the
 * hyphenated `pt-BR`, which passes the allowlist `in_array` check, but the
 * translations live on disk under `pt_BR`. Without normalization `is_dir()`
 * misses the directory and the loader silently falls back to the default
 * locale — the user "switches locale" but sees the default translations.
 *
 * The loader must treat `pt-BR` and `pt_BR` as the same on-disk locale.
 */

it('resolves a hyphenated locale to the underscored on-disk translations', function (): void {
    $loader = app(TranslationLoader::class);

    $payload = $loader->loadForLocale('pt-BR');

    // Must be the pt_BR translations, NOT the en default fallback.
    expect($payload['arqel']['actions']['create'])->toBe('Criar')
        ->and($payload['arqel']['nav']['logout'])->toBe('Sair');
});

it('loads the same translations for pt-BR and pt_BR', function (): void {
    $loader = app(TranslationLoader::class);

    expect($loader->loadForLocale('pt-BR'))
        ->toBe($loader->loadForLocale('pt_BR'));
});

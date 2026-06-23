<?php

declare(strict_types=1);

use Arqel\Core\Http\Middleware\SetLocaleMiddleware;
use Arqel\Core\I18n\TranslationLoader;
use Illuminate\Http\Request;

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

it('matches a hyphenated candidate against an underscored allowlist entry', function (): void {
    $loader = app(TranslationLoader::class);

    // candidate hyphenated, allowlist underscored → returns the allowlist form
    expect($loader->matchAvailable('pt-BR', ['en', 'pt_BR']))->toBe('pt_BR')
        // candidate underscored, allowlist hyphenated → returns the allowlist form
        ->and($loader->matchAvailable('pt_BR', ['en', 'pt-BR']))->toBe('pt-BR')
        // no normalized match → null (rejected by the allowlist)
        ->and($loader->matchAvailable('es', ['en', 'pt_BR']))->toBeNull()
        // empty candidate → null
        ->and($loader->matchAvailable('', ['en', 'pt_BR']))->toBeNull();
});

it('drops configured locales that have no on-disk lang directory', function (): void {
    // 'es' has no resources/lang/es dir → must not be offered by the switcher.
    config()->set('arqel.i18n.locales', ['en', 'pt_BR', 'es']);
    $loader = app(TranslationLoader::class);

    expect($loader->availableLocales())->toBe(['en', 'pt_BR']);
});

it('keeps a hyphenated configured locale that resolves on disk', function (): void {
    config()->set('arqel.i18n.locales', ['en', 'pt-BR']);
    $loader = app(TranslationLoader::class);

    // pt-BR normalizes to the pt_BR dir, so it stays available in its config form.
    expect($loader->availableLocales())->toBe(['en', 'pt-BR']);
});

it('reaches a hyphenated config locale through the SetLocale middleware Accept-Language path', function (): void {
    config()->set('arqel.i18n.locales', ['en', 'pt-BR']);

    $middleware = app(SetLocaleMiddleware::class);
    $request = Request::create('/admin', 'GET', [], [], [], [
        'HTTP_ACCEPT_LANGUAGE' => 'pt-BR,en;q=0.8',
    ]);
    $request->setLaravelSession(app('session.store'));

    $middleware->handle($request, fn () => response('ok'));

    // Header pt-BR normalizes to pt_BR but must match the hyphenated allowlist
    // entry and resolve end to end, not fall back to the default.
    expect(app()->getLocale())->toBe('pt-BR');
});

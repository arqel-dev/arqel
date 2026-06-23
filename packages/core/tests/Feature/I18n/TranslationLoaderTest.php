<?php

declare(strict_types=1);

use Arqel\Core\I18n\TranslationLoader;

it('loads translations for the en locale', function (): void {
    $loader = app(TranslationLoader::class);

    $payload = $loader->loadForLocale('en');

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKey('arqel')
        ->and($payload['arqel'])->toHaveKey('actions')
        ->and($payload['arqel']['actions']['create'])->toBe('Create');
});

it('loads translations for the pt_BR locale', function (): void {
    $loader = app(TranslationLoader::class);

    $payload = $loader->loadForLocale('pt_BR');

    expect($payload['arqel']['actions']['create'])->toBe('Criar')
        ->and($payload['arqel']['nav']['logout'])->toBe('Sair');
});

it('merges the validation lang file so its strings reach the JS dictionary', function (): void {
    $loader = app(TranslationLoader::class);

    $en = $loader->loadForLocale('en');
    expect($en)->toHaveKey('validation')
        ->and($en['validation']['failed'])
        ->toBe('The submitted data is invalid. Please review the highlighted fields.');

    $pt = $loader->loadForLocale('pt_BR');
    expect($pt['validation']['failed'])
        ->toBe('Os dados enviados são inválidos. Revise os campos destacados.');
});

it('loads the widgets and palette chrome namespaces', function (): void {
    $loader = app(TranslationLoader::class);

    $en = $loader->loadForLocale('en');
    $ptBr = $loader->loadForLocale('pt_BR');

    expect($en)->toHaveKey('widgets')
        ->and($en['widgets']['table']['see_all'])->toBe('See all →')
        ->and($en['widgets']['unknown_type'])->toBe('Widget type :type not registered')
        ->and($en['palette']['placeholder'])->toBe('Type a command…')
        ->and($ptBr['widgets']['table']['see_all'])->toBe('Ver todos →')
        ->and($ptBr['palette']['placeholder'])->toBe('Digite um comando…');
});

it('falls back to the default locale when target locale is missing', function (): void {
    config()->set('arqel.i18n.default', 'en');
    $loader = app(TranslationLoader::class);

    $payload = $loader->loadForLocale('xx_YY');

    expect($payload['arqel']['actions']['create'])->toBe('Create');
});

it('returns the default available locales', function (): void {
    $loader = app(TranslationLoader::class);

    expect($loader->availableLocales())->toBe(['en', 'pt_BR']);
});

it('honours configured available locales that resolve on disk', function (): void {
    // 'fr' has no resources/lang/fr directory, so it is dropped: the switcher
    // must only offer locales that actually load translations.
    config()->set('arqel.i18n.locales', ['en', 'fr']);
    $loader = app(TranslationLoader::class);

    expect($loader->availableLocales())->toBe(['en']);
});

it('reads default locale from config or app fallback', function (): void {
    config()->set('arqel.i18n.default', 'pt_BR');
    $loader = app(TranslationLoader::class);

    expect($loader->defaultLocale())->toBe('pt_BR');

    config()->set('arqel.i18n.default', null);
    config()->set('app.locale', 'en');

    expect($loader->defaultLocale())->toBe('en');
});

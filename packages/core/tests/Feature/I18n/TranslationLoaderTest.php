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

it('honours configured available locales', function (): void {
    config()->set('arqel.i18n.locales', ['en', 'fr']);
    $loader = app(TranslationLoader::class);

    expect($loader->availableLocales())->toBe(['en', 'fr']);
});

it('reads default locale from config or app fallback', function (): void {
    config()->set('arqel.i18n.default', 'pt_BR');
    $loader = app(TranslationLoader::class);

    expect($loader->defaultLocale())->toBe('pt_BR');

    config()->set('arqel.i18n.default', null);
    config()->set('app.locale', 'en');

    expect($loader->defaultLocale())->toBe('en');
});

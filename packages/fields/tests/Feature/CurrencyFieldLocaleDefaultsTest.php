<?php

declare(strict_types=1);

use Arqel\Fields\Types\CurrencyField;
use Illuminate\Support\Facades\App;

it('keeps en-US currency defaults under the en locale', function (): void {
    App::setLocale('en');

    $props = (new CurrencyField('price'))->getTypeSpecificProps();

    expect($props['prefix'])->toBe('$')
        ->and($props['thousandsSeparator'])->toBe(',')
        ->and($props['decimalSeparator'])->toBe('.')
        ->and($props['decimals'])->toBe(2);
});

it('derives R$ / . / , currency defaults under the pt_BR locale', function (): void {
    App::setLocale('pt_BR');

    $props = (new CurrencyField('price'))->getTypeSpecificProps();

    expect($props['prefix'])->toBe('R$')
        ->and($props['thousandsSeparator'])->toBe('.')
        ->and($props['decimalSeparator'])->toBe(',')
        ->and($props['decimals'])->toBe(2);
});

it('lets explicit overrides win over the active locale defaults', function (): void {
    App::setLocale('pt_BR');

    $props = (new CurrencyField('price'))
        ->prefix('US$')
        ->thousandsSeparator(' ')
        ->decimalSeparator('.')
        ->getTypeSpecificProps();

    expect($props['prefix'])->toBe('US$')
        ->and($props['thousandsSeparator'])->toBe(' ')
        ->and($props['decimalSeparator'])->toBe('.');
});

it('falls back to en-US shape for an unknown locale', function (): void {
    App::setLocale('zz');

    $props = (new CurrencyField('price'))->getTypeSpecificProps();

    expect($props['thousandsSeparator'])->toBe(',')
        ->and($props['decimalSeparator'])->toBe('.');
});

it('derives comma-decimal separators for dot-grouping locales beyond the original table', function (string $locale): void {
    App::setLocale($locale);

    $props = (new CurrencyField('price'))->getTypeSpecificProps();

    expect($props['thousandsSeparator'])->toBe('.')
        ->and($props['decimalSeparator'])->toBe(',');
})->with(['da', 'id', 'tr', 'vi', 'ro', 'bg']);

it('derives space-grouping comma-decimal separators for slavic/nordic locales', function (string $locale): void {
    App::setLocale($locale);

    $props = (new CurrencyField('price'))->getTypeSpecificProps();

    expect($props['thousandsSeparator'])->toBe(' ')
        ->and($props['decimalSeparator'])->toBe(',');
})->with(['ru', 'pl', 'sv', 'cs', 'sk', 'hu', 'uk', 'fi', 'nb', 'no', 'lt', 'lv', 'et']);

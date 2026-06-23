<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Feature\Pulse;

use Illuminate\Support\Number;
use NumberFormatter;

/**
 * The Pulse blade cards format numbers via Number::format(locale: ...) and
 * pluralise the resources caption via trans_choice('arqel::pulse...'). The
 * Pulse blade components themselves are not registered in this package's test
 * runtime (laravel/pulse is optional), so these tests exercise the exact
 * locale-aware helpers + lang keys the cards delegate to.
 */
/**
 * ICU locale data beyond en_US is stripped in some minimal runtimes (Alpine),
 * making cross-locale formatting differences unobservable. Detect that so the
 * grouping/decimal assertions only run where ICU can actually localise.
 */
function icuHasNonEnglishLocaleData(): bool
{
    if (! class_exists(NumberFormatter::class)) {
        return false;
    }

    return (new NumberFormatter('pt_BR', NumberFormatter::DECIMAL))->format(1234567) === '1.234.567';
}

it('formats Pulse counts via the locale-aware Number helper (pt_BR uses dot)', function (): void {
    // The cards call Number::format(..., locale: app()->getLocale()) instead of
    // number_format(); en keeps comma grouping as before (no regression).
    expect(Number::format(1234567, locale: 'en'))->toBe('1,234,567');

    if (! icuHasNonEnglishLocaleData()) {
        $this->markTestSkipped('ICU locale data limited to en_US in this runtime.');
    }

    expect(Number::format(1234567, locale: 'pt_BR'))->toBe('1.234.567');
});

it('formats the AI cost as locale-aware USD currency, not a hardcoded $', function (): void {
    // en keeps the leading $ and dot decimal — identical to the previous output.
    expect(Number::currency(0.0012, 'USD', 'en', 4))->toContain('0.0012');

    if (! icuHasNonEnglishLocaleData()) {
        $this->markTestSkipped('ICU locale data limited to en_US in this runtime.');
    }

    $ptBr = Number::currency(0.0012, 'USD', 'pt_BR', 4);
    expect($ptBr)->toContain('0,0012')
        ->and($ptBr)->not->toBe(Number::currency(0.0012, 'USD', 'en', 4));
});

it('pluralises the resources caption via trans_choice in both locales', function (): void {
    app()->setLocale('en');
    expect(trans_choice('arqel::pulse.resources.registered', 1))->toBe('Resource registered')
        ->and(trans_choice('arqel::pulse.resources.registered', 5))->toBe('Resources registered');

    app()->setLocale('pt_BR');
    expect(trans_choice('arqel::pulse.resources.registered', 1))->toBe('Resource registrado')
        ->and(trans_choice('arqel::pulse.resources.registered', 5))->toBe('Resources registrados');
});

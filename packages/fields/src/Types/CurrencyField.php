<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

use Illuminate\Support\Facades\App;
use NumberFormatter;
use Throwable;

/**
 * Currency input.
 *
 * Inherits min/max/step from `NumberField` and adds prefix/suffix
 * and locale-style separators.
 *
 * Symbol and separators default to the application's active locale
 * (resolved lazily at serialization time, so the request locale
 * applies): a `pt_BR` request yields `R$` / `.` / `,`, an `en`
 * request yields `$` / `,` / `.`. Defaults are derived through
 * `ext-intl`'s `NumberFormatter`; when the extension is absent we
 * fall back to en-US-shaped values. Explicit `prefix()` /
 * `thousandsSeparator()` / `decimalSeparator()` calls always win.
 *
 * `decimals(2)` only drives display; database casting is the
 * application's responsibility (`$casts = ['price' => 'decimal:2']`).
 */
final class CurrencyField extends NumberField
{
    protected string $type = 'currency';

    protected string $component = 'CurrencyInput';

    /**
     * Null means "derive from the active locale lazily". An explicit
     * setter call pins the value and disables locale derivation.
     */
    protected ?string $prefix = null;

    protected string $suffix = '';

    protected ?string $thousandsSeparator = null;

    protected ?string $decimalSeparator = null;

    protected ?int $decimals = 2;

    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function suffix(string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function thousandsSeparator(string $separator): static
    {
        $this->thousandsSeparator = $separator;

        return $this;
    }

    public function decimalSeparator(string $separator): static
    {
        $this->decimalSeparator = $separator;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        $defaults = $this->localeDefaults();

        return array_filter([
            ...parent::getTypeSpecificProps(),
            'prefix' => $this->prefix ?? $defaults['prefix'],
            'suffix' => $this->suffix !== '' ? $this->suffix : null,
            'thousandsSeparator' => $this->thousandsSeparator ?? $defaults['thousandsSeparator'],
            'decimalSeparator' => $this->decimalSeparator ?? $defaults['decimalSeparator'],
        ], fn ($value) => $value !== null);
    }

    /**
     * Resolve symbol/separators for the application's active locale.
     *
     * The currency SYMBOL is read from `ext-intl`'s `NumberFormatter`
     * when available so any locale's symbol is supported without a
     * hardcoded table (e.g. `pt_BR` -> `R$`, `en` -> `$`).
     *
     * The SEPARATORS are resolved from a small, explicit locale-prefix
     * table rather than from ICU. ICU's separator data is environment
     * dependent (incomplete locale data on some builds silently falls
     * back to the root locale, which would emit en-US separators for a
     * `pt_BR` request); the table makes the common locales deterministic
     * across every install. Unknown locales fall back to en-US shape.
     *
     * @return array{prefix: string, thousandsSeparator: string, decimalSeparator: string}
     */
    private function localeDefaults(): array
    {
        $fallback = [
            'prefix' => '$',
            'thousandsSeparator' => ',',
            'decimalSeparator' => '.',
        ];

        $locale = $this->activeLocale();
        $separators = $this->separatorsForLocale($locale);

        $symbol = $separators['prefix'];
        if (class_exists(NumberFormatter::class)) {
            try {
                $currency = new NumberFormatter($locale, NumberFormatter::CURRENCY);
                // `getSymbol()` is typed `string` by the PHPStan stubs but
                // can return `false` at runtime on failure; cast to string so
                // both the stubbed and the real signatures are handled.
                $resolved = (string) $currency->getSymbol(NumberFormatter::CURRENCY_SYMBOL);

                // `¤` is ICU's generic currency placeholder, returned when
                // the locale has no concrete symbol (e.g. a bare language
                // tag like `en`). Reject it so the table fallback applies.
                if ($resolved !== '' && $resolved !== '¤') {
                    $symbol = $resolved;
                }
            } catch (Throwable) {
                // Invalid/unsupported locale tag — keep the table fallback.
            }
        }

        return [
            'prefix' => $symbol !== '' ? $symbol : $fallback['prefix'],
            'thousandsSeparator' => $separators['thousandsSeparator'],
            'decimalSeparator' => $separators['decimalSeparator'],
        ];
    }

    /**
     * Deterministic symbol/separator shape per locale prefix. Matched on
     * the language so the common comma-decimal locales (dot-grouped:
     * `pt`/`de`/`es`/`it`/`nl`/`da`/`id`/`tr`/…; space-grouped:
     * `fr`/`ru`/`pl`/`sv`/`cs`/`hu`/`uk`/…) render the right numeric
     * shape, while `en` keeps dot-decimal / comma-grouping. Unlisted
     * locales fall back to the en-US shape. The `prefix` here is only a
     * fallback symbol used when `ext-intl` is unavailable.
     *
     * @return array{prefix: string, thousandsSeparator: string, decimalSeparator: string}
     */
    private function separatorsForLocale(string $locale): array
    {
        $language = strtolower(explode('_', $locale)[0]);

        $commaDecimal = [
            'thousandsSeparator' => '.',
            'decimalSeparator' => ',',
        ];

        // Space-grouped, comma-decimal locales (NBSP/thin-space grouping
        // collapsed to a regular space for the JS round-trip).
        $spaceDecimal = [
            'thousandsSeparator' => ' ',
            'decimalSeparator' => ',',
        ];

        $map = [
            // Dot-grouping, comma-decimal.
            'pt' => ['prefix' => 'R$', ...$commaDecimal],
            'de' => ['prefix' => '€', ...$commaDecimal],
            'es' => ['prefix' => '€', ...$commaDecimal],
            'it' => ['prefix' => '€', ...$commaDecimal],
            'nl' => ['prefix' => '€', ...$commaDecimal],
            'da' => ['prefix' => 'kr', ...$commaDecimal],
            'id' => ['prefix' => 'Rp', ...$commaDecimal],
            'tr' => ['prefix' => '₺', ...$commaDecimal],
            'vi' => ['prefix' => '₫', ...$commaDecimal],
            'el' => ['prefix' => '€', ...$commaDecimal],
            'ro' => ['prefix' => 'lei', ...$commaDecimal],
            'bg' => ['prefix' => 'лв', ...$commaDecimal],
            'hr' => ['prefix' => '€', ...$commaDecimal],
            'sl' => ['prefix' => '€', ...$commaDecimal],
            'sr' => ['prefix' => 'дин', ...$commaDecimal],
            // Space-grouping, comma-decimal.
            'fr' => ['prefix' => '€', ...$spaceDecimal],
            'ru' => ['prefix' => '₽', ...$spaceDecimal],
            'pl' => ['prefix' => 'zł', ...$spaceDecimal],
            'sv' => ['prefix' => 'kr', ...$spaceDecimal],
            'nb' => ['prefix' => 'kr', ...$spaceDecimal],
            'nn' => ['prefix' => 'kr', ...$spaceDecimal],
            'no' => ['prefix' => 'kr', ...$spaceDecimal],
            'fi' => ['prefix' => '€', ...$spaceDecimal],
            'cs' => ['prefix' => 'Kč', ...$spaceDecimal],
            'sk' => ['prefix' => '€', ...$spaceDecimal],
            'hu' => ['prefix' => 'Ft', ...$spaceDecimal],
            'uk' => ['prefix' => '₴', ...$spaceDecimal],
            'et' => ['prefix' => '€', ...$spaceDecimal],
            'lv' => ['prefix' => '€', ...$spaceDecimal],
            'lt' => ['prefix' => '€', ...$spaceDecimal],
            'kk' => ['prefix' => '₸', ...$spaceDecimal],
        ];

        return $map[$language] ?? [
            'prefix' => '$',
            'thousandsSeparator' => ',',
            'decimalSeparator' => '.',
        ];
    }

    /**
     * Map Laravel's underscore locale (`pt_BR`) to a BCP-47 tag
     * (`pt_BR` is already accepted by ICU, but we normalise so any
     * dash/underscore form resolves). Falls back to `en_US` when no
     * locale is resolvable, matching the prior hardcoded shape.
     */
    private function activeLocale(): string
    {
        $locale = 'en_US';

        if (class_exists(App::class) && App::getFacadeApplication() !== null) {
            try {
                $resolved = App::getLocale();
                if ($resolved !== '') {
                    $locale = str_replace('-', '_', $resolved);
                }
            } catch (Throwable) {
                // App booted without a translator (bare unit context):
                // keep the en-US default rather than blowing up rendering.
            }
        }

        return $locale;
    }
}

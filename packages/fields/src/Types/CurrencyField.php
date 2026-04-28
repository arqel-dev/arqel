<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

/**
 * Currency input.
 *
 * Inherits min/max/step from `NumberField` and adds prefix/suffix
 * and locale-style separators. Defaults are en-US shaped
 * (`$`, `,`, `.`); use `Field::priceBRL` (macro example) for
 * PT-BR.
 *
 * `decimals(2)` only drives display; database casting is the
 * application's responsibility (`$casts = ['price' => 'decimal:2']`).
 */
final class CurrencyField extends NumberField
{
    protected string $type = 'currency';

    protected string $component = 'CurrencyInput';

    protected string $prefix = '$';

    protected string $suffix = '';

    protected string $thousandsSeparator = ',';

    protected string $decimalSeparator = '.';

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
        return array_filter([
            ...parent::getTypeSpecificProps(),
            'prefix' => $this->prefix,
            'suffix' => $this->suffix !== '' ? $this->suffix : null,
            'thousandsSeparator' => $this->thousandsSeparator,
            'decimalSeparator' => $this->decimalSeparator,
        ], fn ($value) => $value !== null);
    }
}

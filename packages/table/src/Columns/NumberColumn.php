<?php

declare(strict_types=1);

namespace Arqel\Table\Columns;

use Arqel\Table\Column;

/**
 * Numeric cell with optional money formatting, prefix, and suffix.
 *
 * `money(currency)` is sugar over prefix + decimal grouping; the
 * React side picks the right Intl.NumberFormat.
 */
final class NumberColumn extends Column
{
    protected string $type = 'number';

    protected ?string $currency = null;

    protected ?string $prefix = null;

    protected ?string $suffix = null;

    protected ?int $decimals = null;

    public function money(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

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

    public function decimals(int $decimals): static
    {
        $this->decimals = $decimals;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return array_filter([
            'currency' => $this->currency,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'decimals' => $this->decimals,
        ], fn ($value) => $value !== null);
    }
}

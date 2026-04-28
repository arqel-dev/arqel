<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

use Arqel\Fields\Field;

/**
 * Numeric input.
 *
 * Subclassed by `CurrencyField`. `integer()` flips the input into
 * integer-only mode and contributes an `integer` validation rule
 * via `getDefaultRules()`; `decimals()` drives client-side
 * formatting.
 */
class NumberField extends Field
{
    protected string $type = 'number';

    protected string $component = 'NumberInput';

    protected int|float|null $min = null;

    protected int|float|null $max = null;

    protected int|float|null $step = null;

    protected bool $integer = false;

    protected ?int $decimals = null;

    public function min(int|float $min): static
    {
        $this->min = $min;

        return $this;
    }

    public function max(int|float $max): static
    {
        $this->max = $max;

        return $this;
    }

    public function step(int|float $step): static
    {
        $this->step = $step;

        return $this;
    }

    public function integer(bool $integer = true): static
    {
        $this->integer = $integer;

        return $this;
    }

    public function decimals(int $decimals): static
    {
        $this->decimals = $decimals;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getDefaultRules(): array
    {
        $rules = ['numeric'];

        if ($this->integer) {
            $rules = ['integer'];
        }

        if ($this->min !== null) {
            $rules[] = "min:{$this->min}";
        }

        if ($this->max !== null) {
            $rules[] = "max:{$this->max}";
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return array_filter([
            'min' => $this->min,
            'max' => $this->max,
            'step' => $this->step,
            'integer' => $this->integer ?: null,
            'decimals' => $this->decimals,
        ], fn ($value) => $value !== null);
    }
}

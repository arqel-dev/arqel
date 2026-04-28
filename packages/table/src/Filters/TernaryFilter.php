<?php

declare(strict_types=1);

namespace Arqel\Table\Filters;

use Arqel\Table\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Three-state filter (true / false / all).
 *
 * Useful for boolean columns where "all" should leave the query
 * untouched. Coerces the loose request value (`'true'`, `'false'`,
 * `1`, `0`, …) into a strict bool before applying.
 */
final class TernaryFilter extends Filter
{
    public const string STATE_ALL = 'all';

    public const string STATE_TRUE = 'true';

    public const string STATE_FALSE = 'false';

    protected string $type = 'ternary';

    protected ?string $column = null;

    protected ?string $trueLabel = null;

    protected ?string $falseLabel = null;

    public function column(string $column): static
    {
        $this->column = $column;

        return $this;
    }

    public function getColumn(): string
    {
        return $this->column ?? $this->name;
    }

    public function trueLabel(string $label): static
    {
        $this->trueLabel = $label;

        return $this;
    }

    public function falseLabel(string $label): static
    {
        $this->falseLabel = $label;

        return $this;
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $query
     *
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applyDefault(Builder $query, mixed $value): Builder
    {
        $bool = $this->coerce($value);
        if ($bool === null) {
            return $query;
        }

        /** @var Builder<\Illuminate\Database\Eloquent\Model> $result */
        $result = $query->where($this->getColumn(), $bool);

        return $result;
    }

    protected function coerce(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === self::STATE_TRUE || $value === '1' || $value === 1) {
            return true;
        }

        if ($value === self::STATE_FALSE || $value === '0' || $value === 0) {
            return false;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return array_filter([
            'column' => $this->getColumn(),
            'trueLabel' => $this->trueLabel,
            'falseLabel' => $this->falseLabel,
        ], fn ($v) => $v !== null);
    }
}

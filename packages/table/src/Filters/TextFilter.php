<?php

declare(strict_types=1);

namespace Arqel\Table\Filters;

use Arqel\Table\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Free-text filter applying `LIKE %value%` against the column.
 */
final class TextFilter extends Filter
{
    protected string $type = 'text';

    protected ?string $column = null;

    public function column(string $column): static
    {
        $this->column = $column;

        return $this;
    }

    public function getColumn(): string
    {
        return $this->column ?? $this->name;
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $query
     *
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applyDefault(Builder $query, mixed $value): Builder
    {
        if (! is_string($value) || $value === '') {
            return $query;
        }

        /** @var Builder<\Illuminate\Database\Eloquent\Model> $result */
        $result = $query->where($this->getColumn(), 'LIKE', '%'.$value.'%');

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'column' => $this->getColumn(),
        ];
    }
}

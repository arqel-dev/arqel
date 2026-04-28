<?php

declare(strict_types=1);

namespace Arqel\Table\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * Multi-value select filter. Same option modes as SelectFilter,
 * applies via `whereIn()`.
 */
final class MultiSelectFilter extends SelectFilter
{
    protected string $type = 'multiSelect';

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $query
     *
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applyDefault(Builder $query, mixed $value): Builder
    {
        if (! is_array($value) || $value === []) {
            return $query;
        }

        /** @var Builder<\Illuminate\Database\Eloquent\Model> $result */
        $result = $query->whereIn($this->getColumn(), $value);

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Table\Filters\Constraints;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Boolean constraint — `is_true` / `is_false` predicates.
 */
final class BooleanConstraint extends Constraint
{
    protected string $type = 'boolean';

    /**
     * @return array<int, string>
     */
    public function getDefaultOperators(): array
    {
        return ['is_true', 'is_false'];
    }

    /**
     * @param Builder<Model> $query
     */
    public function apply(Builder $query, string $operator, mixed $value, string $method = 'where'): void
    {
        switch ($operator) {
            case 'is_true':
                $query->{$method}($this->field, true);
                break;
            case 'is_false':
                $query->{$method}($this->field, false);
                break;
        }
    }
}

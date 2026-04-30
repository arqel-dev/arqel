<?php

declare(strict_types=1);

namespace Arqel\Table\Filters\Constraints;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Text constraint — string equality, LIKE, prefix/suffix matching.
 */
final class TextConstraint extends Constraint
{
    protected string $type = 'text';

    /**
     * @return array<int, string>
     */
    public function getDefaultOperators(): array
    {
        return ['equals', 'not_equals', 'contains', 'starts_with', 'ends_with'];
    }

    /**
     * @param Builder<Model> $query
     */
    public function apply(Builder $query, string $operator, mixed $value, string $method = 'where'): void
    {
        $stringValue = is_scalar($value) ? (string) $value : '';

        switch ($operator) {
            case 'equals':
                $query->{$method}($this->field, '=', $stringValue);
                break;
            case 'not_equals':
                $query->{$method}($this->field, '!=', $stringValue);
                break;
            case 'contains':
                $query->{$method}($this->field, 'LIKE', '%'.$stringValue.'%');
                break;
            case 'starts_with':
                $query->{$method}($this->field, 'LIKE', $stringValue.'%');
                break;
            case 'ends_with':
                $query->{$method}($this->field, 'LIKE', '%'.$stringValue);
                break;
        }
    }
}

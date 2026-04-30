<?php

declare(strict_types=1);

namespace Arqel\Table\Filters\Constraints;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Number constraint — comparison and range operators.
 */
final class NumberConstraint extends Constraint
{
    protected string $type = 'number';

    /**
     * @return array<int, string>
     */
    public function getDefaultOperators(): array
    {
        return ['=', '!=', '>', '<', '>=', '<=', 'between'];
    }

    /**
     * @param Builder<Model> $query
     */
    public function apply(Builder $query, string $operator, mixed $value, string $method = 'where'): void
    {
        if ($operator === 'between') {
            if (! is_array($value) || count($value) !== 2) {
                throw new InvalidArgumentException(
                    'NumberConstraint::between expects array [min, max], got '.gettype($value),
                );
            }

            $min = $this->castNumeric($value[array_keys($value)[0]]);
            $max = $this->castNumeric($value[array_keys($value)[1]]);

            $betweenMethod = $method === 'orWhere' ? 'orWhereBetween' : 'whereBetween';
            $query->{$betweenMethod}($this->field, [$min, $max]);

            return;
        }

        $numeric = $this->castNumeric($value);

        switch ($operator) {
            case '=':
            case '!=':
            case '>':
            case '<':
            case '>=':
            case '<=':
                $query->{$method}($this->field, $operator, $numeric);
                break;
        }
    }

    private function castNumeric(mixed $value): float|int
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException(
                'NumberConstraint expects numeric value, got '.(is_scalar($value) ? (string) $value : gettype($value)),
            );
        }

        $float = (float) $value;

        return $float === (float) (int) $float ? (int) $float : $float;
    }
}

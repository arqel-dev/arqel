<?php

declare(strict_types=1);

namespace Arqel\Table\Filters\Constraints;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Date constraint — equality, before/after, and range operators.
 */
final class DateConstraint extends Constraint
{
    protected string $type = 'date';

    /**
     * @return array<int, string>
     */
    public function getDefaultOperators(): array
    {
        return ['=', 'before', 'after', 'between'];
    }

    /**
     * @param Builder<Model> $query
     */
    public function apply(Builder $query, string $operator, mixed $value, string $method = 'where'): void
    {
        if ($operator === 'between') {
            if (! is_array($value) || count($value) !== 2) {
                throw new InvalidArgumentException(
                    'DateConstraint::between expects array [from, to], got '.gettype($value),
                );
            }

            $keys = array_keys($value);
            $from = $this->parseDate($value[$keys[0]]);
            $to = $this->parseDate($value[$keys[1]]);

            $betweenMethod = $method === 'orWhere' ? 'orWhereBetween' : 'whereBetween';
            $query->{$betweenMethod}($this->field, [$from, $to]);

            return;
        }

        $parsed = $this->parseDate($value);

        switch ($operator) {
            case '=':
                $query->{$method}($this->field, '=', $parsed);
                break;
            case 'before':
                $query->{$method}($this->field, '<', $parsed);
                break;
            case 'after':
                $query->{$method}($this->field, '>', $parsed);
                break;
        }
    }

    private function parseDate(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            throw new InvalidArgumentException(
                'DateConstraint expects parseable date string, got '.gettype($value),
            );
        }

        try {
            return Carbon::parse((string) $value)->toDateTimeString();
        } catch (Exception $e) {
            throw new InvalidArgumentException(
                'DateConstraint received invalid date: '.(string) $value,
                previous: $e,
            );
        }
    }
}

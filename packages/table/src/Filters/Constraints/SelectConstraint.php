<?php

declare(strict_types=1);

namespace Arqel\Table\Filters\Constraints;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Select constraint — equality and set membership against a fixed
 * (or lazily resolved) options list.
 */
final class SelectConstraint extends Constraint
{
    protected string $type = 'select';

    /** @var array<int|string, mixed>|Closure */
    protected array|Closure $options = [];

    /**
     * @param array<int|string, mixed>|Closure $options
     */
    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function resolveOptions(): array
    {
        if ($this->options instanceof Closure) {
            $resolved = ($this->options)();

            return is_array($resolved) ? $resolved : [];
        }

        return $this->options;
    }

    /**
     * @return array<int, string>
     */
    public function getDefaultOperators(): array
    {
        return ['equals', 'not_equals', 'in', 'not_in'];
    }

    /**
     * @param Builder<Model> $query
     */
    public function apply(Builder $query, string $operator, mixed $value, string $method = 'where'): void
    {
        switch ($operator) {
            case 'equals':
                $query->{$method}($this->field, '=', $value);
                break;
            case 'not_equals':
                $query->{$method}($this->field, '!=', $value);
                break;
            case 'in':
                if (! is_array($value)) {
                    return;
                }
                $inMethod = $method === 'orWhere' ? 'orWhereIn' : 'whereIn';
                $query->{$inMethod}($this->field, array_values($value));
                break;
            case 'not_in':
                if (! is_array($value)) {
                    return;
                }
                $notInMethod = $method === 'orWhere' ? 'orWhereNotIn' : 'whereNotIn';
                $query->{$notInMethod}($this->field, array_values($value));
                break;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'options' => $this->resolveOptions(),
        ]);
    }
}

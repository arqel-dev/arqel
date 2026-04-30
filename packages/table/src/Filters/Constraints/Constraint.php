<?php

declare(strict_types=1);

namespace Arqel\Table\Filters\Constraints;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Base contract for a Visual Query Builder constraint.
 *
 * A constraint declares one queryable field — its type (which drives
 * the value-input UI on the React side), the operators it supports,
 * and how to translate `(operator, value)` into an Eloquent Builder
 * call. `QueryBuilderFilter` resolves incoming conditions against the
 * constraint set declared on the filter (whitelist), so unknown
 * fields can never reach the database.
 *
 * Subclasses set `$type` and implement `apply()` + `getDefaultOperators()`.
 */
abstract class Constraint
{
    protected string $type;

    protected string $field;

    protected ?string $label = null;

    /** @var array<int, string> */
    protected array $operators = [];

    final public function __construct(string $field)
    {
        $this->field = $field;
    }

    public static function make(string $field): static
    {
        return new static($field);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @param array<int, string> $operators
     */
    public function operators(array $operators): static
    {
        $this->operators = array_values($operators);

        return $this;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getLabel(): string
    {
        return $this->label ?? Str::headline($this->field);
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<int, string>
     */
    public function getOperators(): array
    {
        return $this->operators === [] ? $this->getDefaultOperators() : $this->operators;
    }

    /**
     * Operators allowed by default for this constraint type.
     *
     * @return array<int, string>
     */
    abstract public function getDefaultOperators(): array;

    /**
     * Apply this constraint to the query.
     *
     * @param Builder<Model> $query
     */
    abstract public function apply(Builder $query, string $operator, mixed $value, string $method = 'where'): void;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'label' => $this->getLabel(),
            'type' => $this->type,
            'operators' => $this->getOperators(),
        ];
    }
}

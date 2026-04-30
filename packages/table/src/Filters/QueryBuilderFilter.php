<?php

declare(strict_types=1);

namespace Arqel\Table\Filters;

use Arqel\Table\Filter;
use Arqel\Table\Filters\Constraints\Constraint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Visual query builder filter — accepts a tree of conditions
 * (`field`, `operator`, `value`) and recursive AND/OR groups,
 * resolves each leaf against a whitelist of `Constraint`s declared
 * on the filter, and applies them to the underlying Eloquent query.
 *
 * Security guarantee: any `field` in the incoming payload that does
 * not match a declared constraint is silently dropped — there is no
 * path from arbitrary user input to a column name.
 *
 * Payload shape:
 *
 * ```jsonc
 * {
 *   "operator": "AND",          // top-level join (default AND)
 *   "conditions": [
 *     { "field": "name",   "operator": "contains", "value": "alice" },
 *     {
 *       "group":     true,
 *       "operator":  "OR",
 *       "conditions": [
 *         { "field": "age", "operator": ">", "value": 18 },
 *         { "field": "age", "operator": "between", "value": [40, 50] }
 *       ]
 *     }
 *   ]
 * }
 * ```
 */
final class QueryBuilderFilter extends Filter
{
    protected string $type = 'queryBuilder';

    /** @var array<int, Constraint> */
    protected array $constraints = [];

    /**
     * @param array<int, mixed> $constraints
     */
    public function constraints(array $constraints): static
    {
        $this->constraints = array_values(array_filter(
            $constraints,
            static fn (mixed $c): bool => $c instanceof Constraint,
        ));

        return $this;
    }

    /**
     * @return array<int, Constraint>
     */
    public function getConstraints(): array
    {
        return $this->constraints;
    }

    /**
     * @param Builder<Model> $query
     *
     * @return Builder<Model>
     */
    public function applyToQuery(Builder $query, mixed $value): Builder
    {
        if (! is_array($value)) {
            return $query;
        }

        $rawConditions = $value['conditions'] ?? null;
        if (! is_array($rawConditions) || $rawConditions === []) {
            return $query;
        }

        $conditions = array_values($rawConditions);
        $topOperator = is_string($value['operator'] ?? null) ? $value['operator'] : 'AND';

        /** @var Builder<Model> $result */
        $result = $query->where(function (Builder $q) use ($conditions, $topOperator): void {
            $this->applyConditions($q, $conditions, $topOperator);
        });

        return $result;
    }

    /**
     * @param Builder<Model> $query
     * @param array<int, mixed> $conditions
     */
    private function applyConditions(Builder $query, array $conditions, string $operator = 'AND'): void
    {
        $method = $operator === 'OR' ? 'orWhere' : 'where';

        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                continue;
            }

            if (isset($condition['group']) && $condition['group']) {
                $rawChildren = $condition['conditions'] ?? null;
                $childConditions = is_array($rawChildren) ? array_values($rawChildren) : [];
                $childOperator = is_string($condition['operator'] ?? null) ? $condition['operator'] : 'AND';

                if ($childConditions === []) {
                    continue;
                }

                $query->{$method}(function (Builder $subQuery) use ($childConditions, $childOperator): void {
                    $this->applyConditions($subQuery, $childConditions, $childOperator);
                });

                continue;
            }

            $field = $condition['field'] ?? null;
            $opName = $condition['operator'] ?? null;

            if (! is_string($field) || ! is_string($opName)) {
                continue;
            }

            $constraint = $this->findConstraint($field);
            if ($constraint === null) {
                // Security: unknown field — silently drop.
                continue;
            }

            if (! in_array($opName, $constraint->getOperators(), true)) {
                continue;
            }

            $constraint->apply($query, $opName, $condition['value'] ?? null, $method);
        }
    }

    private function findConstraint(string $field): ?Constraint
    {
        foreach ($this->constraints as $constraint) {
            if ($constraint->getField() === $field) {
                return $constraint;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'constraints' => array_map(
                static fn (Constraint $c): array => $c->toArray(),
                $this->constraints,
            ),
        ];
    }
}

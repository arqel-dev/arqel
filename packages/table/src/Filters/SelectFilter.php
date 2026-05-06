<?php

declare(strict_types=1);

namespace Arqel\Table\Filters;

use Arqel\Table\Filter;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single-value select filter.
 *
 * Default `applyDefault` issues `where($column, $value)` against
 * the column matching the filter name; apps that filter on a
 * different column override via `column()` or use `apply()`.
 */
class SelectFilter extends Filter
{
    protected string $type = 'select';

    /** @var array<int|string, mixed>|null */
    protected ?array $staticOptions = null;

    protected ?Closure $optionsCallback = null;

    protected ?string $optionsRelation = null;

    protected ?string $optionsRelationDisplay = null;

    protected ?string $column = null;

    /**
     * @param array<int|string, mixed>|Closure $options
     */
    public function options(array|Closure $options): static
    {
        if ($options instanceof Closure) {
            $this->optionsCallback = $options;
            $this->staticOptions = null;
        } else {
            $this->staticOptions = $options;
            $this->optionsCallback = null;
        }
        $this->optionsRelation = null;
        $this->optionsRelationDisplay = null;

        return $this;
    }

    public function optionsRelationship(string $relation, string $display): static
    {
        $this->optionsRelation = $relation;
        $this->optionsRelationDisplay = $display;
        $this->staticOptions = null;
        $this->optionsCallback = null;

        return $this;
    }

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
     * @return array<int|string, mixed>
     */
    public function resolveOptions(): array
    {
        if ($this->staticOptions !== null) {
            return $this->staticOptions;
        }

        if ($this->optionsCallback !== null) {
            $resolved = ($this->optionsCallback)();

            return is_array($resolved) ? $resolved : [];
        }

        return [];
    }

    public function getOptionsRelation(): ?string
    {
        return $this->optionsRelation;
    }

    public function getOptionsRelationDisplay(): ?string
    {
        return $this->optionsRelationDisplay;
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $query
     *
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applyDefault(Builder $query, mixed $value): Builder
    {
        if ($value === null || $value === '') {
            return $query;
        }

        /** @var Builder<\Illuminate\Database\Eloquent\Model> $result */
        $result = $query->where($this->getColumn(), $value);

        return $result;
    }

    /**
     * Normalize an associative `[value => label]` array into the
     * canonical `[{value, label}]` shape that `@arqel-dev/types`
     * declares for `SelectFilterProps.options`. Closures and
     * relationship-resolved options flow through the same path.
     *
     * @param array<int|string, mixed> $options
     *
     * @return array<int, array{value: int|string, label: string}>
     */
    private static function normalizeOptions(array $options): array
    {
        $normalized = [];
        foreach ($options as $value => $label) {
            $normalized[] = [
                'value' => $value,
                'label' => is_scalar($label) ? (string) $label : (string) $value,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'options' => self::normalizeOptions($this->resolveOptions()),
            'optionsRelation' => $this->optionsRelation,
            'column' => $this->getColumn(),
        ];
    }
}

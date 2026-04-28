<?php

declare(strict_types=1);

namespace Arqel\Table\Filters;

use Arqel\Table\Filter;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Date range filter — accepts `{from, to}` payloads from the React
 * range picker and applies `whereBetween` to the configured column.
 *
 * `minDate`/`maxDate` constrain the picker; both accept literal
 * strings or Closures (resolved at serialise time).
 */
final class DateRangeFilter extends Filter
{
    protected string $type = 'dateRange';

    protected ?string $column = null;

    protected string|Closure|null $minDate = null;

    protected string|Closure|null $maxDate = null;

    public function column(string $column): static
    {
        $this->column = $column;

        return $this;
    }

    public function getColumn(): string
    {
        return $this->column ?? $this->name;
    }

    public function minDate(string|Closure $date): static
    {
        $this->minDate = $date;

        return $this;
    }

    public function maxDate(string|Closure $date): static
    {
        $this->maxDate = $date;

        return $this;
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $query
     *
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applyDefault(Builder $query, mixed $value): Builder
    {
        if (! is_array($value)) {
            return $query;
        }

        $from = $value['from'] ?? null;
        $to = $value['to'] ?? null;

        if (is_string($from) && is_string($to) && $from !== '' && $to !== '') {
            /** @var Builder<\Illuminate\Database\Eloquent\Model> $result */
            $result = $query->whereBetween($this->getColumn(), [$from, $to]);

            return $result;
        }

        if (is_string($from) && $from !== '') {
            /** @var Builder<\Illuminate\Database\Eloquent\Model> $result */
            $result = $query->where($this->getColumn(), '>=', $from);

            return $result;
        }

        if (is_string($to) && $to !== '') {
            /** @var Builder<\Illuminate\Database\Eloquent\Model> $result */
            $result = $query->where($this->getColumn(), '<=', $to);

            return $result;
        }

        return $query;
    }

    protected function resolveBound(string|Closure|null $value): ?string
    {
        if ($value instanceof Closure) {
            $resolved = $value();

            return is_string($resolved) ? $resolved : null;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return array_filter([
            'column' => $this->getColumn(),
            'minDate' => $this->resolveBound($this->minDate),
            'maxDate' => $this->resolveBound($this->maxDate),
        ], fn ($v) => $v !== null);
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Table\Summaries;

use Illuminate\Support\Collection;

/**
 * Base class for table group summaries.
 *
 * A Summary is a declarative aggregation that runs over a Collection
 * of records (typically a single group from `Table::buildGroups()`)
 * and returns a scalar (sum, average, count, min, max). The
 * concrete subclasses live alongside this file; static factory
 * helpers (`Summary::sum`, `Summary::avg`, ...) build the matching
 * concrete instance for ergonomic use in the table builder.
 */
abstract class Summary
{
    /**
     * Discriminator emitted in `toArray()`. Subclasses MUST set
     * this (e.g. `'sum'`, `'avg'`, `'count'`, `'min'`, `'max'`).
     */
    protected string $type;

    protected ?string $field;

    protected ?string $label;

    public function __construct(?string $field = null, ?string $label = null)
    {
        $this->field = $field;
        $this->label = $label;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function field(string $field): static
    {
        $this->field = $field;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getField(): ?string
    {
        return $this->field;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * Compute the aggregation against the supplied records.
     *
     * @param Collection<int, mixed> $records
     */
    abstract public function compute(Collection $records): mixed;

    /**
     * Static facade — `Summary::sum('amount')`.
     */
    public static function sum(string $field): SumSummary
    {
        return SumSummary::sum($field);
    }

    /**
     * Static facade — `Summary::avg('amount')`.
     */
    public static function avg(string $field): AvgSummary
    {
        return AvgSummary::avg($field);
    }

    /**
     * Static facade — `Summary::count()` or `Summary::count('field')`.
     */
    public static function count(?string $field = null): CountSummary
    {
        return CountSummary::count($field);
    }

    /**
     * Static facade — `Summary::min('amount')`.
     */
    public static function min(string $field): MinSummary
    {
        return MinSummary::min($field);
    }

    /**
     * Static facade — `Summary::max('amount')`.
     */
    public static function max(string $field): MaxSummary
    {
        return MaxSummary::max($field);
    }

    /**
     * @return array{type: string, field: ?string, label: ?string}
     */
    final public function toArray(): array
    {
        return [
            'type' => $this->type,
            'field' => $this->field,
            'label' => $this->label,
        ];
    }
}

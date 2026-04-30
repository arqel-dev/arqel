<?php

declare(strict_types=1);

namespace Arqel\Widgets\Filters;

use DateTimeInterface;

/**
 * Date-range filter (from/to). The default range is stored as an
 * associative array with `from` and `to` keys, each holding a
 * nullable `DateTimeInterface` (the React side formats them).
 */
final class DateRangeFilter extends Filter
{
    protected string $type = 'date_range';

    protected string $component = 'DateRangeFilter';

    /**
     * Convenience setter that builds the canonical
     * `['from' => DateTimeInterface|null, 'to' => DateTimeInterface|null]`
     * shape and assigns it as the filter default.
     */
    public function defaultRange(?DateTimeInterface $from, ?DateTimeInterface $to): static
    {
        $this->default = ['from' => $from, 'to' => $to];

        return $this;
    }

    protected function getTypeSpecificProps(): array
    {
        return [];
    }
}

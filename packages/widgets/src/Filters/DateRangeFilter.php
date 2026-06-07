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

    /**
     * Format the range endpoints to `Y-m-d` strings so they survive
     * `json_encode` and populate the React `<input type="date">`
     * controls, which read string values (issue #165). A plain
     * `DateTimeInterface` is not `JsonSerializable` and would leak
     * the `{date, timezone_type, timezone}` cast shape instead.
     *
     * @return array{from: ?string, to: ?string}
     */
    protected function resolveDefault(): array
    {
        $default = is_array($this->default) ? $this->default : [];

        $from = $default['from'] ?? null;
        $to = $default['to'] ?? null;

        return [
            'from' => $from instanceof DateTimeInterface ? $from->format('Y-m-d') : null,
            'to' => $to instanceof DateTimeInterface ? $to->format('Y-m-d') : null,
        ];
    }

    protected function getTypeSpecificProps(): array
    {
        return [];
    }
}

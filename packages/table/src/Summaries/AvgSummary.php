<?php

declare(strict_types=1);

namespace Arqel\Table\Summaries;

use Illuminate\Support\Collection;

final class AvgSummary extends Summary
{
    protected string $type = 'avg';

    public static function avg(string $field): self
    {
        return new self($field, 'Average');
    }

    /**
     * @param Collection<int, mixed> $records
     */
    public function compute(Collection $records): mixed
    {
        if ($this->field === null) {
            return 0;
        }

        return $records->avg($this->field) ?? 0;
    }
}

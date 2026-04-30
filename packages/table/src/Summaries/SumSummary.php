<?php

declare(strict_types=1);

namespace Arqel\Table\Summaries;

use Illuminate\Support\Collection;

final class SumSummary extends Summary
{
    protected string $type = 'sum';

    public static function sum(string $field): self
    {
        return new self($field, 'Total');
    }

    /**
     * @param Collection<int, mixed> $records
     */
    public function compute(Collection $records): mixed
    {
        if ($this->field === null) {
            return 0;
        }

        return $records->sum($this->field);
    }
}

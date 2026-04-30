<?php

declare(strict_types=1);

namespace Arqel\Table\Summaries;

use Illuminate\Support\Collection;

final class CountSummary extends Summary
{
    protected string $type = 'count';

    public static function count(?string $field = null): self
    {
        return new self($field, 'Count');
    }

    /**
     * @param Collection<int, mixed> $records
     */
    public function compute(Collection $records): int
    {
        if ($this->field === null) {
            return $records->count();
        }

        return $records->whereNotNull($this->field)->count();
    }
}

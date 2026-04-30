<?php

declare(strict_types=1);

namespace Arqel\Table\Summaries;

use Illuminate\Support\Collection;

final class MinSummary extends Summary
{
    protected string $type = 'min';

    public static function min(string $field): self
    {
        return new self($field, 'Min');
    }

    /**
     * @param Collection<int, mixed> $records
     */
    public function compute(Collection $records): mixed
    {
        if ($this->field === null) {
            return null;
        }

        return $records->min($this->field);
    }
}

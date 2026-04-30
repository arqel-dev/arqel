<?php

declare(strict_types=1);

namespace Arqel\Table\Summaries;

use Illuminate\Support\Collection;

final class MaxSummary extends Summary
{
    protected string $type = 'max';

    public static function max(string $field): self
    {
        return new self($field, 'Max');
    }

    /**
     * @param Collection<int, mixed> $records
     */
    public function compute(Collection $records): mixed
    {
        if ($this->field === null) {
            return null;
        }

        return $records->max($this->field);
    }
}

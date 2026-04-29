<?php

declare(strict_types=1);

namespace Arqel\Widgets\Tests\Fixtures;

use Illuminate\Support\Collection;

/**
 * Builder-shaped object used by TableWidgetTest. Mimics the
 * subset of Eloquent\Builder that TableWidget exercises
 * (`limit()->get()->toArray()`) without requiring pdo_sqlite.
 */
final class FakeBuilder
{
    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function __construct(private array $rows) {}

    public function limit(int $n): self
    {
        $this->rows = array_slice($this->rows, 0, $n);

        return $this;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function get(): Collection
    {
        return new Collection($this->rows);
    }
}

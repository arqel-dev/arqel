<?php

declare(strict_types=1);

namespace Arqel\Export\Tests\Fixtures;

use Arqel\Export\Contracts\RecordsResolver;

final class FakeRecordsResolver implements RecordsResolver
{
    /**
     * @return iterable<int, array<string, mixed>>
     */
    public function resolve(): iterable
    {
        return [
            ['id' => 1, 'name' => 'Alice', 'active' => true],
            ['id' => 2, 'name' => 'Bob', 'active' => false],
            ['id' => 3, 'name' => 'Carol', 'active' => true],
        ];
    }
}

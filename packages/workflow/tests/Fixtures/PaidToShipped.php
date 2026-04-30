<?php

declare(strict_types=1);

namespace Arqel\Workflow\Tests\Fixtures;

final class PaidToShipped
{
    /** @return list<class-string> */
    public static function from(): array
    {
        return [PaidState::class];
    }
}

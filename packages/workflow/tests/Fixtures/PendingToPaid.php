<?php

declare(strict_types=1);

namespace Arqel\Workflow\Tests\Fixtures;

final class PendingToPaid
{
    /** @return list<class-string> */
    public static function from(): array
    {
        return [PendingState::class];
    }
}

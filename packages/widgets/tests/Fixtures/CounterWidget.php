<?php

declare(strict_types=1);

namespace Arqel\Widgets\Tests\Fixtures;

use Arqel\Widgets\Widget;

/**
 * Test-only widget that returns a deterministic payload — keeps
 * Widget tests isolated from real implementations (StatWidget,
 * ChartWidget, etc., which land in WIDGETS-002..005).
 */
final class CounterWidget extends Widget
{
    protected string $type = 'counter';

    protected string $component = 'CounterWidget';

    public int $value = 0;

    public function data(): array
    {
        return ['value' => $this->value];
    }
}

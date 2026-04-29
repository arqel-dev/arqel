<?php

declare(strict_types=1);

namespace Arqel\Widgets\Tests\Fixtures;

use Arqel\Widgets\Widget;

/**
 * Test-only widget whose `data()` payload echoes the current filter
 * map — lets feature tests assert filter passthrough without a real
 * data source.
 */
final class EchoFiltersWidget extends Widget
{
    protected string $type = 'echo';

    protected string $component = 'EchoFiltersWidget';

    public function data(): array
    {
        return ['filters' => $this->filters];
    }
}

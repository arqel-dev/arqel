<?php

declare(strict_types=1);

namespace Arqel\Widgets\Tests\Fixtures;

use Arqel\Widgets\Widget;

/**
 * Test-only widget that echoes the current filter map back through
 * `data()`. Used by Dashboard filter-propagation tests to assert
 * declared defaults are merged into each widget at `resolve()` time.
 */
final class EchoFiltersWidget extends Widget
{
    protected string $type = 'echo_filters';

    protected string $component = 'EchoFiltersWidget';

    public function data(): array
    {
        return ['filters' => $this->getFilters()];
    }
}

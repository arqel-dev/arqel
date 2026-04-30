<?php

declare(strict_types=1);

namespace Arqel\Widgets\Tests\Fixtures;

use Arqel\Widgets\Widget;

/**
 * Test-only widget whose `data()` payload echoes the current filter
 * map back. Used by both `WidgetDataControllerTest` (asserting
 * request-time filter passthrough) and `DashboardFilterPropagationTest`
 * (asserting declared dashboard defaults are merged into each widget).
 */
final class EchoFiltersWidget extends Widget
{
    protected string $type = 'echo';

    protected string $component = 'EchoFiltersWidget';

    public function data(): array
    {
        return ['filters' => $this->getFilters()];
    }
}

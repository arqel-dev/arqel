<?php

declare(strict_types=1);

namespace Arqel\Form\Layout;

use Arqel\Fields\Field;

/**
 * Marker interface for form layout components (Section, Fieldset,
 * Grid, Columns, Group, Tabs).
 *
 * Layout components nest fields and other components, which the
 * `Form` builder flattens for validation and re-traverses for the
 * Inertia payload.
 */
interface Component
{
    /**
     * @return array<int, Component|Field>
     */
    public function getSchema(): array;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}

<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

/**
 * Multi-choice select input.
 *
 * Thin subclass of `SelectField` that flips `$multiple` to true by
 * default and switches to a combobox component. Eloquent's `array`
 * cast is the natural fit for the underlying column.
 */
final class MultiSelectField extends SelectField
{
    protected string $type = 'multiSelect';

    protected string $component = 'MultiSelectInput';

    protected bool $multiple = true;

    protected bool $native = false;
}

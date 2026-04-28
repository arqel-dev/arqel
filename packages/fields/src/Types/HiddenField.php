<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

use Arqel\Fields\Field;

/**
 * Hidden input.
 *
 * Renders as `<input type="hidden">` on the React side and is
 * silently submitted with the form payload — useful for stashing
 * tenant IDs, parent foreign keys, or computed defaults that the
 * user should not edit.
 */
final class HiddenField extends Field
{
    protected string $type = 'hidden';

    protected string $component = 'HiddenInput';
}

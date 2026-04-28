<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

/**
 * Radio-group input.
 *
 * Reuses `SelectField`'s option resolution but renders as inline
 * radio buttons. Search and creatable do not apply.
 */
final class RadioField extends SelectField
{
    protected string $type = 'radio';

    protected string $component = 'RadioInput';

    protected bool $native = false;
}

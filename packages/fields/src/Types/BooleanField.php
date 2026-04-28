<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

use Arqel\Fields\Field;

/**
 * Checkbox-style boolean input.
 *
 * Subclassed by `ToggleField` for the switch-style visual.
 * `inline()` controls whether the label sits next to the checkbox
 * (inline) or above it (stacked).
 */
class BooleanField extends Field
{
    protected string $type = 'boolean';

    protected string $component = 'Checkbox';

    protected mixed $default = false;

    protected bool $inline = false;

    public function inline(bool $inline = true): static
    {
        $this->inline = $inline;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getDefaultRules(): array
    {
        return ['boolean'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'inline' => $this->inline,
        ];
    }
}

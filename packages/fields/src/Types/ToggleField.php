<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

/**
 * Switch-style boolean input.
 *
 * Inherits the boolean default rule from `BooleanField` and adds
 * optional visual customisation: on/off colours and on/off icons
 * that the React Toggle component reads from the serialised props.
 */
final class ToggleField extends BooleanField
{
    protected string $type = 'toggle';

    protected string $component = 'Toggle';

    protected ?string $onColor = null;

    protected ?string $offColor = null;

    protected ?string $onIcon = null;

    protected ?string $offIcon = null;

    public function onColor(string $color): static
    {
        $this->onColor = $color;

        return $this;
    }

    public function offColor(string $color): static
    {
        $this->offColor = $color;

        return $this;
    }

    public function onIcon(string $icon): static
    {
        $this->onIcon = $icon;

        return $this;
    }

    public function offIcon(string $icon): static
    {
        $this->offIcon = $icon;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return array_filter([
            ...parent::getTypeSpecificProps(),
            'onColor' => $this->onColor,
            'offColor' => $this->offColor,
            'onIcon' => $this->onIcon,
            'offIcon' => $this->offIcon,
        ], fn ($value) => $value !== null);
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

use Arqel\Fields\Field;

/**
 * Color picker input.
 *
 * `presets()` defines the swatches the React component shows below
 * the picker; `format()` controls the serialised value shape (hex,
 * rgb, hsl); `alpha()` toggles the opacity slider.
 */
final class ColorField extends Field
{
    public const string FORMAT_HEX = 'hex';

    public const string FORMAT_RGB = 'rgb';

    public const string FORMAT_HSL = 'hsl';

    protected string $type = 'color';

    protected string $component = 'ColorInput';

    /** @var array<int, string> */
    protected array $presets = [];

    protected string $format = self::FORMAT_HEX;

    protected bool $alpha = false;

    /**
     * @param array<int, string> $colors
     */
    public function presets(array $colors): static
    {
        $this->presets = array_values($colors);

        return $this;
    }

    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function alpha(bool $allow = true): static
    {
        $this->alpha = $allow;

        return $this;
    }

    /** @return array<int, string> */
    public function getPresets(): array
    {
        return $this->presets;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function allowsAlpha(): bool
    {
        return $this->alpha;
    }

    /**
     * @return array<int, string>
     */
    public function getDefaultRules(): array
    {
        return ['string'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return array_filter([
            'presets' => $this->presets !== [] ? $this->presets : null,
            'format' => $this->format,
            'alpha' => $this->alpha,
        ], fn ($value) => $value !== null);
    }
}

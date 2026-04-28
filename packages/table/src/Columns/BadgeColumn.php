<?php

declare(strict_types=1);

namespace Arqel\Table\Columns;

/**
 * Coloured badge with optional value→colour and value→icon maps.
 */
final class BadgeColumn extends TextColumn
{
    protected string $type = 'badge';

    /** @var array<string, string> */
    protected array $colors = [];

    /** @var array<string, string> */
    protected array $icons = [];

    /**
     * @param array<string, string> $map
     */
    public function colors(array $map): static
    {
        $this->colors = $map;

        return $this;
    }

    /**
     * @param array<string, string> $map
     */
    public function icons(array $map): static
    {
        $this->icons = $map;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return array_filter([
            ...parent::getTypeSpecificProps(),
            'colors' => $this->colors !== [] ? $this->colors : null,
            'icons' => $this->icons !== [] ? $this->icons : null,
        ], fn ($value) => $value !== null);
    }
}

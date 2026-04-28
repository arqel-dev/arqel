<?php

declare(strict_types=1);

namespace Arqel\Table\Columns;

use Arqel\Table\Column;

/**
 * Boolean cell rendered as an icon pair (default check / x).
 */
final class BooleanColumn extends Column
{
    protected string $type = 'boolean';

    protected string $trueIcon = 'check';

    protected string $falseIcon = 'x';

    protected ?string $trueColor = null;

    protected ?string $falseColor = null;

    public function trueIcon(string $icon): static
    {
        $this->trueIcon = $icon;

        return $this;
    }

    public function falseIcon(string $icon): static
    {
        $this->falseIcon = $icon;

        return $this;
    }

    public function trueColor(string $color): static
    {
        $this->trueColor = $color;

        return $this;
    }

    public function falseColor(string $color): static
    {
        $this->falseColor = $color;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return array_filter([
            'trueIcon' => $this->trueIcon,
            'falseIcon' => $this->falseIcon,
            'trueColor' => $this->trueColor,
            'falseColor' => $this->falseColor,
        ], fn ($value) => $value !== null);
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Table\Columns;

use Arqel\Table\Column;

/**
 * Icon-only cell. The cell value is the icon name unless
 * `options()` maps the underlying state to an icon.
 */
final class IconColumn extends Column
{
    protected string $type = 'icon';

    /** @var array<string, string> */
    protected array $options = [];

    protected ?string $size = null;

    /**
     * @param array<string, string> $map
     */
    public function options(array $map): static
    {
        $this->options = $map;

        return $this;
    }

    public function size(string $size): static
    {
        $this->size = $size;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return array_filter([
            'options' => $this->options !== [] ? $this->options : null,
            'size' => $this->size,
        ], fn ($value) => $value !== null);
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Table\Columns;

use Arqel\Table\Column;

/**
 * Image thumbnail cell. `disk` + `directory` mirror Laravel storage
 * semantics so the React side can build the public URL.
 */
final class ImageColumn extends Column
{
    public const string SHAPE_SQUARE = 'square';

    public const string SHAPE_CIRCULAR = 'circular';

    protected string $type = 'image';

    protected ?string $disk = null;

    protected ?string $directory = null;

    protected string $shape = self::SHAPE_SQUARE;

    protected ?int $size = null;

    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function directory(string $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    public function circular(): static
    {
        $this->shape = self::SHAPE_CIRCULAR;

        return $this;
    }

    public function square(): static
    {
        $this->shape = self::SHAPE_SQUARE;

        return $this;
    }

    public function size(int $size): static
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
            'disk' => $this->disk,
            'directory' => $this->directory,
            'shape' => $this->shape,
            'size' => $this->size,
        ], fn ($value) => $value !== null);
    }
}

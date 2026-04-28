<?php

declare(strict_types=1);

namespace Arqel\Table\Columns;

use Arqel\Table\Column;

/**
 * Plain text cell with optional truncation, line-wrap, and font
 * family override (handy for monospace IDs / hashes).
 */
class TextColumn extends Column
{
    protected string $type = 'text';

    protected ?int $limit = null;

    protected bool $wrap = false;

    protected ?string $fontFamily = null;

    public function limit(int $chars): static
    {
        $this->limit = $chars;

        return $this;
    }

    public function wrap(bool $wrap = true): static
    {
        $this->wrap = $wrap;

        return $this;
    }

    public function fontFamily(string $family): static
    {
        $this->fontFamily = $family;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return array_filter([
            'limit' => $this->limit,
            'wrap' => $this->wrap ?: null,
            'fontFamily' => $this->fontFamily,
        ], fn ($value) => $value !== null);
    }
}

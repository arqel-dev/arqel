<?php

declare(strict_types=1);

namespace Arqel\Form\Layout;

/**
 * Responsive grid. Accepts a fixed column count (`columns(3)`) or
 * a breakpoint map (`columns(['sm' => 1, 'md' => 2, 'lg' => 4])`)
 * which the React side translates into Tailwind classes.
 */
final class Grid extends Component
{
    protected string $type = 'grid';

    protected string $component = 'FormGrid';

    /** @var int|array<string, int> */
    protected int|array $cols = 2;

    protected ?string $gap = null;

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param int|array<string, int> $cols
     */
    public function columns(int|array $cols): static
    {
        $this->cols = $cols;

        return $this;
    }

    public function gap(string $gap): static
    {
        $this->gap = $gap;

        return $this;
    }

    /** @return int|array<string, int> */
    public function getCols(): int|array
    {
        return $this->cols;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return array_filter([
            'columns' => $this->cols,
            'gap' => $this->gap,
        ], fn ($v) => $v !== null);
    }
}

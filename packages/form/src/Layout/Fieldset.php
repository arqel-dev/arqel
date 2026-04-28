<?php

declare(strict_types=1);

namespace Arqel\Form\Layout;

/**
 * Fieldset: native `<fieldset>` semantic with `<legend>`. Useful
 * for grouping related fields (radio groups, permission lists).
 */
final class Fieldset extends Component
{
    protected string $type = 'fieldset';

    protected string $component = 'FormFieldset';

    protected string $legend;

    protected int $columns = 1;

    public function __construct(string $legend)
    {
        $this->legend = $legend;
    }

    public static function make(string $legend): self
    {
        return new self($legend);
    }

    public function legend(string $legend): static
    {
        $this->legend = $legend;

        return $this;
    }

    public function columns(int $columns): static
    {
        $this->columns = max(1, $columns);

        return $this;
    }

    public function getLegend(): string
    {
        return $this->legend;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'legend' => $this->legend,
            'columns' => $this->columns,
        ];
    }
}

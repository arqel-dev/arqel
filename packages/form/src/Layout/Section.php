<?php

declare(strict_types=1);

namespace Arqel\Form\Layout;

/**
 * Section: heading + optional description, collapsible, columns,
 * compact, aside.
 */
final class Section extends Component
{
    protected string $type = 'section';

    protected string $component = 'FormSection';

    protected string $heading;

    protected ?string $description = null;

    protected ?string $icon = null;

    protected bool $collapsible = false;

    protected bool $collapsed = false;

    protected int $columns = 1;

    protected bool $compact = false;

    protected bool $aside = false;

    public function __construct(string $heading)
    {
        $this->heading = $heading;
    }

    public static function make(string $heading): self
    {
        return new self($heading);
    }

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function collapsible(bool $collapsible = true): static
    {
        $this->collapsible = $collapsible;

        return $this;
    }

    public function collapsed(bool $collapsed = true): static
    {
        $this->collapsed = $collapsed;
        if ($collapsed) {
            $this->collapsible = true;
        }

        return $this;
    }

    public function columns(int $columns): static
    {
        $this->columns = max(1, $columns);

        return $this;
    }

    public function compact(bool $compact = true): static
    {
        $this->compact = $compact;

        return $this;
    }

    public function aside(bool $aside = true): static
    {
        $this->aside = $aside;

        return $this;
    }

    public function getHeading(): string
    {
        return $this->heading;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return array_filter([
            'heading' => $this->heading,
            'description' => $this->description,
            'icon' => $this->icon,
            'collapsible' => $this->collapsible ?: null,
            'collapsed' => $this->collapsed ?: null,
            'columns' => $this->columns,
            'compact' => $this->compact ?: null,
            'aside' => $this->aside ?: null,
        ], fn ($v) => $v !== null);
    }
}

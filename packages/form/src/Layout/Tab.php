<?php

declare(strict_types=1);

namespace Arqel\Form\Layout;

use Closure;

/**
 * A single tab inside a `Tabs` container.
 */
final class Tab extends Component
{
    protected string $type = 'tab';

    protected string $component = 'FormTab';

    protected string $id;

    protected string $label;

    protected ?string $icon = null;

    protected int|Closure|null $badge = null;

    public function __construct(string $id, string $label)
    {
        $this->id = $id;
        $this->label = $label;
    }

    public static function make(string $id, string $label): self
    {
        return new self($id, $label);
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function badge(int|Closure $count): static
    {
        $this->badge = $count;

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        $badge = null;
        if (is_int($this->badge)) {
            $badge = $this->badge;
        } elseif ($this->badge instanceof Closure) {
            $resolved = ($this->badge)();
            $badge = is_int($resolved) ? $resolved : null;
        }

        return array_filter([
            'id' => $this->id,
            'label' => $this->label,
            'icon' => $this->icon,
            'badge' => $badge,
        ], fn ($v) => $v !== null);
    }
}

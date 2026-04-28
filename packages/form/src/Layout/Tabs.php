<?php

declare(strict_types=1);

namespace Arqel\Form\Layout;

/**
 * Tabs container. Children are `Tab` instances; non-Tab children
 * are silently ignored. Default tab is the first one unless an
 * explicit id is set via `defaultTab()`.
 */
final class Tabs extends Component
{
    public const string ORIENTATION_HORIZONTAL = 'horizontal';

    public const string ORIENTATION_VERTICAL = 'vertical';

    protected string $type = 'tabs';

    protected string $component = 'FormTabs';

    protected ?string $defaultTab = null;

    protected string $orientation = self::ORIENTATION_HORIZONTAL;

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param array<int, Tab> $tabs
     */
    public function tabs(array $tabs): static
    {
        return $this->schema($tabs);
    }

    public function defaultTab(string $id): static
    {
        $this->defaultTab = $id;

        return $this;
    }

    public function vertical(): static
    {
        $this->orientation = self::ORIENTATION_VERTICAL;

        return $this;
    }

    public function horizontal(): static
    {
        $this->orientation = self::ORIENTATION_HORIZONTAL;

        return $this;
    }

    public function getDefaultTab(): ?string
    {
        if ($this->defaultTab !== null) {
            return $this->defaultTab;
        }

        foreach ($this->schema as $child) {
            if ($child instanceof Tab) {
                return $child->getId();
            }
        }

        return null;
    }

    public function getOrientation(): string
    {
        return $this->orientation;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'defaultTab' => $this->getDefaultTab(),
            'orientation' => $this->orientation,
        ];
    }
}

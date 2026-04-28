<?php

declare(strict_types=1);

namespace Arqel\Form\Layout;

/**
 * Invisible group — no border, no heading. Lets the developer
 * apply `visibleIf` / `canSee` / `columnSpan` to a set of
 * fields without rendering chrome.
 */
final class Group extends Component
{
    public const string ORIENTATION_HORIZONTAL = 'horizontal';

    public const string ORIENTATION_VERTICAL = 'vertical';

    protected string $type = 'group';

    protected string $component = 'FormGroup';

    protected string $orientation = self::ORIENTATION_VERTICAL;

    public static function make(): self
    {
        return new self;
    }

    public function orientation(string $orientation): static
    {
        $this->orientation = $orientation === self::ORIENTATION_HORIZONTAL
            ? self::ORIENTATION_HORIZONTAL
            : self::ORIENTATION_VERTICAL;

        return $this;
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
            'orientation' => $this->orientation,
        ];
    }
}

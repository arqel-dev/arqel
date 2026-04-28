<?php

declare(strict_types=1);

namespace Arqel\Form\Tests\Fixtures;

use Arqel\Fields\Field;
use Arqel\Form\Layout\Component;

/**
 * Minimal layout component used to exercise Form's flattening and
 * serialisation in tests without depending on Section/Fieldset/etc.
 * (those land in FORM-003+).
 */
final class StubLayout implements Component
{
    /**
     * @param array<int, Component|Field> $children
     */
    public function __construct(
        public readonly string $label,
        public readonly array $children,
    ) {}

    /** @return array<int, Component|Field> */
    public function getSchema(): array
    {
        return $this->children;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => 'stub',
            'label' => $this->label,
            'count' => count($this->children),
        ];
    }
}

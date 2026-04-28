<?php

declare(strict_types=1);

namespace Arqel\Form\Tests\Fixtures;

use Arqel\Fields\Field;
use Arqel\Form\Layout\Component;

/**
 * Minimal layout component used to exercise Form's flattening and
 * serialisation in tests without depending on Section/Fieldset/etc.
 */
final class StubLayout extends Component
{
    protected string $type = 'stub';

    protected string $component = 'StubLayout';

    public readonly string $label;

    /**
     * @param array<int, Component|Field> $children
     */
    public function __construct(string $label, array $children = [])
    {
        $this->label = $label;
        $this->schema = array_values($children);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'label' => $this->label,
            'count' => count($this->schema),
        ];
    }
}

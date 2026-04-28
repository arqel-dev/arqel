<?php

declare(strict_types=1);

namespace Arqel\Form\Layout;

/**
 * Two-column shorthand for `Grid::make()->columns(2)`.
 *
 * Pure semantic wrapper: distinguishes "I want a two-column
 * split" from "I want a configurable grid" in the schema.
 */
final class Columns extends Component
{
    protected string $type = 'columns';

    protected string $component = 'FormColumns';

    public static function make(): self
    {
        return new self;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'columns' => 2,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Fields\Tests\Fixtures;

use Arqel\Fields\Field;

/**
 * Minimal concrete Field used to exercise the abstract base.
 *
 * Real field types (TextField, SelectField, etc.) land in FIELDS-004+
 * and will define richer `getTypeSpecificProps()` payloads.
 */
final class StubField extends Field
{
    protected string $type = 'stub';

    protected string $component = 'StubInput';

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return ['stub' => true];
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Fields\Tests\Fixtures\Resources;

/**
 * Minimal duck-typed form object mirroring the flattened output of a
 * real `form()` schema (Section/layout aware). `Resource::effectiveFields()`
 * only requires a `getFields()` method returning the flat list of fields,
 * so this stub stands in for the `arqel-dev/forms` Form without coupling
 * the fields tests to that package.
 */
final class StubForm
{
    /**
     * @param array<int, mixed> $fields
     */
    public function __construct(private array $fields) {}

    /**
     * @return array<int, mixed>
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}

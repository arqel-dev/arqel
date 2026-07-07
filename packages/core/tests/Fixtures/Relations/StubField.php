<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Relations;

/**
 * Duck-typed stand-in for `Arqel\Fields\Field` — mirrors the shape
 * `FieldSchemaSerializer` (core) reads via `method_exists`, without a hard
 * dep on arqel-dev/fields (core stays dependency-free). Mirrors the
 * existing `StubRelationTable` pattern in this same directory.
 *
 * NOT consumable by the real `Arqel\Form\FieldRulesExtractor::extract()`:
 * that method hard `instanceof \Arqel\Fields\Field`-checks each entry
 * rather than duck-typing, so this stub is filtered out there, not turned
 * into a rule. It exists solely to exercise the serializer
 * (`RelationController::create()`); see `RelationStoreTest.php` for how
 * the validation-rejection path is covered instead.
 */
final class StubField
{
    public function __construct(
        private readonly string $name,
        private readonly bool $required = false,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return 'text';
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    /** @return array<int, mixed> */
    public function getValidationRules(): array
    {
        return $this->required ? ['required'] : [];
    }
}

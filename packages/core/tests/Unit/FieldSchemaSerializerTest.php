<?php

declare(strict_types=1);

use Arqel\Core\Support\FieldSchemaSerializer;

/**
 * Hand-rolled fixtures with explicit accessors so we don't depend
 * on `arqel/fields` from the core test-suite. The serializer is
 * duck-typed and only invokes a method when `method_exists`, so
 * minimal stubs are enough to cover the canonical shape.
 */
final class StubBasicField
{
    public function __construct(
        public readonly string $name = 'first_name',
        public readonly string $type = 'text',
        public readonly string $label = 'First name',
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getComponent(): string
    {
        return 'TextInput';
    }

    public function isRequired(): bool
    {
        return true;
    }

    public function getValidationRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    public function getTypeSpecificProps(): array
    {
        return ['maxLength' => 255];
    }
}

final class StubVisibilityField
{
    public bool $seen = true;

    public bool $editable = true;

    public function getName(): string
    {
        return 'secret';
    }

    public function getType(): string
    {
        return 'text';
    }

    public function canBeSeenBy(?object $user, mixed $record): bool
    {
        return $this->seen;
    }

    public function canBeEditedBy(?object $user, mixed $record): bool
    {
        return $this->editable;
    }

    public function isReadonly(): bool
    {
        return false;
    }
}

beforeEach(function (): void {
    $this->serializer = new FieldSchemaSerializer;
});

it('serialises a basic field into the canonical shape', function (): void {
    $payload = $this->serializer->serialize([new StubBasicField]);

    expect($payload)->toHaveCount(1);

    $field = $payload[0];

    expect($field)->toMatchArray([
        'type' => 'text',
        'name' => 'first_name',
        'label' => 'First name',
        'component' => 'TextInput',
        'required' => true,
        'readonly' => false,
        'columnSpan' => 1,
        'live' => false,
        'props' => ['maxLength' => 255],
    ])
        ->and($field['validation']['rules'])->toBe(['required', 'string', 'max:255'])
        ->and($field['visibility']['canSee'])->toBeTrue();
});

it('filters fields where canBeSeenBy returns false', function (): void {
    $field = new StubVisibilityField;
    $field->seen = false;

    expect($this->serializer->serialize([$field]))->toBe([]);
});

it('marks readonly when canBeEditedBy returns false', function (): void {
    $field = new StubVisibilityField;
    $field->seen = true;
    $field->editable = false;

    $payload = $this->serializer->serialize([$field]);

    expect($payload[0]['readonly'])->toBeTrue();
});

it('emits an empty visibility map when isVisibleIn is missing', function (): void {
    $payload = $this->serializer->serialize([new StubBasicField]);

    expect($payload[0]['visibility'])->toMatchArray([
        'create' => true,
        'edit' => true,
        'detail' => true,
        'table' => true,
    ]);
});

it('skips entries that are not objects', function (): void {
    $payload = $this->serializer->serialize([
        new StubBasicField,
        'not-a-field',
        42,
        null,
    ]);

    expect($payload)->toHaveCount(1);
});

it('serialises Closure rules as their string form (skipping callables)', function (): void {
    $field = new class
    {
        public function getName(): string
        {
            return 'x';
        }

        public function getType(): string
        {
            return 'text';
        }

        public function getValidationRules(): array
        {
            return ['required', fn () => true, 'max:10'];
        }
    };

    $payload = (new FieldSchemaSerializer)->serialize([$field]);

    expect($payload[0]['validation']['rules'])->toBe(['required', 'max:10']);
});

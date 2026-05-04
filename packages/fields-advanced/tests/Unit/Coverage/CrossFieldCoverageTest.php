<?php

declare(strict_types=1);

use Arqel\Fields\Field;
use Arqel\FieldsAdvanced\Types\BuilderField;
use Arqel\FieldsAdvanced\Types\CodeField;
use Arqel\FieldsAdvanced\Types\KeyValueField;
use Arqel\FieldsAdvanced\Types\MarkdownField;
use Arqel\FieldsAdvanced\Types\RepeaterField;
use Arqel\FieldsAdvanced\Types\RichTextField;
use Arqel\FieldsAdvanced\Types\TagsField;
use Arqel\FieldsAdvanced\Types\WizardField;

/**
 * Cross-field coverage gaps for the 8 advanced field types.
 *
 * Each test here asserts an invariant that holds across every field
 * in the package and was previously only checked implicitly by per
 * field tests. They exist to catch a future regression where one
 * field drifts from the shared contract (e.g. a nullable
 * `getTypeSpecificProps()` return, a missing `extends Field`, or a
 * non-JSON-serialisable prop leaking through).
 *
 * Implements FIELDS-ADV-019 (PHP slice) from
 * PLANNING/09-fase-2-essenciais.md.
 */

/**
 * @return array<int, class-string<Field>>
 */
function arqelAdvancedFieldClasses(): array
{
    return [
        RichTextField::class,
        MarkdownField::class,
        CodeField::class,
        RepeaterField::class,
        BuilderField::class,
        KeyValueField::class,
        TagsField::class,
        WizardField::class,
    ];
}

it('extends the base Field for every advanced field type', function (): void {
    foreach (arqelAdvancedFieldClasses() as $class) {
        expect(is_subclass_of($class, Field::class))
            ->toBeTrue("{$class} must extend Arqel\\Fields\\Field");
    }
});

it('returns a non-nullable array from getTypeSpecificProps() on every field', function (): void {
    foreach (arqelAdvancedFieldClasses() as $class) {
        /** @var Field $field */
        $field = new $class('content');

        $props = $field->getTypeSpecificProps();

        // PHP would already type-error if this returned null, but the
        // cast-via-`expect` pins the contract for future refactors.
        expect($props)->toBeArray("{$class}::getTypeSpecificProps() must return an array");
    }
});

it('exposes a non-empty type and component string on every field', function (): void {
    foreach (arqelAdvancedFieldClasses() as $class) {
        /** @var Field $field */
        $field = new $class('content');

        expect($field->getType())
            ->toBeString()
            ->not->toBe('', "{$class}::getType() must be a non-empty string")
            ->and($field->getComponent())
            ->toBeString()
            ->not->toBe('', "{$class}::getComponent() must be a non-empty string");
    }
});

it('produces a JSON-serialisable type-specific props payload on every field', function (): void {
    foreach (arqelAdvancedFieldClasses() as $class) {
        /** @var Field $field */
        $field = new $class('content');

        $encoded = json_encode($field->getTypeSpecificProps());

        expect($encoded)
            ->toBeString()
            ->and(json_last_error())->toBe(JSON_ERROR_NONE, "{$class} props must be JSON-encodable");
    }
});

it('returns a same-class instance from make() on every field that overrides it', function (): void {
    $factories = [
        RepeaterField::class,
        BuilderField::class,
        KeyValueField::class,
        TagsField::class,
        WizardField::class,
    ];

    foreach ($factories as $class) {
        /** @var Field $field */
        $field = $class::make('content');

        expect($field)
            ->toBeInstanceOf($class)
            ->and($field->getName())->toBe('content');
    }
});

it('honours the configured imageUploadDisk verbatim in the route query string', function (): void {
    $field = (new RichTextField('body'))->imageUploadDisk('private-attachments');

    expect($field->getTypeSpecificProps()['imageUploadRoute'])
        ->toBe('/arqel-dev/fields/upload?disk=private-attachments');
});

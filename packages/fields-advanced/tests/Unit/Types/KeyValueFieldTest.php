<?php

declare(strict_types=1);

use Arqel\Fields\FieldFactory;
use Arqel\FieldsAdvanced\Types\KeyValueField;

it('exposes the correct type and component for KeyValueField', function (): void {
    $field = new KeyValueField('headers');

    expect($field->getType())->toBe('keyValue')
        ->and($field->getComponent())->toBe('KeyValueInput');
});

it('can be constructed via KeyValueField::make()', function (): void {
    $field = KeyValueField::make('headers');

    expect($field)->toBeInstanceOf(KeyValueField::class)
        ->and($field->getName())->toBe('headers');
});

it('can be constructed via the FieldFactory keyValue macro', function (): void {
    $field = FieldFactory::keyValue('headers');

    expect($field)->toBeInstanceOf(KeyValueField::class)
        ->and($field->getName())->toBe('headers');
});

it('ships the canonical default state', function (): void {
    $props = (new KeyValueField('headers'))->getTypeSpecificProps();

    expect($props)->toBe([
        'keyLabel' => 'Key',
        'valueLabel' => 'Value',
        'keyPlaceholder' => '',
        'valuePlaceholder' => '',
        'editableKeys' => true,
        'addable' => true,
        'deletable' => true,
        'reorderable' => false,
        'asObject' => false,
    ]);
});

it('persists keyLabel via keyLabel() and is fluent', function (): void {
    $field = (new KeyValueField('headers'))->keyLabel('Header Name');

    expect($field)->toBeInstanceOf(KeyValueField::class)
        ->and($field->getTypeSpecificProps()['keyLabel'])->toBe('Header Name');
});

it('rejects an empty keyLabel with InvalidArgumentException', function (): void {
    (new KeyValueField('headers'))->keyLabel('');
})->throws(InvalidArgumentException::class);

it('rejects an empty valueLabel with InvalidArgumentException', function (): void {
    (new KeyValueField('headers'))->valueLabel('');
})->throws(InvalidArgumentException::class);

it('persists valueLabel via valueLabel() and is fluent', function (): void {
    $field = (new KeyValueField('headers'))->valueLabel('Header Value');

    expect($field->getTypeSpecificProps()['valueLabel'])->toBe('Header Value');
});

it('accepts arbitrary key/value placeholders, including the empty string', function (): void {
    $set = (new KeyValueField('headers'))
        ->keyPlaceholder('e.g. Authorization')
        ->valuePlaceholder('Bearer ...');
    $reset = (new KeyValueField('headers'))
        ->keyPlaceholder('foo')
        ->valuePlaceholder('bar')
        ->keyPlaceholder('')
        ->valuePlaceholder('');

    expect($set->getTypeSpecificProps()['keyPlaceholder'])->toBe('e.g. Authorization')
        ->and($set->getTypeSpecificProps()['valuePlaceholder'])->toBe('Bearer ...')
        ->and($reset->getTypeSpecificProps()['keyPlaceholder'])->toBe('')
        ->and($reset->getTypeSpecificProps()['valuePlaceholder'])->toBe('');
});

it('toggles editableKeys via editableKeys()', function (): void {
    $defaultOn = new KeyValueField('headers');
    $explicitOff = (new KeyValueField('headers'))->editableKeys(false);
    $reEnabled = (new KeyValueField('headers'))->editableKeys(false)->editableKeys();

    expect($defaultOn->getTypeSpecificProps()['editableKeys'])->toBeTrue()
        ->and($explicitOff->getTypeSpecificProps()['editableKeys'])->toBeFalse()
        ->and($reEnabled->getTypeSpecificProps()['editableKeys'])->toBeTrue();
});

it('flips addable, deletable and reorderable independently', function (): void {
    $field = (new KeyValueField('headers'))
        ->addable(false)
        ->deletable(false)
        ->reorderable(true);

    $props = $field->getTypeSpecificProps();

    expect($props['addable'])->toBeFalse()
        ->and($props['deletable'])->toBeFalse()
        ->and($props['reorderable'])->toBeTrue();
});

it('toggles asObject via asObject() with default true and explicit reset', function (): void {
    $defaultShape = new KeyValueField('headers');
    $asObject = (new KeyValueField('headers'))->asObject();
    $explicitTrue = (new KeyValueField('headers'))->asObject(true);
    $reverted = (new KeyValueField('headers'))->asObject(true)->asObject(false);

    expect($defaultShape->getTypeSpecificProps()['asObject'])->toBeFalse()
        ->and($asObject->getTypeSpecificProps()['asObject'])->toBeTrue()
        ->and($explicitTrue->getTypeSpecificProps()['asObject'])->toBeTrue()
        ->and($reverted->getTypeSpecificProps()['asObject'])->toBeFalse();
});

it('returns all 9 keys from getTypeSpecificProps()', function (): void {
    $props = (new KeyValueField('headers'))->getTypeSpecificProps();

    expect(array_keys($props))->toBe([
        'keyLabel',
        'valueLabel',
        'keyPlaceholder',
        'valuePlaceholder',
        'editableKeys',
        'addable',
        'deletable',
        'reorderable',
        'asObject',
    ]);
});

it('serialises the full type-specific props payload end-to-end', function (): void {
    $field = (new KeyValueField('headers'))
        ->keyLabel('Header Name')
        ->valueLabel('Header Value')
        ->keyPlaceholder('e.g. Authorization')
        ->valuePlaceholder('Bearer ...')
        ->editableKeys(false)
        ->addable(false)
        ->deletable(false)
        ->reorderable(true)
        ->asObject(true);

    expect($field->getTypeSpecificProps())->toBe([
        'keyLabel' => 'Header Name',
        'valueLabel' => 'Header Value',
        'keyPlaceholder' => 'e.g. Authorization',
        'valuePlaceholder' => 'Bearer ...',
        'editableKeys' => false,
        'addable' => false,
        'deletable' => false,
        'reorderable' => true,
        'asObject' => true,
    ])
        ->and($field->getName())->toBe('headers')
        ->and($field->getType())->toBe('keyValue')
        ->and($field->getComponent())->toBe('KeyValueInput');
});

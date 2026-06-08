<?php

declare(strict_types=1);

use Arqel\Fields\FieldFactory;
use Arqel\Fields\Types\SelectField;
use Arqel\Fields\Types\TextField;
use Arqel\FieldsAdvanced\Types\RepeaterField;

it('exposes the correct type and component for RepeaterField', function (): void {
    $field = new RepeaterField('addresses');

    expect($field->getType())->toBe('repeater')
        ->and($field->getComponent())->toBe('RepeaterInput');
});

it('can be constructed via RepeaterField::make()', function (): void {
    $field = RepeaterField::make('addresses');

    expect($field)->toBeInstanceOf(RepeaterField::class)
        ->and($field->getName())->toBe('addresses');
});

it('can be constructed via the FieldFactory repeater macro', function (): void {
    $field = FieldFactory::repeater('addresses');

    expect($field)->toBeInstanceOf(RepeaterField::class)
        ->and($field->getName())->toBe('addresses');
});

it('ships the canonical default state', function (): void {
    $props = (new RepeaterField('addresses'))->getTypeSpecificProps();

    expect($props)->toBe([
        'schema' => [],
        'minItems' => null,
        'maxItems' => null,
        'reorderable' => true,
        'collapsible' => false,
        'cloneable' => true,
        'itemLabel' => null,
        'relationship' => null,
    ]);
});

it('persists Field children via schema() and silently filters non-Field entries', function (): void {
    $field = (new RepeaterField('addresses'))->schema([
        new TextField('street'),
        'not a field instance',
        new TextField('city'),
        42,
        null,
    ]);

    $schema = $field->getTypeSpecificProps()['schema'];

    expect($schema)->toHaveCount(2)
        ->and($schema[0]['name'])->toBe('street')
        ->and($schema[1]['name'])->toBe('city');
});

it('clamps minItems to ≥0 and persists positive values', function (): void {
    $negative = (new RepeaterField('addresses'))->minItems(-5);
    $three = (new RepeaterField('addresses'))->minItems(3);
    $zero = (new RepeaterField('addresses'))->minItems(0);

    expect($negative->getTypeSpecificProps()['minItems'])->toBe(0)
        ->and($three->getTypeSpecificProps()['minItems'])->toBe(3)
        ->and($zero->getTypeSpecificProps()['minItems'])->toBe(0);
});

it('clamps maxItems to ≥1 and persists positive values', function (): void {
    $zero = (new RepeaterField('addresses'))->maxItems(0);
    $negative = (new RepeaterField('addresses'))->maxItems(-2);
    $five = (new RepeaterField('addresses'))->maxItems(5);

    expect($zero->getTypeSpecificProps()['maxItems'])->toBe(1)
        ->and($negative->getTypeSpecificProps()['maxItems'])->toBe(1)
        ->and($five->getTypeSpecificProps()['maxItems'])->toBe(5);
});

it('throws InvalidArgumentException when maxItems < minItems', function (): void {
    (new RepeaterField('addresses'))
        ->minItems(5)
        ->maxItems(3);
})->throws(InvalidArgumentException::class, 'maxItems must be >= minItems');

it('toggles reorderable via reorderable()', function (): void {
    $defaultOn = new RepeaterField('addresses');
    $explicitOff = (new RepeaterField('addresses'))->reorderable(false);
    $reEnabled = (new RepeaterField('addresses'))->reorderable(false)->reorderable();

    expect($defaultOn->getTypeSpecificProps()['reorderable'])->toBeTrue()
        ->and($explicitOff->getTypeSpecificProps()['reorderable'])->toBeFalse()
        ->and($reEnabled->getTypeSpecificProps()['reorderable'])->toBeTrue();
});

it('toggles collapsible via collapsible()', function (): void {
    $defaultOff = new RepeaterField('addresses');
    $explicitOn = (new RepeaterField('addresses'))->collapsible(true);
    $reDisabled = (new RepeaterField('addresses'))->collapsible(true)->collapsible(false);

    expect($defaultOff->getTypeSpecificProps()['collapsible'])->toBeFalse()
        ->and($explicitOn->getTypeSpecificProps()['collapsible'])->toBeTrue()
        ->and($reDisabled->getTypeSpecificProps()['collapsible'])->toBeFalse();
});

it('toggles cloneable via cloneable()', function (): void {
    $defaultOn = new RepeaterField('addresses');
    $explicitOff = (new RepeaterField('addresses'))->cloneable(false);
    $reEnabled = (new RepeaterField('addresses'))->cloneable(false)->cloneable();

    expect($defaultOn->getTypeSpecificProps()['cloneable'])->toBeTrue()
        ->and($explicitOff->getTypeSpecificProps()['cloneable'])->toBeFalse()
        ->and($reEnabled->getTypeSpecificProps()['cloneable'])->toBeTrue();
});

it('persists itemLabel template and relationship name', function (): void {
    $field = (new RepeaterField('addresses'))
        ->itemLabel('Address {{label}}')
        ->relationship('addresses');

    expect($field->getTypeSpecificProps()['itemLabel'])->toBe('Address {{label}}')
        ->and($field->getTypeSpecificProps()['relationship'])->toBe('addresses');
});

it('returns all 8 keys from getTypeSpecificProps()', function (): void {
    $props = (new RepeaterField('addresses'))->getTypeSpecificProps();

    expect(array_keys($props))->toBe([
        'schema',
        'minItems',
        'maxItems',
        'reorderable',
        'collapsible',
        'cloneable',
        'itemLabel',
        'relationship',
    ]);
});

it('serialises each nested child through the canonical FieldSchema shape (#221)', function (): void {
    $field = (new RepeaterField('addresses'))->schema([
        new TextField('street'),
        new TextField('city'),
    ]);

    $schema = $field->getTypeSpecificProps()['schema'];

    // Each entry is now the rich FieldSchema array (name + type + label +
    // props + ...), not the lossy `{name, type}` collapse that dropped
    // every other attribute before #221.
    expect($schema)->toHaveCount(2)
        ->and($schema[0])->toMatchArray(['name' => 'street', 'type' => 'text'])
        ->and($schema[0])->toHaveKeys(['label', 'placeholder', 'props', 'validation'])
        ->and($schema[1])->toMatchArray(['name' => 'city', 'type' => 'text']);
});

it('preserves a nested SelectField options + a TextField label/placeholder (#221)', function (): void {
    $field = (new RepeaterField('items'))->schema([
        (new TextField('title'))->label('My Title')->placeholder('Type here'),
        (new SelectField('status'))->options(['a' => 'Active', 'b' => 'Banned']),
    ]);

    $schema = $field->getTypeSpecificProps()['schema'];

    // Text sub-field carries its label + placeholder (flat, matching how
    // RepeaterInput.tsx reads `sub.label` / `sub.placeholder`).
    expect($schema[0]['name'])->toBe('title')
        ->and($schema[0]['label'])->toBe('My Title')
        ->and($schema[0]['placeholder'])->toBe('Type here');

    // Select sub-field carries its options nested under `props.options`,
    // the same shape SelectInput consumes at the top level. Before #221
    // the whole sub-field collapsed to `{name, type}` and the dropdown
    // rendered empty.
    expect($schema[1]['name'])->toBe('status')
        ->and($schema[1]['type'])->toBe('select')
        ->and($schema[1]['props']['options'])->toBe(['a' => 'Active', 'b' => 'Banned']);
});

it('serialises the full type-specific props payload end-to-end', function (): void {
    $field = (new RepeaterField('addresses'))
        ->schema([new TextField('street')])
        ->minItems(1)
        ->maxItems(5)
        ->reorderable(false)
        ->collapsible(true)
        ->cloneable(false)
        ->itemLabel('{{street}}')
        ->relationship('addresses');

    $props = $field->getTypeSpecificProps();

    expect($props['minItems'])->toBe(1)
        ->and($props['maxItems'])->toBe(5)
        ->and($props['reorderable'])->toBeFalse()
        ->and($props['collapsible'])->toBeTrue()
        ->and($props['cloneable'])->toBeFalse()
        ->and($props['itemLabel'])->toBe('{{street}}')
        ->and($props['relationship'])->toBe('addresses')
        ->and($props['schema'])->toHaveCount(1)
        ->and($props['schema'][0])->toMatchArray(['name' => 'street', 'type' => 'text']);
});

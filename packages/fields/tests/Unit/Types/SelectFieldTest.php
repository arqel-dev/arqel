<?php

declare(strict_types=1);

use Arqel\Fields\Types\MultiSelectField;
use Arqel\Fields\Types\RadioField;
use Arqel\Fields\Types\SelectField;

it('exposes the correct type and component for SelectField', function (): void {
    $field = new SelectField('status');

    expect($field->getType())->toBe('select')
        ->and($field->getComponent())->toBe('SelectInput');
});

it('serialises static options as-is', function (): void {
    $field = (new SelectField('status'))->options([
        'draft' => 'Draft',
        'published' => 'Published',
    ]);

    $props = $field->getTypeSpecificProps();

    expect($props['options'])->toBe([
        'draft' => 'Draft',
        'published' => 'Published',
    ])
        ->and($props['searchable'])->toBeFalse()
        ->and($props['multiple'])->toBeFalse()
        ->and($props['native'])->toBeTrue()
        ->and($props['creatable'])->toBeFalse()
        ->and($props['optionsRelation'])->toBeNull();
});

it('invokes a closure when resolving options', function (): void {
    $field = (new SelectField('cat'))->options(fn () => [1 => 'A', 2 => 'B']);

    expect($field->resolveOptions())->toBe([1 => 'A', 2 => 'B']);
});

it('returns an empty array when a closure returns a non-array', function (): void {
    $field = (new SelectField('cat'))->options(fn () => 'oops');

    expect($field->resolveOptions())->toBe([]);
});

it('stores relationship metadata for controller-side resolution', function (): void {
    $query = fn ($q) => $q;
    $field = (new SelectField('cat'))->optionsRelationship('category', 'name', $query);

    expect($field->getOptionsRelation())->toBe('category')
        ->and($field->getOptionsRelationDisplay())->toBe('name')
        ->and($field->getOptionsRelationQuery())->toBe($query)
        ->and($field->resolveOptions())->toBe([])
        ->and($field->getTypeSpecificProps()['optionsRelation'])->toBe('category');
});

it('clears prior option sources when a new one is set', function (): void {
    $field = (new SelectField('cat'))
        ->options(['a' => 'A'])
        ->optionsRelationship('category', 'name')
        ->options([1 => 'X']);

    expect($field->getOptionsRelation())->toBeNull()
        ->and($field->resolveOptions())->toBe([1 => 'X']);
});

it('toggles searchable, multiple, native, creatable, and allowCustomValues', function (): void {
    $field = (new SelectField('cat'))
        ->searchable()
        ->multiple()
        ->native(false)
        ->creatable()
        ->allowCustomValues();

    $props = $field->getTypeSpecificProps();

    expect($props['searchable'])->toBeTrue()
        ->and($props['multiple'])->toBeTrue()
        ->and($props['native'])->toBeFalse()
        ->and($props['creatable'])->toBeTrue()
        ->and($props['allowCustomValues'])->toBeTrue()
        ->and($field->isMultiple())->toBeTrue();
});

it('flips creatable on automatically when createOptionUsing is set', function (): void {
    $callback = fn (string $name) => ['id' => 1, 'name' => $name];
    $field = (new SelectField('cat'))->createOptionUsing($callback);

    expect($field->getTypeSpecificProps()['creatable'])->toBeTrue()
        ->and($field->getCreateUsing())->toBe($callback);
});

it('flips MultiSelectField to multiple + non-native by default', function (): void {
    $field = new MultiSelectField('tags');

    expect($field->getType())->toBe('multiSelect')
        ->and($field->getComponent())->toBe('MultiSelectInput')
        ->and($field->isMultiple())->toBeTrue()
        ->and($field->getTypeSpecificProps()['native'])->toBeFalse();
});

it('exposes the correct type and component for RadioField', function (): void {
    $field = (new RadioField('size'))->options(['s' => 'S', 'l' => 'L']);

    expect($field->getType())->toBe('radio')
        ->and($field->getComponent())->toBe('RadioInput')
        ->and($field->getTypeSpecificProps()['native'])->toBeFalse()
        ->and($field->resolveOptions())->toBe(['s' => 'S', 'l' => 'L']);
});

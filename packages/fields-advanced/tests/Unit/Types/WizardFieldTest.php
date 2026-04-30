<?php

declare(strict_types=1);

use Arqel\Fields\FieldFactory;
use Arqel\Fields\Types\TextField;
use Arqel\FieldsAdvanced\Steps\Step;
use Arqel\FieldsAdvanced\Types\WizardField;

it('exposes the correct type and component for WizardField', function (): void {
    $field = new WizardField('signup');

    expect($field->getType())->toBe('wizard')
        ->and($field->getComponent())->toBe('WizardInput');
});

it('can be constructed via WizardField::make()', function (): void {
    $field = WizardField::make('signup');

    expect($field)->toBeInstanceOf(WizardField::class)
        ->and($field->getName())->toBe('signup');
});

it('can be constructed via the FieldFactory wizard macro', function (): void {
    $field = FieldFactory::wizard('signup');

    expect($field)->toBeInstanceOf(WizardField::class)
        ->and($field->getName())->toBe('signup');
});

it('ships the canonical default state', function (): void {
    $props = (new WizardField('signup'))->getTypeSpecificProps();

    expect($props)->toBe([
        'steps' => [],
        'persistInUrl' => false,
        'skippable' => false,
    ]);
});

it('returns the same instance from steps() to allow chaining', function (): void {
    $field = new WizardField('signup');
    $returned = $field->steps([Step::make('a'), Step::make('b')]);

    expect($returned)->toBe($field);
});

it('persists the configured steps in order', function (): void {
    $field = (new WizardField('signup'))->steps([
        Step::make('details'),
        Step::make('password'),
    ]);

    $steps = $field->getTypeSpecificProps()['steps'];

    expect($steps)->toHaveCount(2)
        ->and($steps[0]['name'])->toBe('details')
        ->and($steps[1]['name'])->toBe('password');
});

it('silently filters non-Step entries from steps()', function (): void {
    $field = (new WizardField('signup'))->steps([
        Step::make('details'),
        'not a step',
        42,
        null,
        new stdClass,
        Step::make('password'),
    ]);

    $steps = $field->getTypeSpecificProps()['steps'];

    expect($steps)->toHaveCount(2)
        ->and(array_column($steps, 'name'))->toBe(['details', 'password']);
});

it('throws InvalidArgumentException when two steps share the same name', function (): void {
    (new WizardField('signup'))->steps([
        Step::make('details'),
        Step::make('details'),
    ]);
})->throws(InvalidArgumentException::class, 'Step names must be unique; duplicate: details');

it('toggles persistInUrl via its setter', function (): void {
    $field = (new WizardField('signup'))->persistInUrl();

    expect($field->getTypeSpecificProps()['persistInUrl'])->toBeTrue();

    $disabled = (new WizardField('signup'))->persistInUrl(false);
    expect($disabled->getTypeSpecificProps()['persistInUrl'])->toBeFalse();
});

it('toggles skippable via its setter', function (): void {
    $field = (new WizardField('signup'))->skippable();

    expect($field->getTypeSpecificProps()['skippable'])->toBeTrue();

    $disabled = (new WizardField('signup'))->skippable(false);
    expect($disabled->getTypeSpecificProps()['skippable'])->toBeFalse();
});

it('returns all 3 keys from getTypeSpecificProps() with steps serialised via toArray()', function (): void {
    $field = (new WizardField('signup'))
        ->steps([
            Step::make('details')
                ->label('Details')
                ->icon('info')
                ->schema([new TextField('name')]),
            Step::make('password')
                ->schema([new TextField('password')]),
        ])
        ->persistInUrl()
        ->skippable();

    $props = $field->getTypeSpecificProps();

    expect(array_keys($props))->toBe(['steps', 'persistInUrl', 'skippable'])
        ->and($props['persistInUrl'])->toBeTrue()
        ->and($props['skippable'])->toBeTrue()
        ->and($props['steps'])->toHaveCount(2);

    expect($props['steps'][0])->toMatchArray([
        'name' => 'details',
        'label' => 'Details',
        'icon' => 'info',
    ])->and($props['steps'][0]['schema'][0]['name'])->toBe('name')
        ->and($props['steps'][1]['name'])->toBe('password')
        ->and($props['steps'][1]['icon'])->toBeNull()
        ->and($props['steps'][1]['label'])->toBe('Password');
});

it('serialises each step entry through Step::toArray()', function (): void {
    $field = (new WizardField('signup'))->steps([
        Step::make('details')->schema([new TextField('name')]),
    ]);

    $stepPayload = $field->getTypeSpecificProps()['steps'][0];

    expect(array_keys($stepPayload))->toBe(['name', 'label', 'icon', 'schema'])
        ->and($stepPayload['schema'][0]['type'])->toBe('text');
});

<?php

declare(strict_types=1);

use Arqel\Fields\Types\BooleanField;
use Arqel\Fields\Types\ToggleField;

it('exposes the correct type and component for BooleanField', function (): void {
    $field = new BooleanField('is_active');

    expect($field->getType())->toBe('boolean')
        ->and($field->getComponent())->toBe('Checkbox');
});

it('defaults BooleanField to false and stacked layout', function (): void {
    $field = new BooleanField('is_active');

    expect($field->getDefault())->toBeFalse()
        ->and($field->getTypeSpecificProps())->toBe(['inline' => false])
        ->and($field->getDefaultRules())->toBe(['boolean']);
});

it('flips BooleanField to inline layout', function (): void {
    $field = (new BooleanField('is_active'))->inline();

    expect($field->getTypeSpecificProps())->toBe(['inline' => true]);
});

it('honours an explicit BooleanField default override', function (): void {
    $field = (new BooleanField('newsletter_opt_in'))->default(true);

    expect($field->getDefault())->toBeTrue();
});

it('exposes the correct type and component for ToggleField', function (): void {
    $field = new ToggleField('is_published');

    expect($field->getType())->toBe('toggle')
        ->and($field->getComponent())->toBe('Toggle')
        ->and($field->getDefault())->toBeFalse()
        ->and($field->getDefaultRules())->toBe(['boolean']);
});

it('serialises ToggleField visual customisation props only when set', function (): void {
    $bare = (new ToggleField('is_published'))->getTypeSpecificProps();
    $custom = (new ToggleField('is_published'))
        ->onColor('emerald')
        ->offColor('zinc')
        ->onIcon('check')
        ->offIcon('x')
        ->getTypeSpecificProps();

    expect($bare)->toBe(['inline' => false])
        ->and($custom)->toBe([
            'inline' => false,
            'onColor' => 'emerald',
            'offColor' => 'zinc',
            'onIcon' => 'check',
            'offIcon' => 'x',
        ]);
});

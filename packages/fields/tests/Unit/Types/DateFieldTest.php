<?php

declare(strict_types=1);

use Arqel\Fields\Types\DateField;
use Arqel\Fields\Types\DateTimeField;

it('exposes the correct type, component, and default rules for DateField', function (): void {
    $field = new DateField('birthday');

    expect($field->getType())->toBe('date')
        ->and($field->getComponent())->toBe('DateInput')
        ->and($field->getDefaultRules())->toBe(['date'])
        ->and($field->getFormat())->toBe('Y-m-d')
        ->and($field->getDisplayFormat())->toBe('d/m/Y');
});

it('emits no bound props when no min/max is configured', function (): void {
    $field = new DateField('birthday');

    expect($field->getTypeSpecificProps())->toBe([
        'format' => 'Y-m-d',
        'displayFormat' => 'd/m/Y',
        'closeOnSelect' => true,
    ]);
});

it('serialises literal min/max bounds verbatim', function (): void {
    $field = (new DateField('birthday'))
        ->minDate('1900-01-01')
        ->maxDate('2025-12-31');

    $props = $field->getTypeSpecificProps();

    expect($props['minDate'])->toBe('1900-01-01')
        ->and($props['maxDate'])->toBe('2025-12-31');
});

it('invokes Closures when resolving min/max bounds', function (): void {
    $field = (new DateField('appointment'))
        ->minDate(fn () => '2026-01-01')
        ->maxDate(fn () => '2026-12-31');

    $props = $field->getTypeSpecificProps();

    expect($props['minDate'])->toBe('2026-01-01')
        ->and($props['maxDate'])->toBe('2026-12-31');
});

it('drops bounds whose Closure returns a non-string', function (): void {
    $field = (new DateField('appointment'))->minDate(fn () => 12345);

    expect($field->getTypeSpecificProps())->not->toHaveKey('minDate');
});

it('honours custom format, displayFormat, closeOnSelect, and timezone', function (): void {
    $field = (new DateField('event_date'))
        ->format('Y-m-d')
        ->displayFormat('M j, Y')
        ->closeOnDateSelection(false)
        ->timezone('Europe/Lisbon');

    $props = $field->getTypeSpecificProps();

    expect($props)->toBe([
        'format' => 'Y-m-d',
        'displayFormat' => 'M j, Y',
        'closeOnSelect' => false,
        'timezone' => 'Europe/Lisbon',
    ]);
});

it('exposes correct defaults for DateTimeField', function (): void {
    $field = new DateTimeField('starts_at');

    expect($field->getType())->toBe('dateTime')
        ->and($field->getComponent())->toBe('DateTimeInput')
        ->and($field->getFormat())->toBe('Y-m-d H:i:s')
        ->and($field->getDisplayFormat())->toBe('d/m/Y H:i')
        ->and($field->showsSeconds())->toBeFalse()
        ->and($field->getTypeSpecificProps()['seconds'])->toBeFalse();
});

it('flips DateTimeField displayFormat when seconds is enabled', function (): void {
    $field = (new DateTimeField('starts_at'))->seconds();

    expect($field->showsSeconds())->toBeTrue()
        ->and($field->getDisplayFormat())->toBe('d/m/Y H:i:s')
        ->and($field->getTypeSpecificProps()['seconds'])->toBeTrue();
});

it('disables seconds explicitly on DateTimeField', function (): void {
    $field = (new DateTimeField('starts_at'))->seconds()->seconds(false);

    expect($field->showsSeconds())->toBeFalse()
        ->and($field->getDisplayFormat())->toBe('d/m/Y H:i');
});

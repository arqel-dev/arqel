<?php

declare(strict_types=1);

use Arqel\Fields\Types\CurrencyField;
use Arqel\Fields\Types\NumberField;

it('exposes the correct type and component for NumberField', function (): void {
    $field = new NumberField('age');

    expect($field->getType())->toBe('number')
        ->and($field->getComponent())->toBe('NumberInput');
});

it('serialises Number constraints and emits numeric default rules', function (): void {
    $field = (new NumberField('age'))
        ->min(0)
        ->max(120)
        ->step(1)
        ->decimals(0);

    expect($field->getTypeSpecificProps())->toBe([
        'min' => 0,
        'max' => 120,
        'step' => 1,
        'decimals' => 0,
    ])
        ->and($field->getDefaultRules())->toBe(['numeric', 'min:0', 'max:120']);
});

it('flips to integer-only mode and replaces the numeric rule', function (): void {
    $field = (new NumberField('count'))->integer()->min(1);

    expect($field->getTypeSpecificProps())->toBe([
        'min' => 1,
        'integer' => true,
    ])
        ->and($field->getDefaultRules())->toBe(['integer', 'min:1']);
});

it('emits no constraint props when nothing is configured', function (): void {
    expect((new NumberField('amount'))->getTypeSpecificProps())->toBe([]);
});

it('exposes the correct type and component for CurrencyField', function (): void {
    $field = new CurrencyField('price');

    expect($field->getType())->toBe('currency')
        ->and($field->getComponent())->toBe('CurrencyInput');
});

it('defaults CurrencyField to en-US formatting with two decimals', function (): void {
    $props = (new CurrencyField('price'))->getTypeSpecificProps();

    expect($props)->toBe([
        'decimals' => 2,
        'prefix' => '$',
        'thousandsSeparator' => ',',
        'decimalSeparator' => '.',
    ]);
});

it('honours pt-BR-style overrides on CurrencyField', function (): void {
    $field = (new CurrencyField('price'))
        ->prefix('R$')
        ->thousandsSeparator('.')
        ->decimalSeparator(',')
        ->decimals(2);

    expect($field->getTypeSpecificProps())->toBe([
        'decimals' => 2,
        'prefix' => 'R$',
        'thousandsSeparator' => '.',
        'decimalSeparator' => ',',
    ]);
});

it('emits an optional suffix on CurrencyField only when set', function (): void {
    $without = (new CurrencyField('price'))->getTypeSpecificProps();
    $with = (new CurrencyField('price'))->suffix('USD')->getTypeSpecificProps();

    expect($without)->not->toHaveKey('suffix')
        ->and($with['suffix'])->toBe('USD');
});

it('inherits min/max constraints into the CurrencyField default rules', function (): void {
    $field = (new CurrencyField('price'))->min(0)->max(9999.99);

    expect($field->getDefaultRules())->toBe(['numeric', 'min:0', 'max:9999.99']);
});

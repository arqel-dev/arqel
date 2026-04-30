<?php

declare(strict_types=1);

use Arqel\Fields\Types\TextField;
use Arqel\FieldsAdvanced\Steps\Step;

it('exposes the configured name, label and icon via getters', function (): void {
    $step = Step::make('details')
        ->label('Account Details')
        ->icon('info');

    expect($step->getName())->toBe('details')
        ->and($step->getLabel())->toBe('Account Details')
        ->and($step->getIcon())->toBe('info');
});

it('lets label() override the humanised default', function (): void {
    $step = Step::make('user_details')->label('Custom');

    expect($step->getLabel())->toBe('Custom');
});

it('falls back to a humanised name when no explicit label is set', function (): void {
    $step = Step::make('user_details');

    expect($step->getLabel())->toBe('User Details')
        ->and($step->getIcon())->toBeNull();
});

it('silently filters non-Field entries from the schema', function (): void {
    $step = Step::make('details')->schema([
        new TextField('name'),
        'not a field',
        42,
        null,
    ]);

    expect($step->getSchema())->toHaveCount(1)
        ->and($step->getSchema()[0])->toBeInstanceOf(TextField::class);
});

it('returns the canonical toArray shape', function (): void {
    $payload = Step::make('details')
        ->label('Details')
        ->icon('info')
        ->schema([new TextField('name')])
        ->toArray();

    expect(array_keys($payload))->toBe(['name', 'label', 'icon', 'schema'])
        ->and($payload['name'])->toBe('details')
        ->and($payload['label'])->toBe('Details')
        ->and($payload['icon'])->toBe('info')
        ->and($payload['schema'])->toHaveCount(1)
        ->and($payload['schema'][0]['name'])->toBe('name')
        ->and($payload['schema'][0]['type'])->toBe('text');
});

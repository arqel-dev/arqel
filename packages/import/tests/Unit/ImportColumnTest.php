<?php

declare(strict_types=1);

use Arqel\Import\ImportColumn;

it('exposes name and defaults label to the name', function (): void {
    $col = ImportColumn::make('email');

    expect($col->getName())->toBe('email')
        ->and($col->getLabel())->toBe('email')
        ->and($col->getRules())->toBe([])
        ->and($col->isMappingRequired())->toBeFalse();
});

it('is fluent for label, rules, required mapping', function (): void {
    $col = ImportColumn::make('email')
        ->label('E-mail')
        ->rules(['required', 'email'])
        ->requiredMapping();

    expect($col->getLabel())->toBe('E-mail')
        ->and($col->getRules())->toBe(['required', 'email'])
        ->and($col->isMappingRequired())->toBeTrue();
});

it('applies fillUsing to transform the raw value, else returns raw', function (): void {
    $transforming = ImportColumn::make('email')->fillUsing(fn (string $v) => strtolower(trim($v)));
    $plain = ImportColumn::make('name');

    expect($transforming->applyFill('  ADA@Example.com '))->toBe('ada@example.com')
        ->and($plain->applyFill('Ada'))->toBe('Ada');
});

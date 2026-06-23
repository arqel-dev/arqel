<?php

declare(strict_types=1);

use Arqel\Actions\Types\RowAction;
use Arqel\Fields\Types\TextField;

afterEach(function (): void {
    app()->setLocale('en');
});

it('uses each form field label as the :attribute name', function (): void {
    $action = RowAction::make('transfer')->form([
        (new TextField('new_owner'))->label('New Owner'),
        new TextField('reason'),
    ]);

    expect($action->getFormValidationAttributes())->toBe([
        'new_owner' => 'New Owner',
        // No explicit label → falls back to the humanised field name.
        'reason' => 'Reason',
    ]);
});

it('prefers an explicit validationAttribute over the label', function (): void {
    $action = RowAction::make('transfer')->form([
        (new TextField('new_owner'))->label('New Owner')->validationAttribute('Recipient'),
    ]);

    expect($action->getFormValidationAttributes())->toBe([
        'new_owner' => 'Recipient',
    ]);
});

it('localizes the :attribute label per active locale', function (): void {
    $action = RowAction::make('save')->form([
        (new TextField('mode'))->label('arqel::actions.edit'),
    ]);

    app()->setLocale('en');
    expect($action->getFormValidationAttributes()['mode'])->toBe('Edit');

    app()->setLocale('pt_BR');
    expect($action->getFormValidationAttributes()['mode'])->toBe('Editar');
});

it('exposes per-field custom validation messages keyed field.rule', function (): void {
    $action = RowAction::make('transfer')->form([
        (new TextField('reason'))
            ->required()
            ->validationMessage('required', 'A reason is required.'),
    ]);

    expect($action->getFormValidationMessages())->toBe([
        'reason.required' => 'A reason is required.',
    ]);
});

it('returns empty maps when no field declares labels or messages', function (): void {
    $action = RowAction::make('noop');

    expect($action->getFormValidationMessages())->toBe([])
        ->and($action->getFormValidationAttributes())->toBe([]);
});

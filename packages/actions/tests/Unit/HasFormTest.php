<?php

declare(strict_types=1);

use Arqel\Actions\Action;
use Arqel\Actions\Types\RowAction;
use Arqel\Fields\Types\SelectField;
use Arqel\Fields\Types\TextField;

it('has no form by default', function (): void {
    $action = RowAction::make('publish');

    expect($action->hasForm())->toBeFalse()
        ->and($action->getFormFields())->toBe([])
        ->and($action->getFormValidationRules())->toBe([])
        ->and($action->toArray())->not->toHaveKey('form');
});

it('attaches form fields and rejects non-Field entries', function (): void {
    $action = RowAction::make('transfer')->form([
        new TextField('reason'),
        'not-a-field',
        (new SelectField('new_owner'))->options(['1' => 'Alice', '2' => 'Bob']),
    ]);

    expect($action->hasForm())->toBeTrue()
        ->and($action->getFormFields())->toHaveCount(2);
});

it('serialises the form layout schema in toArray', function (): void {
    $action = RowAction::make('transfer')
        ->form([new TextField('reason'), new TextField('note')])
        ->modalSize(Action::MODAL_SIZE_LG);

    $payload = $action->toArray();

    expect($payload['modalSize'])->toBe('lg')
        ->and($payload['form'])->toBe([
            ['name' => 'reason', 'type' => 'text'],
            ['name' => 'note', 'type' => 'text'],
        ]);
});

it('serialises the full FieldSchema for form fields in toArray (#213)', function (): void {
    $action = RowAction::make('transfer')->form([
        (new SelectField('reason'))
            ->options(['a' => 'Alpha', 'b' => 'Beta'])
            ->required(),
        (new TextField('note'))->label('Note'),
    ]);

    $payload = $action->toArray();

    expect($payload)->toHaveKey('formFields')
        ->and($payload['formFields'])->toHaveCount(2);

    [$reason, $note] = $payload['formFields'];

    // SelectField options survive — the empty-modal bug was the select
    // arriving with no options to render.
    expect($reason['name'])->toBe('reason')
        ->and($reason['type'])->toBe('select')
        ->and($reason['required'])->toBeTrue()
        ->and($reason['props']['options'])->toBe(['a' => 'Alpha', 'b' => 'Beta'])
        ->and($reason['validation']['rules'])->toContain('required');

    // Labels survive too.
    expect($note['name'])->toBe('note')
        ->and($note['label'])->toBe('Note');
});

it('omits formFields when the action has no form (#213)', function (): void {
    $payload = RowAction::make('publish')->toArray();

    expect($payload)->not->toHaveKey('formFields');
});

it('aggregates validation rules per field name', function (): void {
    $action = RowAction::make('transfer')->form([
        (new TextField('reason'))->required()->rule('max:255'),
        (new TextField('note'))->nullable(),
    ]);

    $rules = $action->getFormValidationRules();

    expect($rules)->toHaveKeys(['reason', 'note'])
        ->and($rules['reason'])->toContain('required')
        ->and($rules['reason'])->toContain('max:255')
        ->and($rules['note'])->toContain('nullable');
});

it('modalWide flips between md and lg', function (): void {
    $action = RowAction::make('x');

    expect($action->modalWide()->getModalSize())->toBe('lg')
        ->and($action->modalWide(false)->getModalSize())->toBe('md');
});

it('modalSize falls back to md when invalid', function (): void {
    expect(RowAction::make('x')->modalSize('huge')->getModalSize())->toBe('md');
});

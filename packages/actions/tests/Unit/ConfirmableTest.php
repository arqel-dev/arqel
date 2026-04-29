<?php

declare(strict_types=1);

use Arqel\Actions\Types\RowAction;

it('does not require confirmation by default', function (): void {
    $action = RowAction::make('publish');

    expect($action->isRequiringConfirmation())->toBeFalse()
        ->and($action->getConfirmationConfig())->toBeNull()
        ->and($action->toArray())->not->toHaveKey('confirmation');
});

it('requiresConfirmation flag flips on the modal payload', function (): void {
    $action = RowAction::make('publish')->requiresConfirmation();

    expect($action->isRequiringConfirmation())->toBeTrue()
        ->and($action->getConfirmationConfig())->toMatchArray([
            'color' => 'destructive',
            'submitLabel' => 'Confirm',
            'cancelLabel' => 'Cancel',
        ]);
});

it('modalHeading auto-activates requiresConfirmation', function (): void {
    $action = RowAction::make('publish')->modalHeading('Publish post?');

    expect($action->isRequiringConfirmation())->toBeTrue()
        ->and($action->getConfirmationConfig()['heading'])->toBe('Publish post?');
});

it('modalDescription auto-activates requiresConfirmation', function (): void {
    $action = RowAction::make('archive')->modalDescription('Pode reverter em 30 dias.');

    expect($action->isRequiringConfirmation())->toBeTrue()
        ->and($action->getConfirmationConfig()['description'])->toBe('Pode reverter em 30 dias.');
});

it('modalConfirmationRequiresText auto-activates requiresConfirmation', function (): void {
    $action = RowAction::make('purge')->modalConfirmationRequiresText('DELETE');

    expect($action->isRequiringConfirmation())->toBeTrue()
        ->and($action->getConfirmationConfig()['requiresText'])->toBe('DELETE');
});

it('modalColor accepts only the canonical palette and falls back to destructive otherwise', function (): void {
    expect(
        RowAction::make('a')->modalColor('warning')->requiresConfirmation()
            ->getConfirmationConfig()['color'],
    )->toBe('warning');

    expect(
        RowAction::make('b')->modalColor('info')->requiresConfirmation()
            ->getConfirmationConfig()['color'],
    )->toBe('info');

    expect(
        RowAction::make('c')->modalColor('rainbow')->requiresConfirmation()
            ->getConfirmationConfig()['color'],
    )->toBe('destructive');
});

it('serialises confirmation in toArray when required', function (): void {
    $payload = RowAction::make('archive')
        ->modalHeading('Archive?')
        ->modalDescription('You can restore later.')
        ->modalColor('warning')
        ->modalSubmitButtonLabel('Yes, archive')
        ->modalCancelButtonLabel('Keep')
        ->toArray();

    expect($payload)->toHaveKey('requiresConfirmation', true)
        ->and($payload['confirmation'])->toMatchArray([
            'heading' => 'Archive?',
            'description' => 'You can restore later.',
            'color' => 'warning',
            'submitLabel' => 'Yes, archive',
            'cancelLabel' => 'Keep',
        ]);
});

it('omits confirmation from toArray when not required', function (): void {
    $payload = RowAction::make('soft')->toArray();

    expect($payload)->not->toHaveKey('confirmation')
        ->and($payload)->not->toHaveKey('requiresConfirmation');
});

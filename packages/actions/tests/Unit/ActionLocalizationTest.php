<?php

declare(strict_types=1);

use Arqel\Actions\Types\RowAction;

afterEach(function (): void {
    app()->setLocale('en');
});

it('localizes label/tooltip/notifications from translation keys at serialization', function (): void {
    app()->setLocale('pt_BR');

    $payload = RowAction::make('delete')
        ->label('arqel::actions.delete')
        ->tooltip('arqel::actions.delete')
        ->successNotification('arqel::messages.flash.deleted')
        ->action(fn ($record) => $record)
        ->toArray();

    expect($payload['label'])->toBe('Excluir')
        ->and($payload['tooltip'])->toBe('Excluir')
        ->and($payload['successNotification'])->toBe(__('arqel::messages.flash.deleted'));
});

it('respects the active locale when re-serializing the same action', function (): void {
    $action = RowAction::make('delete')->label('arqel::actions.delete');

    app()->setLocale('en');
    expect($action->toArray()['label'])->toBe('Delete');

    app()->setLocale('pt_BR');
    expect($action->toArray()['label'])->toBe('Excluir');
});

it('passes through a plain (non-key) label verbatim', function (): void {
    app()->setLocale('pt_BR');

    expect(RowAction::make('publish')->label('Publish Now')->toArray()['label'])
        ->toBe('Publish Now');
});

it('localizes default confirm/cancel button labels per locale', function (): void {
    app()->setLocale('en');
    $en = RowAction::make('archive')->requiresConfirmation()->getConfirmationConfig();
    expect($en)->toMatchArray(['submitLabel' => 'Confirm', 'cancelLabel' => 'Cancel']);

    app()->setLocale('pt_BR');
    $pt = RowAction::make('archive')->requiresConfirmation()->getConfirmationConfig();
    expect($pt)->toMatchArray(['submitLabel' => 'Confirmar', 'cancelLabel' => 'Cancelar']);
});

it('localizes a custom confirm label when it is a translation key', function (): void {
    app()->setLocale('pt_BR');

    $config = RowAction::make('archive')
        ->requiresConfirmation()
        ->modalSubmitButtonLabel('arqel::actions.confirm.submit')
        ->getConfirmationConfig();

    expect($config['submitLabel'])->toBe('Confirmar');
});

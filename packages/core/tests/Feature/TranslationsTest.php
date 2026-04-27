<?php

declare(strict_types=1);

it('resolves the en messages namespace', function (): void {
    app()->setLocale('en');

    expect(__('arqel::messages.actions.create'))->toBe('Create')
        ->and(__('arqel::messages.actions.cancel'))->toBe('Cancel')
        ->and(__('arqel::messages.confirmation.delete'))->toBe('Are you sure you want to delete this?');
});

it('resolves the pt_BR messages namespace', function (): void {
    app()->setLocale('pt_BR');

    expect(__('arqel::messages.actions.create'))->toBe('Criar')
        ->and(__('arqel::messages.actions.cancel'))->toBe('Cancelar')
        ->and(__('arqel::messages.confirmation.delete'))->toBe('Tem certeza que deseja excluir?');
});

it('exposes table, form and actions namespaces in both locales', function (): void {
    app()->setLocale('en');

    expect(__('arqel::table.empty'))->toBe('No records found.')
        ->and(__('arqel::form.submit'))->toBe('Submit')
        ->and(__('arqel::actions.delete'))->toBe('Delete');

    app()->setLocale('pt_BR');

    expect(__('arqel::table.empty'))->toBe('Nenhum registro encontrado.')
        ->and(__('arqel::form.submit'))->toBe('Enviar')
        ->and(__('arqel::actions.delete'))->toBe('Excluir');
});

it('falls back to en when the locale is missing', function (): void {
    app()->setLocale('xx_YY');
    app()->setFallbackLocale('en');

    expect(__('arqel::messages.actions.create'))->toBe('Create');
});

it('substitutes placeholders in pagination strings', function (): void {
    app()->setLocale('en');

    expect(__('arqel::table.pagination.showing', ['from' => 1, 'to' => 10, 'total' => 42]))
        ->toBe('Showing 1 to 10 of 42 results');
});

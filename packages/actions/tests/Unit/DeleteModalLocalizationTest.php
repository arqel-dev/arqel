<?php

declare(strict_types=1);

use Arqel\Actions\Actions;

it('localizes the built-in delete modal under pt_BR', function (): void {
    app()->setLocale('pt_BR');

    $config = Actions::delete()->getConfirmationConfig();

    expect($config['heading'])->toBe('Excluir registro?')
        ->and($config['description'])->toBe('Esta ação não pode ser desfeita.')
        ->and($config['submitLabel'])->toBe('Excluir');
});

it('keeps the built-in delete modal English under en (stability)', function (): void {
    app()->setLocale('en');

    $config = Actions::delete()->getConfirmationConfig();

    expect($config['heading'])->toBe('Delete record?')
        ->and($config['description'])->toBe('This action cannot be undone.')
        ->and($config['submitLabel'])->toBe('Delete');
});

it('localizes the built-in bulk-delete modal under pt_BR', function (): void {
    app()->setLocale('pt_BR');

    $config = Actions::deleteBulk()->getConfirmationConfig();

    expect($config['heading'])->toBe('Excluir registros selecionados?')
        ->and($config['description'])->toBe('Esta ação não pode ser desfeita.')
        ->and($config['submitLabel'])->toBe('Excluir');
});

it('keeps the built-in bulk-delete modal English under en (stability)', function (): void {
    app()->setLocale('en');

    $config = Actions::deleteBulk()->getConfirmationConfig();

    expect($config['heading'])->toBe('Delete selected records?')
        ->and($config['description'])->toBe('This action cannot be undone.')
        ->and($config['submitLabel'])->toBe('Delete');
});

it('localizes the built-in bulk-delete button label under pt_BR', function (): void {
    app()->setLocale('pt_BR');

    $array = Actions::deleteBulk()->toArray();

    expect($array['label'])->toBe('Excluir selecionados');
});

it('keeps the built-in bulk-delete button label English under en (stability)', function (): void {
    app()->setLocale('en');

    $array = Actions::deleteBulk()->toArray();

    expect($array['label'])->toBe('Delete selected');
});

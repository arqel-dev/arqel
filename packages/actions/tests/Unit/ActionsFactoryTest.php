<?php

declare(strict_types=1);

use Arqel\Actions\Actions;
use Arqel\Actions\Types\BulkAction;
use Arqel\Actions\Types\RowAction;
use Arqel\Actions\Types\ToolbarAction;

it('Actions::view returns a ghost secondary RowAction', function (): void {
    $action = Actions::view();

    expect($action)->toBeInstanceOf(RowAction::class)
        ->and($action->getName())->toBe('view')
        ->and($action->getColor())->toBe('secondary');
});

it('Actions::edit returns a ghost primary RowAction', function (): void {
    $action = Actions::edit();

    expect($action)->toBeInstanceOf(RowAction::class)
        ->and($action->getName())->toBe('edit')
        ->and($action->getColor())->toBe('primary');
});

it('Actions::delete is destructive and pre-confirms', function (): void {
    $action = Actions::delete();

    $config = $action->getConfirmationConfig();

    expect($action)->toBeInstanceOf(RowAction::class)
        ->and($action->getColor())->toBe('destructive')
        ->and($action->isRequiringConfirmation())->toBeTrue()
        ->and($config['heading'])->toBe('Delete record?')
        ->and($config['submitLabel'])->toBe('Delete')
        ->and($config['color'])->toBe('destructive');
});

it('Actions::create returns a ToolbarAction', function (): void {
    expect(Actions::create())->toBeInstanceOf(ToolbarAction::class);
});

it('Actions::deleteBulk returns a BulkAction with confirmation', function (): void {
    $action = Actions::deleteBulk();

    expect($action)->toBeInstanceOf(BulkAction::class)
        ->and($action->isRequiringConfirmation())->toBeTrue()
        // getLabel() returns the raw stored key; toArray() localizes it (en default).
        ->and($action->toArray()['label'])->toBe('Delete selected');
});

it('Actions::restore returns a success-coloured RowAction', function (): void {
    expect(Actions::restore()->getColor())->toBe('success');
});

it('localizes the built-in factory labels by the active locale', function (): void {
    // English (default) keeps the original literals for accessible-name stability.
    expect(Actions::view()->toArray()['label'])->toBe('View')
        ->and(Actions::edit()->toArray()['label'])->toBe('Edit')
        ->and(Actions::restore()->toArray()['label'])->toBe('Restore')
        ->and(Actions::create()->toArray()['label'])->toBe('Create');

    app()->setLocale('pt_BR');

    // pt_BR resolves the arqel::actions.* keys the factories now seed.
    expect(Actions::view()->toArray()['label'])->toBe('Visualizar')
        ->and(Actions::edit()->toArray()['label'])->toBe('Editar')
        ->and(Actions::restore()->toArray()['label'])->toBe('Restaurar')
        ->and(Actions::create()->toArray()['label'])->toBe('Criar');
});

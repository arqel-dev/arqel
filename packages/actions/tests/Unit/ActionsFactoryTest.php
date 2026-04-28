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
        ->and($action->getLabel())->toBe('Delete selected');
});

it('Actions::restore returns a success-coloured RowAction', function (): void {
    expect(Actions::restore()->getColor())->toBe('success');
});

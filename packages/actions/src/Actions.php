<?php

declare(strict_types=1);

namespace Arqel\Actions;

use Arqel\Actions\Types\BulkAction;
use Arqel\Actions\Types\RowAction;
use Arqel\Actions\Types\ToolbarAction;

/**
 * Factory of pre-configured built-in actions covering the most
 * common admin verbs. Returned instances are fully configurable
 * — these factories only seed sensible defaults.
 */
final class Actions
{
    public static function view(): RowAction
    {
        return RowAction::make('view')
            ->icon('eye')
            ->color(Action::COLOR_SECONDARY)
            ->variant(Action::VARIANT_GHOST);
    }

    public static function edit(): RowAction
    {
        return RowAction::make('edit')
            ->icon('pencil')
            ->color(Action::COLOR_PRIMARY)
            ->variant(Action::VARIANT_GHOST);
    }

    public static function delete(): RowAction
    {
        return RowAction::make('delete')
            ->icon('trash')
            ->color(Action::COLOR_DESTRUCTIVE)
            ->variant(Action::VARIANT_GHOST)
            ->requiresConfirmation()
            ->modalHeading('arqel::actions.delete_confirm.heading')
            ->modalDescription('arqel::actions.delete_confirm.description')
            ->modalColor(Action::MODAL_COLOR_DESTRUCTIVE)
            ->modalSubmitButtonLabel('arqel::actions.delete');
    }

    public static function restore(): RowAction
    {
        return RowAction::make('restore')
            ->icon('arrow-uturn-left')
            ->color(Action::COLOR_SUCCESS)
            ->variant(Action::VARIANT_GHOST);
    }

    public static function create(): ToolbarAction
    {
        return ToolbarAction::make('create')
            ->icon('plus')
            ->color(Action::COLOR_PRIMARY)
            ->variant(Action::VARIANT_DEFAULT);
    }

    public static function deleteBulk(): BulkAction
    {
        return BulkAction::make('delete')
            ->label('Delete selected')
            ->icon('trash')
            ->color(Action::COLOR_DESTRUCTIVE)
            ->variant(Action::VARIANT_OUTLINE)
            ->requiresConfirmation()
            ->modalHeading('arqel::actions.delete_bulk.heading')
            ->modalDescription('arqel::actions.delete_bulk.description')
            ->modalColor(Action::MODAL_COLOR_DESTRUCTIVE)
            ->modalSubmitButtonLabel('arqel::actions.delete');
    }
}

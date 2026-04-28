<?php

declare(strict_types=1);

use Arqel\Table\Table;

it('chains fluent setters and exposes them via getters', function (): void {
    $table = (new Table)
        ->columns(['col1', 'col2'])
        ->filters(['filter1'])
        ->actions(['edit', 'delete'])
        ->bulkActions(['bulkDelete'])
        ->toolbarActions(['export'])
        ->defaultSort('created_at', Table::DIRECTION_DESC)
        ->perPage(50)
        ->searchable(false)
        ->selectable(false)
        ->striped()
        ->compact()
        ->emptyState('Nothing here yet', 'Add your first record', 'inbox');

    expect($table->getColumns())->toBe(['col1', 'col2'])
        ->and($table->getFilters())->toBe(['filter1'])
        ->and($table->getActions())->toBe(['edit', 'delete'])
        ->and($table->getBulkActions())->toBe(['bulkDelete'])
        ->and($table->getToolbarActions())->toBe(['export'])
        ->and($table->getDefaultSortColumn())->toBe('created_at')
        ->and($table->getDefaultSortDirection())->toBe(Table::DIRECTION_DESC)
        ->and($table->getDefaultPerPage())->toBe(50)
        ->and($table->isSearchable())->toBeFalse()
        ->and($table->isSelectable())->toBeFalse()
        ->and($table->isStriped())->toBeTrue()
        ->and($table->isCompact())->toBeTrue();
});

it('uses sane defaults', function (): void {
    $table = new Table;

    expect($table->getDefaultPerPage())->toBe(25)
        ->and($table->getPerPageOptions())->toBe([10, 25, 50, 100])
        ->and($table->getDefaultSortColumn())->toBeNull()
        ->and($table->getDefaultSortDirection())->toBe('desc')
        ->and($table->isSearchable())->toBeTrue()
        ->and($table->isSelectable())->toBeTrue()
        ->and($table->isStriped())->toBeFalse()
        ->and($table->isCompact())->toBeFalse();
});

it('rejects unknown sort directions and falls back to desc', function (): void {
    $table = (new Table)->defaultSort('name', 'sideways');

    expect($table->getDefaultSortDirection())->toBe('desc');
});

it('honours an asc default sort when explicitly requested', function (): void {
    $table = (new Table)->defaultSort('name', Table::DIRECTION_ASC);

    expect($table->getDefaultSortDirection())->toBe('asc');
});

it('inserts a default perPage that is not in the options list', function (): void {
    $table = (new Table)->perPage(7, [10, 25, 50]);

    expect($table->getDefaultPerPage())->toBe(7)
        ->and($table->getPerPageOptions())->toBe([7, 10, 25, 50]);
});

it('keeps the existing options list when default is already present', function (): void {
    $table = (new Table)->perPage(50);

    expect($table->getPerPageOptions())->toBe([10, 25, 50, 100]);
});

it('serialises the table schema to an Inertia-shaped array', function (): void {
    $table = (new Table)
        ->columns(['name', 'email'])
        ->filters(['status'])
        ->actions(['edit'])
        ->bulkActions(['bulkDelete'])
        ->toolbarActions(['export'])
        ->defaultSort('created_at', 'desc')
        ->perPage(50)
        ->striped()
        ->emptyState('No users yet', 'Add the first one', 'users');

    $payload = $table->toArray();

    expect($payload['columns'])->toBe(['name', 'email'])
        ->and($payload['filters'])->toBe(['status'])
        ->and($payload['actions'])->toBe(['edit'])
        ->and($payload['bulkActions'])->toBe(['bulkDelete'])
        ->and($payload['toolbarActions'])->toBe(['export'])
        ->and($payload['config']['defaultPerPage'])->toBe(50)
        ->and($payload['config']['defaultSort'])->toBe(['column' => 'created_at', 'direction' => 'desc'])
        ->and($payload['config']['searchable'])->toBeTrue()
        ->and($payload['config']['striped'])->toBeTrue()
        ->and($payload['emptyState'])->toBe([
            'heading' => 'No users yet',
            'description' => 'Add the first one',
            'icon' => 'users',
        ]);
});

it('omits defaultSort and emptyState when not configured', function (): void {
    $payload = (new Table)->toArray();

    expect($payload['config']['defaultSort'])->toBeNull()
        ->and($payload['emptyState'])->toBeNull();
});

<?php

declare(strict_types=1);

use Arqel\Export\Actions\ExportAction;
use Arqel\Export\ExportFormat;

it('creates an action with sensible defaults', function (): void {
    $action = ExportAction::make('export');

    expect($action)->toBeInstanceOf(ExportAction::class);
    expect($action->getName())->toBe('export');
    expect($action->getLabel())->toBe('Export');
    expect($action->getType())->toBe('bulk');
    expect($action->getFormat())->toBe(ExportFormat::CSV);
});

it('accepts a custom action name', function (): void {
    $action = ExportAction::make('downloadCsv');

    expect($action->getName())->toBe('downloadCsv');
});

it('exposes a fluent format setter', function (): void {
    $action = ExportAction::make('export');
    $returned = $action->format(ExportFormat::XLSX);

    expect($returned)->toBe($action);
    expect($action->getFormat())->toBe(ExportFormat::XLSX);
});

it('throws RuntimeException when execute is invoked (wired in EXPORT-005)', function (): void {
    ExportAction::make('export')->execute();
})->throws(RuntimeException::class, 'Wired in EXPORT-005');

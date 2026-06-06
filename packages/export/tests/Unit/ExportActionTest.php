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

it('exposes a fluent withColumns setter', function (): void {
    $action = ExportAction::make('export');
    $returned = $action->withColumns([['name' => 'id']]);

    expect($returned)->toBe($action);
});

it('exposes a fluent withDestinationDir setter', function (): void {
    $action = ExportAction::make('export');
    $returned = $action->withDestinationDir('/tmp');

    expect($returned)->toBe($action);
});

it('exposes a fluent dryRun setter', function (): void {
    $action = ExportAction::make('export');
    $returned = $action->dryRun();

    expect($returned)->toBe($action);
});

it('serialises a stock bulk url targeting the core bulk route (#48)', function (): void {
    // ExportAction overrides execute() and never declares a callback or
    // url, so before the fix toArray() shipped no url and the frontend
    // POSTed to an unregistered route. The base bulk stock-url arm must
    // now emit /admin/{slug}/bulk/{name} via POST.
    $resource = new class
    {
        public static ?string $slug = 'posts';
    };

    $array = ExportAction::make('export')->toArray(null, null, $resource);

    expect($array['url'])->toBe('/admin/posts/bulk/export')
        ->and($array['method'])->toBe('POST');
});

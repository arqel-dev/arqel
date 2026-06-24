<?php

declare(strict_types=1);

use Arqel\Export\Exporters\CsvExporter;
use Arqel\Export\Exporters\PdfExporter;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\App;

beforeEach(function (): void {
    $this->tempFile = tempnam(sys_get_temp_dir(), 'arqel-bool-');
});

afterEach(function (): void {
    if (isset($this->tempFile) && is_string($this->tempFile) && file_exists($this->tempFile)) {
        @unlink($this->tempFile);
    }

    App::setLocale('en');
});

it('exports boolean cells as English Yes/No under the en locale', function (): void {
    App::setLocale('en');

    $columns = [
        ['name' => 'active', 'label' => 'Active', 'type' => 'boolean'],
    ];

    (new CsvExporter)->export(
        [['active' => true], ['active' => false]],
        $columns,
        $this->tempFile,
    );

    $parsed = readCsvRows($this->tempFile);

    expect($parsed)->toBe([
        ['Active'],
        ['Yes'],
        ['No'],
    ]);
});

it('localizes boolean cells to Sim/Não under the pt_BR locale', function (): void {
    App::setLocale('pt_BR');

    $columns = [
        ['name' => 'active', 'label' => 'Active', 'type' => 'boolean'],
    ];

    (new CsvExporter)->export(
        [['active' => true], ['active' => false]],
        $columns,
        $this->tempFile,
    );

    $parsed = readCsvRows($this->tempFile);

    expect($parsed)->toBe([
        ['Active'],
        ['Sim'],
        ['Não'],
    ]);
});

it('localizes a computed boolean cell via the state_resolver branch under pt_BR', function (): void {
    App::setLocale('pt_BR');

    $columns = [
        [
            'name' => 'active',
            'label' => 'Active',
            'type' => 'boolean',
            'state_resolver' => static fn (mixed $record): bool => (bool) ($record['active'] ?? false),
        ],
    ];

    (new CsvExporter)->export(
        [['active' => true]],
        $columns,
        $this->tempFile,
    );

    $parsed = readCsvRows($this->tempFile);

    expect($parsed)->toBe([
        ['Active'],
        ['Sim'],
    ]);
});

it('localizes the PDF document title to Exportação under pt_BR', function (): void {
    if (! class_exists(Dompdf::class)) {
        $this->markTestSkipped('dompdf/dompdf required for PdfExporter tests.');
    }

    App::setLocale('pt_BR');

    $exporter = new PdfExporter;
    $method = (new ReflectionClass($exporter))->getMethod('renderHtml');
    $method->setAccessible(true);

    $html = $method->invoke(
        $exporter,
        [['id' => 1]],
        [['name' => 'id', 'label' => 'ID', 'type' => 'string']],
    );

    expect($html)->toContain('<h1>Exportação</h1>')
        ->and($html)->not->toContain('<h1>Export</h1>');
});

it('keeps the PDF document title as Export under the en locale', function (): void {
    if (! class_exists(Dompdf::class)) {
        $this->markTestSkipped('dompdf/dompdf required for PdfExporter tests.');
    }

    App::setLocale('en');

    $exporter = new PdfExporter;
    $method = (new ReflectionClass($exporter))->getMethod('renderHtml');
    $method->setAccessible(true);

    $html = $method->invoke(
        $exporter,
        [['id' => 1]],
        [['name' => 'id', 'label' => 'ID', 'type' => 'string']],
    );

    expect($html)->toContain('<h1>Export</h1>');
});

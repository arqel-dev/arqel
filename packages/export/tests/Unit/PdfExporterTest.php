<?php

declare(strict_types=1);

use Arqel\Export\Exporters\PdfExporter;
use Dompdf\Dompdf;

beforeEach(function (): void {
    if (! class_exists(Dompdf::class)) {
        $this->markTestSkipped('dompdf/dompdf required for PdfExporter tests.');
    }

    if (! extension_loaded('mbstring')) {
        $this->markTestSkipped('ext-mbstring required by dompdf.');
    }

    $this->tempFile = tempnam(sys_get_temp_dir(), 'arqel-pdf-');
    @unlink($this->tempFile);
    $this->tempFile .= '.pdf';
});

afterEach(function (): void {
    if (isset($this->tempFile) && is_string($this->tempFile) && file_exists($this->tempFile)) {
        @unlink($this->tempFile);
    }
});

it('writes a valid PDF file to the destination and returns the path', function (): void {
    $columns = [
        ['name' => 'id', 'label' => 'ID', 'type' => 'string'],
        ['name' => 'name', 'label' => 'Name', 'type' => 'string'],
    ];

    $rows = [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ];

    $result = (new PdfExporter)->export($rows, $columns, $this->tempFile);

    expect($result)->toBe($this->tempFile);
    expect(file_exists($this->tempFile))->toBeTrue();

    $head = (string) file_get_contents($this->tempFile, false, null, 0, 4);
    expect($head)->toBe('%PDF');
});

it('produces a valid PDF when given an empty rows iterable', function (): void {
    $columns = [
        ['name' => 'id', 'label' => 'ID', 'type' => 'string'],
    ];

    (new PdfExporter)->export([], $columns, $this->tempFile);

    expect(file_exists($this->tempFile))->toBeTrue();

    $head = (string) file_get_contents($this->tempFile, false, null, 0, 4);
    expect($head)->toBe('%PDF');
});

it('exposes a fluent setOrientation that persists the value', function (): void {
    $exporter = new PdfExporter;

    $result = $exporter->setOrientation('landscape');

    expect($result)->toBe($exporter);

    $reflection = new ReflectionClass($exporter);
    $property = $reflection->getProperty('orientation');
    $property->setAccessible(true);

    expect($property->getValue($exporter))->toBe('landscape');
});

it('exposes a fluent setPaperSize that persists the value', function (): void {
    $exporter = new PdfExporter;

    $result = $exporter->setPaperSize('letter');

    expect($result)->toBe($exporter);

    $reflection = new ReflectionClass($exporter);
    $property = $reflection->getProperty('paperSize');
    $property->setAccessible(true);

    expect($property->getValue($exporter))->toBe('letter');
});

it('formats boolean cells as Yes or No via formatCell', function (): void {
    $exporter = new PdfExporter;
    $method = (new ReflectionClass($exporter))->getMethod('formatCell');
    $method->setAccessible(true);

    $column = ['name' => 'active', 'type' => 'boolean'];

    expect($method->invoke($exporter, ['active' => true], $column))->toBe('Yes');
    expect($method->invoke($exporter, ['active' => false], $column))->toBe('No');
});

it('formats DateTimeInterface as Y-m-d for date columns via formatCell', function (): void {
    $exporter = new PdfExporter;
    $method = (new ReflectionClass($exporter))->getMethod('formatCell');
    $method->setAccessible(true);

    $column = ['name' => 'created_at', 'type' => 'date'];
    $record = ['created_at' => new DateTime('2026-04-29 10:00:00')];

    expect($method->invoke($exporter, $record, $column))->toBe('2026-04-29');
});

it('follows display_path for relationship columns via formatCell', function (): void {
    $exporter = new PdfExporter;
    $method = (new ReflectionClass($exporter))->getMethod('formatCell');
    $method->setAccessible(true);

    $column = [
        'name' => 'author',
        'type' => 'relationship',
        'display_path' => 'author.name',
    ];

    $record = ['author' => ['name' => 'Carol']];

    expect($method->invoke($exporter, $record, $column))->toBe('Carol');
});

it('stringifies scalar fallback values and treats null as empty string via formatCell', function (): void {
    $exporter = new PdfExporter;
    $method = (new ReflectionClass($exporter))->getMethod('formatCell');
    $method->setAccessible(true);

    $column = ['name' => 'sku', 'type' => 'string'];

    expect($method->invoke($exporter, ['sku' => 'ABC-123'], $column))->toBe('ABC-123');
    expect($method->invoke($exporter, ['sku' => null], $column))->toBe('');
    expect($method->invoke($exporter, ['sku' => 42], $column))->toBe('42');
});

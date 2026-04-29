<?php

declare(strict_types=1);

use Arqel\Export\ExportFormat;

it('exposes three cases', function (): void {
    expect(ExportFormat::cases())->toHaveCount(3);
    expect(ExportFormat::CSV->value)->toBe('csv');
    expect(ExportFormat::XLSX->value)->toBe('xlsx');
    expect(ExportFormat::PDF->value)->toBe('pdf');
});

it('returns canonical mime types', function (): void {
    expect(ExportFormat::CSV->mimeType())->toBe('text/csv');
    expect(ExportFormat::XLSX->mimeType())->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect(ExportFormat::PDF->mimeType())->toBe('application/pdf');
});

it('returns file extension matching the value', function (): void {
    foreach (ExportFormat::cases() as $format) {
        expect($format->extension())->toBe($format->value);
    }
});

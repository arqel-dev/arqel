<?php

declare(strict_types=1);

use Arqel\Import\ImportFormat;

it('maps cases to extensions', function (): void {
    expect(ImportFormat::CSV->extension())->toBe('csv')
        ->and(ImportFormat::XLSX->extension())->toBe('xlsx');
});

it('resolves a format from a file extension case-insensitively', function (): void {
    expect(ImportFormat::fromExtension('CSV'))->toBe(ImportFormat::CSV)
        ->and(ImportFormat::fromExtension('xlsx'))->toBe(ImportFormat::XLSX);
});

it('throws on an unsupported extension', function (): void {
    ImportFormat::fromExtension('pdf');
})->throws(InvalidArgumentException::class);

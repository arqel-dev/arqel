<?php

declare(strict_types=1);

use Arqel\Import\Contracts\ImportLogger;
use Arqel\Import\ImportFormat;
use Arqel\Import\Logging\NullImportLogger;

it('is bound as the default ImportLogger', function (): void {
    expect(app(ImportLogger::class))->toBeInstanceOf(NullImportLogger::class);
});

it('accepts all lifecycle calls as no-ops', function (): void {
    $logger = new NullImportLogger;
    $logger->logQueued('id', ImportFormat::CSV);
    $logger->progress('id', 5, 1);
    $logger->logCompleted('id', 5, 1, '/tmp/failed.csv');
    $logger->logFailed('id', ImportFormat::CSV, new RuntimeException('x'));

    expect(true)->toBeTrue();
});

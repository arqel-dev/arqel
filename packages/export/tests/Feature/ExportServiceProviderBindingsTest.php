<?php

declare(strict_types=1);

use Arqel\Export\Contracts\ExportLogger;
use Arqel\Export\Logging\NullExportLogger;

it('binds NullExportLogger as the default ExportLogger', function (): void {
    expect(app(ExportLogger::class))->toBeInstanceOf(NullExportLogger::class);
});

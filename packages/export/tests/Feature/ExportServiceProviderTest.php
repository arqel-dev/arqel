<?php

declare(strict_types=1);

use Arqel\Export\ExportServiceProvider;

it('boots the export service provider', function (): void {
    expect(app()->getProvider(ExportServiceProvider::class))->not->toBeNull();
});

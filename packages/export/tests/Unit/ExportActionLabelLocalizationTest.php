<?php

declare(strict_types=1);

use Arqel\Export\Actions\ExportAction;

it('localizes the built-in export button label under pt_BR', function (): void {
    app()->setLocale('pt_BR');

    $array = ExportAction::make('export')->toArray();

    expect($array['label'])->toBe('Exportar');
});

it('keeps the built-in export button label English under en (stability)', function (): void {
    app()->setLocale('en');

    $array = ExportAction::make('export')->toArray();

    expect($array['label'])->toBe('Export');
});

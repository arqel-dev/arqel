<?php

declare(strict_types=1);

use Arqel\Import\ImportServiceProvider;

it('boots the import service provider', function (): void {
    expect(app()->getProvider(ImportServiceProvider::class))->not->toBeNull();
});

it('registers the arqel-import translation namespace', function (): void {
    expect(__('arqel-import::import.action'))->toBe('Import')
        ->and(__('arqel-import::import.queued'))->toBe('Import queued. You will be notified when it finishes.');
});

it('translates the pt_BR locale for import keys', function (): void {
    app()->setLocale('pt_BR');

    expect(__('arqel-import::import.action'))->toBe('Importar')
        ->and(__('arqel-import::import.queued'))->toBe('Importação na fila. Você será notificado quando terminar.');

    app()->setLocale('en');
});

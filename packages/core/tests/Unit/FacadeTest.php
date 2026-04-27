<?php

declare(strict_types=1);

use Arqel\Core\ArqelServiceProvider;
use Arqel\Core\Facades\Arqel;
use Arqel\Core\Panel\PanelRegistry;

it('points the Arqel facade at the "arqel" container alias', function (): void {
    expect(Arqel::getFacadeRoot())->toBeInstanceOf(PanelRegistry::class);
});

it('exposes the facade accessor as a class constant', function (): void {
    expect(ArqelServiceProvider::FACADE_ACCESSOR)->toBe('arqel');
});

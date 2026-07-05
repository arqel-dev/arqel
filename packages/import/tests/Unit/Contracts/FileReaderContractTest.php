<?php

declare(strict_types=1);

use Arqel\Import\Contracts\FileReader;

it('declares a read method returning iterable', function (): void {
    $reflection = new ReflectionMethod(FileReader::class, 'read');

    expect($reflection->getReturnType()?->getName())->toBe('iterable')
        ->and($reflection->getNumberOfParameters())->toBe(1);
});

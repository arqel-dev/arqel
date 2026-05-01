<?php

declare(strict_types=1);

use Arqel\Marketplace\Services\ConventionValidationResult;

it('cria resultado de sucesso com warnings derivados dos checks', function (): void {
    $checks = [
        ['name' => 'a', 'status' => 'ok', 'message' => 'fine'],
        ['name' => 'b', 'status' => 'warn', 'message' => 'careful'],
    ];
    $result = ConventionValidationResult::success($checks);

    expect($result->passed)->toBeTrue()
        ->and($result->errors)->toBe([])
        ->and($result->warnings)->toBe(['careful'])
        ->and($result->checks)->toBe($checks);
});

it('cria resultado falho com errors e warnings derivados', function (): void {
    $checks = [
        ['name' => 'a', 'status' => 'fail', 'message' => 'bad'],
        ['name' => 'b', 'status' => 'warn', 'message' => 'meh'],
        ['name' => 'c', 'status' => 'ok', 'message' => 'fine'],
    ];
    $result = ConventionValidationResult::failed($checks);

    expect($result->passed)->toBeFalse()
        ->and($result->errors)->toBe(['bad'])
        ->and($result->warnings)->toBe(['meh']);
});

it('toArray retorna todos os campos esperados', function (): void {
    $result = ConventionValidationResult::success([]);
    $arr = $result->toArray();

    expect($arr)->toHaveKeys(['checks', 'passed', 'warnings', 'errors']);
});

it('é imutável (readonly) — propriedades são marcadas como readonly via reflection', function (): void {
    $reflection = new ReflectionClass(ConventionValidationResult::class);

    foreach (['checks', 'passed', 'warnings', 'errors'] as $name) {
        $property = $reflection->getProperty($name);
        expect($property->isReadOnly())->toBeTrue("Property {$name} must be readonly");
    }
});

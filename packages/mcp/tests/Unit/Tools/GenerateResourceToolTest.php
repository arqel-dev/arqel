<?php

declare(strict_types=1);

use Arqel\Mcp\Tools\GenerateResourceTool;

it('exposes the canonical tool schema', function (): void {
    $tool = new GenerateResourceTool;

    $schema = $tool->schema();

    expect($schema['name'])->toBe('generate_resource')
        ->and($schema['description'])->toBe('Generate a new Arqel Resource for an Eloquent model')
        ->and($schema['inputSchema']['type'])->toBe('object')
        ->and($schema['inputSchema']['required'])->toBe(['model'])
        ->and($schema['inputSchema']['properties'])->toHaveKeys(['model', 'fromModel', 'withPolicy'])
        ->and($schema['inputSchema']['properties']['model']['type'])->toBe('string')
        ->and($schema['inputSchema']['properties']['fromModel']['type'])->toBe('boolean')
        ->and($schema['inputSchema']['properties']['fromModel']['default'])->toBeTrue()
        ->and($schema['inputSchema']['properties']['withPolicy']['type'])->toBe('boolean')
        ->and($schema['inputSchema']['properties']['withPolicy']['default'])->toBeTrue();
});

it('runs the happy path and returns success payload', function (): void {
    $captured = [];
    $runner = function (array $args) use (&$captured): array {
        $captured = $args;

        return ['exitCode' => 0, 'output' => 'created'];
    };

    $tool = new GenerateResourceTool($runner);
    $result = $tool(['model' => 'User']);

    expect($result)->toBe([
        'model' => 'User',
        'exitCode' => 0,
        'output' => 'created',
        'success' => true,
    ])
        ->and($captured)->toBe([
            'model' => 'User',
            '--from-model' => true,
            '--with-policy' => true,
        ]);
});

it('throws InvalidArgumentException when model is missing', function (): void {
    $tool = new GenerateResourceTool(fn (array $args): array => ['exitCode' => 0, 'output' => '']);

    $tool([]);
})->throws(InvalidArgumentException::class, "'model' parameter is required and must be a string");

it('throws InvalidArgumentException when model is not a string', function (): void {
    $tool = new GenerateResourceTool(fn (array $args): array => ['exitCode' => 0, 'output' => '']);

    $tool(['model' => 42]);
})->throws(InvalidArgumentException::class, "'model' parameter is required and must be a string");

it('forwards fromModel and withPolicy flags to the runner', function (): void {
    $captured = [];
    $runner = function (array $args) use (&$captured): array {
        $captured = $args;

        return ['exitCode' => 0, 'output' => ''];
    };

    $tool = new GenerateResourceTool($runner);
    $tool(['model' => 'Post', 'fromModel' => false, 'withPolicy' => false]);

    expect($captured)->toBe([
        'model' => 'Post',
        '--from-model' => false,
        '--with-policy' => false,
    ]);
});

it('reports failure when the runner returns a non-zero exit code', function (): void {
    $tool = new GenerateResourceTool(
        fn (array $args): array => ['exitCode' => 1, 'output' => 'Model class [App\\Models\\Ghost] does not exist.'],
    );

    $result = $tool(['model' => 'Ghost']);

    expect($result['success'])->toBeFalse()
        ->and($result['exitCode'])->toBe(1)
        ->and($result['output'])->toBe('Model class [App\\Models\\Ghost] does not exist.')
        ->and($result['model'])->toBe('Ghost');
});

<?php

declare(strict_types=1);

use Arqel\Mcp\Tools\RunTestTool;

it('exposes the canonical tool schema', function (): void {
    $tool = new RunTestTool;

    $schema = $tool->schema();

    expect($schema['name'])->toBe('run_test')
        ->and($schema['description'])->toBe('Run Pest or PHPUnit tests with optional filter')
        ->and($schema['inputSchema']['type'])->toBe('object')
        ->and($schema['inputSchema'])->not->toHaveKey('required')
        ->and($schema['inputSchema']['properties'])->toHaveKeys(['filter', 'path', 'coverage'])
        ->and($schema['inputSchema']['properties']['filter']['type'])->toBe('string')
        ->and($schema['inputSchema']['properties']['path']['type'])->toBe('string')
        ->and($schema['inputSchema']['properties']['coverage']['type'])->toBe('boolean')
        ->and($schema['inputSchema']['properties']['coverage']['default'])->toBeFalse();
});

it('runs the happy path with default cmd and reports success', function (): void {
    $capturedCmd = [];
    $capturedTimeout = 0;
    $runner = function (array $cmd, int $timeout) use (&$capturedCmd, &$capturedTimeout): array {
        $capturedCmd = $cmd;
        $capturedTimeout = $timeout;

        return ['exitCode' => 0, 'output' => 'PASS', 'errorOutput' => ''];
    };

    $tool = new RunTestTool($runner);
    $result = $tool([]);

    expect($result)->toBe([
        'exitCode' => 0,
        'output' => 'PASS',
        'errorOutput' => '',
        'success' => true,
        'command' => './vendor/bin/pest',
    ])
        ->and($capturedCmd)->toBe(['./vendor/bin/pest'])
        ->and($capturedTimeout)->toBe(300);
});

it('forwards filter as --filter= argument', function (): void {
    $capturedCmd = [];
    $runner = function (array $cmd, int $timeout) use (&$capturedCmd): array {
        $capturedCmd = $cmd;

        return ['exitCode' => 0, 'output' => '', 'errorOutput' => ''];
    };

    $tool = new RunTestTool($runner);
    $tool(['filter' => 'CsvExporter']);

    expect($capturedCmd)->toContain('--filter=CsvExporter');
});

it('forwards path as a positional argument', function (): void {
    $capturedCmd = [];
    $runner = function (array $cmd, int $timeout) use (&$capturedCmd): array {
        $capturedCmd = $cmd;

        return ['exitCode' => 0, 'output' => '', 'errorOutput' => ''];
    };

    $tool = new RunTestTool($runner);
    $tool(['path' => 'tests/Unit']);

    expect($capturedCmd)->toContain('tests/Unit');
});

it('forwards --coverage when coverage flag is true', function (): void {
    $capturedCmd = [];
    $runner = function (array $cmd, int $timeout) use (&$capturedCmd): array {
        $capturedCmd = $cmd;

        return ['exitCode' => 0, 'output' => '', 'errorOutput' => ''];
    };

    $tool = new RunTestTool($runner);
    $tool(['coverage' => true]);

    expect($capturedCmd)->toContain('--coverage');
});

it('rejects path containing parent directory traversal', function (): void {
    $tool = new RunTestTool(fn (array $cmd, int $timeout): array => ['exitCode' => 0, 'output' => '', 'errorOutput' => '']);

    $tool(['path' => '../etc']);
})->throws(InvalidArgumentException::class, "path must be relative and may not contain '..'");

it('rejects absolute paths', function (): void {
    $tool = new RunTestTool(fn (array $cmd, int $timeout): array => ['exitCode' => 0, 'output' => '', 'errorOutput' => '']);

    $tool(['path' => '/etc']);
})->throws(InvalidArgumentException::class, "path must be relative and may not contain '..'");

it('clamps timeout above the maximum to 600 seconds', function (): void {
    $capturedTimeout = 0;
    $runner = function (array $cmd, int $timeout) use (&$capturedTimeout): array {
        $capturedTimeout = $timeout;

        return ['exitCode' => 0, 'output' => '', 'errorOutput' => ''];
    };

    $tool = new RunTestTool($runner);
    $tool(['timeout' => 999]);

    expect($capturedTimeout)->toBe(600);
});

it('clamps timeout below the minimum to 1 second', function (): void {
    $capturedTimeout = 0;
    $runner = function (array $cmd, int $timeout) use (&$capturedTimeout): array {
        $capturedTimeout = $timeout;

        return ['exitCode' => 0, 'output' => '', 'errorOutput' => ''];
    };

    $tool = new RunTestTool($runner);
    $tool(['timeout' => 0]);

    expect($capturedTimeout)->toBe(1);
});

it('reports failure when the runner returns a non-zero exit code', function (): void {
    $tool = new RunTestTool(
        fn (array $cmd, int $timeout): array => [
            'exitCode' => 1,
            'output' => 'FAIL: 1 test failing',
            'errorOutput' => 'stderr noise',
        ],
    );

    $result = $tool([]);

    expect($result['success'])->toBeFalse()
        ->and($result['exitCode'])->toBe(1)
        ->and($result['output'])->toBe('FAIL: 1 test failing')
        ->and($result['errorOutput'])->toBe('stderr noise');
});

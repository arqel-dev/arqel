<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Feature;

use Illuminate\Support\Facades\Artisan;

it('registers the arqel:doctor command', function (): void {
    $commands = array_keys(Artisan::all());

    expect($commands)->toContain('arqel:doctor');
});

it('exits 0 in a clean Testbench environment', function (): void {
    $exitCode = Artisan::call('arqel:doctor');

    expect($exitCode)->toBe(0);
});

it('renders human-readable status indicators by default', function (): void {
    Artisan::call('arqel:doctor');
    $output = Artisan::output();

    expect($output)
        ->toContain('Arqel Doctor')
        ->and($output)
        ->toContain('Summary:');

    // At least one of the textual status tags must be present.
    $hasStatusTag = str_contains($output, '[ok]')
        || str_contains($output, '[warn]')
        || str_contains($output, '[fail]');

    expect($hasStatusTag)->toBeTrue();
});

it('emits a parseable JSON document with --json', function (): void {
    Artisan::call('arqel:doctor', ['--json' => true]);
    $output = trim(Artisan::output());

    // The JSON line is the last non-empty line of the output buffer.
    $lines = array_values(array_filter(
        preg_split("/\r?\n/", $output) ?: [],
        static fn (string $line): bool => trim($line) !== '',
    ));

    $jsonLine = end($lines);
    expect($jsonLine)->toBeString();

    $decoded = json_decode((string) $jsonLine, true);

    expect($decoded)
        ->toBeArray()
        ->toHaveKeys(['checks', 'summary']);

    expect($decoded['checks'])->toBeArray()->not->toBeEmpty();
    expect($decoded['summary'])->toHaveKeys(['ok', 'warn', 'fail']);

    foreach ($decoded['checks'] as $check) {
        expect($check)->toHaveKeys(['name', 'status', 'message']);
        expect($check['status'])->toBeIn(['ok', 'warn', 'fail']);
    }
});

it('forces non-zero exit in --strict when warnings are present', function (): void {
    // The Testbench environment uses `array` cache + session drivers
    // by default, which the Doctor flags as warnings.
    config()->set('cache.default', 'array');
    config()->set('session.driver', 'array');

    $exitCode = Artisan::call('arqel:doctor', ['--strict' => true]);

    expect($exitCode)->toBe(1);
});

it('reports the ArqelServiceProvider as loaded', function (): void {
    Artisan::call('arqel:doctor', ['--json' => true]);
    $output = trim(Artisan::output());

    $lines = array_values(array_filter(
        preg_split("/\r?\n/", $output) ?: [],
        static fn (string $line): bool => trim($line) !== '',
    ));
    $jsonLine = end($lines);

    /** @var array{checks: list<array{name: string, status: string}>} $decoded */
    $decoded = json_decode((string) $jsonLine, true);

    $providerCheck = collect($decoded['checks'])
        ->firstWhere('name', 'arqel.provider');

    expect($providerCheck)->not->toBeNull();
    expect($providerCheck['status'])->toBe('ok');
});

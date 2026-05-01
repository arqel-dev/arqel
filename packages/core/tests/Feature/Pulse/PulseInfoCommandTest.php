<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Feature\Pulse;

use Illuminate\Support\Facades\Artisan;

it('registers the arqel:pulse:info command', function (): void {
    $commands = array_keys(Artisan::all());

    expect($commands)->toContain('arqel:pulse:info');
});

it('prints a "not detected" message when Pulse is absent', function (): void {
    if (class_exists(\Laravel\Pulse\Pulse::class)) {
        $this->markTestSkipped('Laravel Pulse is unexpectedly present.');
    }

    $exit = Artisan::call('arqel:pulse:info');
    $output = Artisan::output();

    expect($exit)->toBe(0);
    expect($output)->toContain('Laravel Pulse not detected');
});

it('emits a parseable JSON document with --json', function (): void {
    Artisan::call('arqel:pulse:info', ['--json' => true]);
    $output = trim(Artisan::output());

    $lines = array_values(array_filter(
        preg_split("/\r?\n/", $output) ?: [],
        static fn (string $line): bool => trim($line) !== '',
    ));

    $jsonLine = end($lines);
    expect($jsonLine)->toBeString();

    $decoded = json_decode((string) $jsonLine, true);

    expect($decoded)
        ->toBeArray()
        ->toHaveKeys(['available', 'pulse_version', 'cards', 'recorders', 'sample']);

    expect($decoded['cards'])->toBeArray()->toHaveCount(5);
    expect($decoded['recorders'])->toBeArray()->toHaveCount(2);
});

it('returns success exit code in both modes', function (): void {
    expect(Artisan::call('arqel:pulse:info'))->toBe(0);
    expect(Artisan::call('arqel:pulse:info', ['--json' => true]))->toBe(0);
});

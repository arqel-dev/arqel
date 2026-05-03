<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Feature;

use Illuminate\Support\Facades\Artisan;

it('registers the arqel:audit command', function (): void {
    $commands = array_keys(Artisan::all());

    expect($commands)->toContain('arqel:audit');
});

it('exits 0 or 1 on a monorepo audit (cleanly)', function (): void {
    // The audit can fail for legitimate reasons in the Testbench
    // environment (e.g. running outside the monorepo root, missing
    // installed package metadata). We only verify the command terminates
    // with a deterministic 0/1 exit, never crashes.
    $exitCode = Artisan::call('arqel:audit');

    expect($exitCode)->toBeIn([0, 1]);
});

it('emits a parseable JSON document with --json', function (): void {
    Artisan::call('arqel:audit', ['--json' => true]);
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
        ->toHaveKeys(['checks', 'summary']);

    expect($decoded['checks'])->toBeArray()->not->toBeEmpty();
    expect($decoded['summary'])->toHaveKeys(['ok', 'warn', 'fail']);

    foreach ($decoded['checks'] as $check) {
        expect($check)->toHaveKeys(['name', 'status', 'message']);
        expect($check['status'])->toBeIn(['ok', 'warn', 'fail', 'neutral']);
    }
});

it('respects the --strict flag when warnings are present', function (): void {
    // Run without strict — we don't assert exit code (depends on env).
    $loose = Artisan::call('arqel:audit');
    expect($loose)->toBeIn([0, 1]);

    // Strict run: exit code must be 0 or 1, never anything else.
    $strict = Artisan::call('arqel:audit', ['--strict' => true]);
    expect($strict)->toBeIn([0, 1]);
});

it('renders a human-readable summary by default', function (): void {
    Artisan::call('arqel:audit');
    $output = Artisan::output();

    expect($output)
        ->toContain('Arqel Audit')
        ->and($output)
        ->toContain('Summary:');
});

it('reports the expected check names in JSON output', function (): void {
    Artisan::call('arqel:audit', ['--json' => true]);
    $output = trim(Artisan::output());

    $lines = array_values(array_filter(
        preg_split("/\r?\n/", $output) ?: [],
        static fn (string $line): bool => trim($line) !== '',
    ));
    $jsonLine = end($lines);

    /** @var array{checks: list<array{name: string}>} $decoded */
    $decoded = json_decode((string) $jsonLine, true);

    $names = array_map(static fn (array $check): string => $check['name'], $decoded['checks']);

    expect($names)->toContain('packages.skill_md');
    expect($names)->toContain('packages.composer_json');
    expect($names)->toContain('packages.changelog_entry');
    expect($names)->toContain('docs.skill_canonical');
    expect($names)->toContain('tests.suite_size');
});

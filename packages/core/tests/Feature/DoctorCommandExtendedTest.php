<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Feature;

use Illuminate\Support\Facades\Artisan;

/**
 * @return array<string, array{name: string, status: string, message: string}>
 */
function arqel_doctor_checks_by_name(): array
{
    Artisan::call('arqel:doctor', ['--json' => true]);
    $output = trim(Artisan::output());

    $lines = array_values(array_filter(
        preg_split("/\r?\n/", $output) ?: [],
        static fn (string $line): bool => trim($line) !== '',
    ));
    $jsonLine = end($lines);

    /** @var array{checks: list<array{name: string, status: string, message: string}>} $decoded */
    $decoded = json_decode((string) $jsonLine, true);

    $byName = [];
    foreach ($decoded['checks'] as $check) {
        $byName[$check['name']] = $check;
    }

    return $byName;
}

it('warns when broadcasting driver is log in production', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'production');
    config()->set('broadcasting.default', 'log');

    $checks = arqel_doctor_checks_by_name();

    expect($checks)->toHaveKey('broadcasting.driver');
    expect($checks['broadcasting.driver']['status'])->toBe('warn');
    expect($checks['broadcasting.driver']['message'])->toContain('production');
});

it('marks broadcasting driver ok outside of production', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'testing');
    config()->set('broadcasting.default', 'log');

    $checks = arqel_doctor_checks_by_name();

    expect($checks['broadcasting.driver']['status'])->toBe('ok');
});

it('warns when queue driver is sync in production', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'production');
    config()->set('queue.default', 'sync');

    $checks = arqel_doctor_checks_by_name();

    expect($checks)->toHaveKey('queue.driver');
    expect($checks['queue.driver']['status'])->toBe('warn');
    expect($checks['queue.driver']['message'])->toContain('sync');
});

it('reports ai.providers.configured as neutral when arqel-dev/ai is absent', function (): void {
    $checks = arqel_doctor_checks_by_name();

    expect($checks)->toHaveKey('ai.providers.configured');
    // Testbench environment does not install arqel-dev/ai by default.
    expect($checks['ai.providers.configured']['status'])->toBe('neutral');
    expect($checks['ai.providers.configured']['message'])->toContain('not installed');
});

it('reports marketplace.migrations as neutral when arqel-dev/marketplace is absent', function (): void {
    $checks = arqel_doctor_checks_by_name();

    expect($checks)->toHaveKey('marketplace.migrations');
    expect($checks['marketplace.migrations']['status'])->toBe('neutral');
    expect($checks['marketplace.migrations']['message'])->toContain('not installed');
});

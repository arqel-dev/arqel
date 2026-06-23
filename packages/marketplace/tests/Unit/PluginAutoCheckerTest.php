<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Services\PluginAutoChecker;

/**
 * @param list<array{name: string, status: string, message: string}> $checks
 *
 * @return array{name: string, status: string, message: string}
 */
function findCheck(array $checks, string $name): array
{
    foreach ($checks as $check) {
        if ($check['name'] === $name) {
            return $check;
        }
    }

    throw new RuntimeException("Check [{$name}] not found");
}

function makeCheckerPlugin(array $overrides = []): Plugin
{
    /** @var Plugin $p */
    $p = Plugin::query()->create(array_merge([
        'slug' => 'check-target-'.uniqid(),
        'name' => 'Check Target '.uniqid(),
        'description' => str_repeat('a description that is sufficiently long for the check ', 2),
        'type' => 'widget',
        'github_url' => 'https://github.com/acme/check-target',
        'composer_package' => 'acme/check-target',
        'status' => 'pending',
    ], $overrides));

    return $p;
}

it('flags invalid composer package format as fail', function (): void {
    $plugin = makeCheckerPlugin(['composer_package' => 'INVALID']);
    $report = (new PluginAutoChecker)->check($plugin);

    $check = findCheck($report['checks'], 'composer_package_format');
    expect($check['status'])->toBe('fail');
    expect($report['passed'])->toBeFalse();
});

it('flags non-github url as fail', function (): void {
    $plugin = makeCheckerPlugin(['github_url' => 'https://gitlab.com/x/y']);
    $report = (new PluginAutoChecker)->check($plugin);

    $check = findCheck($report['checks'], 'github_url_format');
    expect($check['status'])->toBe('fail');
});

it('warns on short description', function (): void {
    $plugin = makeCheckerPlugin(['description' => 'short desc']);
    $report = (new PluginAutoChecker)->check($plugin);

    $check = findCheck($report['checks'], 'description_length');
    expect($check['status'])->toBe('warn');
});

it('warns when no screenshots provided', function (): void {
    $plugin = makeCheckerPlugin();
    $report = (new PluginAutoChecker)->check($plugin);

    $check = findCheck($report['checks'], 'screenshots_count');
    expect($check['status'])->toBe('warn');
});

it('localizes the screenshot count with a correct singular form (no "(s)" hack)', function (): void {
    Illuminate\Support\Facades\App::setLocale('en');

    $plugin = makeCheckerPlugin([
        'screenshots' => ['https://example.com/a.png'],
    ]);
    $report = (new PluginAutoChecker)->check($plugin);

    $check = findCheck($report['checks'], 'screenshots_count');
    expect($check['status'])->toBe('ok');
    expect($check['message'])->toBe('Provided 1 screenshot.');
    expect($check['message'])->not->toContain('(s)');
});

it('localizes the screenshot count with a correct plural form', function (): void {
    Illuminate\Support\Facades\App::setLocale('en');

    $plugin = makeCheckerPlugin([
        'screenshots' => ['https://example.com/a.png', 'https://example.com/b.png'],
    ]);
    $report = (new PluginAutoChecker)->check($plugin);

    $check = findCheck($report['checks'], 'screenshots_count');
    expect($check['message'])->toBe('Provided 2 screenshots.');
});

it('translates the screenshot count to pt_BR', function (): void {
    Illuminate\Support\Facades\App::setLocale('pt_BR');

    $singular = (new PluginAutoChecker)->check(makeCheckerPlugin([
        'screenshots' => ['https://example.com/a.png'],
    ]));
    expect(findCheck($singular['checks'], 'screenshots_count')['message'])
        ->toBe('1 captura de tela fornecida.');

    $plural = (new PluginAutoChecker)->check(makeCheckerPlugin([
        'screenshots' => ['https://example.com/a.png', 'https://example.com/b.png'],
    ]));
    expect(findCheck($plural['checks'], 'screenshots_count')['message'])
        ->toBe('2 capturas de tela fornecidas.');
});

it('returns shape with checks list and passed flag for happy plugin', function (): void {
    $plugin = makeCheckerPlugin([
        'screenshots' => ['https://example.com/a.png'],
    ]);
    $report = (new PluginAutoChecker)->check($plugin);

    expect($report)->toHaveKeys(['checks', 'passed']);
    expect($report['passed'])->toBeTrue();
    expect($report['checks'])->toBeArray();
    expect(count($report['checks']))->toBe(5);

    foreach ($report['checks'] as $check) {
        expect($check)->toHaveKeys(['name', 'status', 'message']);
        expect($check['status'])->not->toBe('fail');
    }
});

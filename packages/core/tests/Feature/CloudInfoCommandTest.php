<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Feature;

use Illuminate\Support\Facades\Artisan;

it('registers the arqel:cloud:info command', function (): void {
    $commands = array_keys(Artisan::all());

    expect($commands)->toContain('arqel:cloud:info');
});

it('prints the detected platform and effective drivers', function (): void {
    Artisan::call('arqel:cloud:info');
    $output = Artisan::output();

    expect($output)
        ->toContain('Arqel Cloud Info')
        ->toContain('Platform:')
        ->toContain('Auto-configure:')
        ->toContain('Effective drivers')
        ->toContain('filesystems.default');
});

it('emits a parseable JSON document with --json', function (): void {
    Artisan::call('arqel:cloud:info', ['--json' => true]);
    $output = trim(Artisan::output());

    $decoded = json_decode($output, true);

    expect($decoded)->toBeArray();
    expect($decoded)->toHaveKeys(['platform', 'detected', 'auto_configure', 'drivers']);
    expect($decoded['drivers'])->toHaveKey('cache.default');
});

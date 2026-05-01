<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Feature;

use Arqel\Core\Cloud\CloudDetector;
use Illuminate\Support\Facades\Artisan;

it('reports cloud.detected as ok when running on a fake cloud', function (): void {
    app()->instance(CloudDetector::class, new class extends CloudDetector
    {
        public function isLaravelCloud(): bool
        {
            return true;
        }

        public function description(): string
        {
            return 'laravel-cloud';
        }
    });

    Artisan::call('arqel:doctor', ['--json' => true]);
    $output = trim(Artisan::output());

    /** @var array{checks: list<array{name: string, status: string}>} $decoded */
    $decoded = json_decode($output, true);
    $cloudCheck = collect($decoded['checks'])->firstWhere('name', 'cloud.detected');

    expect($cloudCheck)->not->toBeNull();
    expect($cloudCheck['status'])->toBe('ok');
});

it('reports cloud.detected as neutral on a generic host', function (): void {
    app()->instance(CloudDetector::class, new class extends CloudDetector
    {
        public function isLaravelCloud(): bool
        {
            return false;
        }

        public function description(): string
        {
            return 'unknown';
        }
    });

    Artisan::call('arqel:doctor', ['--json' => true]);
    $output = trim(Artisan::output());

    /** @var array{checks: list<array{name: string, status: string}>} $decoded */
    $decoded = json_decode($output, true);
    $cloudCheck = collect($decoded['checks'])->firstWhere('name', 'cloud.detected');

    expect($cloudCheck)->not->toBeNull();
    expect($cloudCheck['status'])->toBe('neutral');
});

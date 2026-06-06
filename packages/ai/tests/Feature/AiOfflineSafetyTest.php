<?php

declare(strict_types=1);

use Arqel\Ai\AiCache;
use Arqel\Ai\AiCompletionResult;
use Arqel\Ai\AiManager;
use Arqel\Ai\AiServiceProvider;
use Arqel\Ai\CostTracker;
use Arqel\Ai\Tests\Fixtures\FakeProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelPackageTools\Package;

/**
 * Regression coverage for issue #49.
 *
 * The package must not hard-crash on the first AI call when the
 * `ai_usage` table has not been migrated (offline / fresh-install
 * scenario), and `cost_tracking.enabled = false` must fully disable
 * the CostTracker DB access — mirroring how `caching.enabled` gates
 * `AiCache`.
 *
 * RefreshDatabase wraps each test in a transaction, so the explicit
 * `Schema::dropIfExists('ai_usage')` used to simulate the un-migrated
 * state is rolled back and never leaks into sibling tests.
 */
uses(RefreshDatabase::class);

// (A) offline-safe: missing ai_usage table must not crash complete().
it('does not crash on complete() when the ai_usage table is missing', function (): void {
    Schema::dropIfExists('ai_usage');
    expect(Schema::hasTable('ai_usage'))->toBeFalse();

    config()->set('arqel-ai.default_provider', 'fake');
    // Positive limit so assertWithinLimit() would query the table pre-fix.
    config()->set('arqel-ai.cost_tracking.enabled', true);
    config()->set('arqel-ai.cost_tracking.daily_limit_usd', 10.0);

    $manager = new AiManager(['fake' => new FakeProvider], new CostTracker, new AiCache);

    $result = $manager->complete('offline prompt');

    expect($result->text)->toBe('echo:offline prompt');
});

it('getCostSince() returns 0 when the ai_usage table is missing', function (): void {
    Schema::dropIfExists('ai_usage');

    config()->set('arqel-ai.cost_tracking.enabled', true);

    expect((new CostTracker)->getCostSince())->toBe(0.0)
        ->and((new CostTracker)->getCostForUserSince(7))->toBe(0.0);
});

// (B) dead flag honored: enabled=false short-circuits all DB access.
it('does not touch the database when cost_tracking.enabled is false', function (): void {
    Schema::dropIfExists('ai_usage');
    config()->set('arqel-ai.cost_tracking.enabled', false);
    config()->set('arqel-ai.cost_tracking.daily_limit_usd', 10.0);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $tracker = new CostTracker;
    $tracker->assertWithinLimit(7);
    $tracker->record(7, new AiCompletionResult('x', 1, 1, 0.5, 'm', []), 'fake');

    expect(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();
});

// (C) migration publishable: the registered migration source path exists.
it('registers a migration that exists on disk and can be published', function (): void {
    $provider = new AiServiceProvider(app());
    $package = new Package;
    $provider->configurePackage($package);

    expect($package->migrationFileNames)->toContain('2026_05_01_000000_create_ai_usage_table');

    foreach ($package->migrationFileNames as $name) {
        expect($name)->toBeString();

        if (! is_string($name)) {
            continue;
        }

        $base = __DIR__.'/../../database/migrations/'.$name;
        $found = file_exists($base.'.php') || file_exists($base.'.php.stub');

        expect($found)->toBeTrue("migration source for '{$name}' not found on disk");
    }

    $exitCode = Illuminate\Support\Facades\Artisan::call('vendor:publish', [
        '--tag' => 'arqel-ai-migrations',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0);
});

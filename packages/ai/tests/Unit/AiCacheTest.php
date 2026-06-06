<?php

declare(strict_types=1);

use Arqel\Ai\AiCache;
use Arqel\Ai\AiCompletionResult;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

it('stores and retrieves a completion result', function (): void {
    $cache = new AiCache;
    $result = new AiCompletionResult('hello', 5, 7, 0.0001, 'test-model', []);

    expect($cache->has('p', []))->toBeFalse();

    $cache->put('p', [], $result);

    expect($cache->has('p', []))->toBeTrue()
        ->and($cache->get('p', [])?->text)->toBe('hello');
});

it('treats different prompts as distinct cache entries', function (): void {
    $cache = new AiCache;
    $result = new AiCompletionResult('a', 1, 1, 0.0, 'm', []);

    $cache->put('alpha', [], $result);

    expect($cache->has('beta', []))->toBeFalse();
});

it('is a no-op when caching is disabled', function (): void {
    config()->set('arqel-ai.caching.enabled', false);

    $cache = new AiCache;
    $cache->put('p', [], new AiCompletionResult('x', 0, 0, 0.0, 'm', []));

    expect($cache->has('p', []))->toBeFalse()
        ->and($cache->get('p', []))->toBeNull();
});

it('stores a plain array snapshot, not the result object', function (): void {
    $cache = new AiCache;
    $result = new AiCompletionResult('snap', 3, 4, 0.002, 'snap-model', ['k' => 'v']);

    $cache->put('p', [], $result);

    // Read the raw cached value directly via the underlying store. The value
    // must be a plain array so that drivers which disallow object
    // unserialization (e.g. the default Laravel 13 database driver with
    // cache.serializable_classes=false) can rehydrate it losslessly.
    $raw = Cache::store()->get($cache->key('p', []));

    expect($raw)->toBeArray()
        ->and($raw)->not->toBeInstanceOf(AiCompletionResult::class);
});

it('round-trips losslessly across put and get', function (): void {
    $cache = new AiCache;
    $result = new AiCompletionResult('full', 11, 22, 0.0033, 'full-model', ['stop' => 'end']);

    $cache->put('p', [], $result);

    $restored = $cache->get('p', []);

    expect($restored)->toBeInstanceOf(AiCompletionResult::class)
        ->and($restored?->text)->toBe('full')
        ->and($restored?->inputTokens)->toBe(11)
        ->and($restored?->outputTokens)->toBe(22)
        ->and($restored?->estimatedCost)->toBe(0.0033)
        ->and($restored?->model)->toBe('full-model')
        ->and($restored?->raw)->toBe(['stop' => 'end'])
        ->and($restored?->totalTokens())->toBe(33);
});

it('survives a cache driver that disallows object unserialization', function (): void {
    // Reproduce the issue #82 failure mode: Laravel's database cache driver
    // with the stock cache.serializable_classes=false runs
    // unserialize($value, ['allowed_classes' => false]), which turns any
    // stored object into a __PHP_Incomplete_Class. Storing the result object
    // (the old behaviour) therefore made get() miss every time, silently
    // re-invoking the provider and double-counting cost.
    //
    // The store is wired onto a dedicated, isolated sqlite connection so this
    // test never touches the default connection (which other suites use for
    // the ai_usage table under RefreshDatabase).
    config()->set('database.connections.aicache_db', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    config()->set('cache.serializable_classes', false);
    config()->set('cache.default', 'aicache_database');
    config()->set('cache.stores.aicache_database', [
        'driver' => 'database',
        'connection' => 'aicache_db',
        'table' => 'cache',
        'lock_connection' => null,
    ]);

    Schema::connection('aicache_db')->create('cache', function (Blueprint $table): void {
        $table->string('key')->primary();
        $table->mediumText('value');
        $table->integer('expiration');
    });

    Cache::purge('aicache_database');

    $cache = new AiCache;
    $result = new AiCompletionResult('db', 1, 2, 0.001, 'db-model', ['ok' => true]);

    $cache->put('p', [], $result);

    $restored = $cache->get('p', []);

    expect($restored)->toBeInstanceOf(AiCompletionResult::class)
        ->and($restored?->text)->toBe('db')
        ->and($restored?->raw)->toBe(['ok' => true]);
});

it('treats a non-array cached value as a miss', function (): void {
    $cache = new AiCache;

    // Legacy/corrupt entry: a bare string under the cache key. get() must
    // degrade gracefully to a miss rather than fataling.
    Cache::store()->put($cache->key('p', []), 'not-a-snapshot', 60);

    expect($cache->get('p', []))->toBeNull();
});

<?php

declare(strict_types=1);

use Arqel\Ai\AiCache;
use Arqel\Ai\AiCompletionResult;

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

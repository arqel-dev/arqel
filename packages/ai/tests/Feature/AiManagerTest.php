<?php

declare(strict_types=1);

use Arqel\Ai\AiCache;
use Arqel\Ai\AiCompletionResult;
use Arqel\Ai\AiManager;
use Arqel\Ai\CostTracker;
use Arqel\Ai\Events\AiCompletionGenerated;
use Arqel\Ai\Exceptions\DailyLimitExceeded;
use Arqel\Ai\Models\AiUsage;
use Arqel\Ai\Tests\Fixtures\FakeProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('resolves the default provider from config', function (): void {
    config()->set('arqel-ai.default_provider', 'fake');
    $fake = new FakeProvider('fake');

    $manager = new AiManager(['fake' => $fake]);

    expect($manager->provider())->toBe($fake);
});

it('throws when an unknown provider is requested', function (): void {
    $manager = new AiManager(['fake' => new FakeProvider]);

    $manager->provider('unknown');
})->throws(InvalidArgumentException::class);

it('forwards complete() to the provider and dispatches the event', function (): void {
    Event::fake([AiCompletionGenerated::class]);
    config()->set('arqel-ai.default_provider', 'fake');

    $fake = new FakeProvider('fake');
    $manager = new AiManager(['fake' => $fake], new CostTracker, new AiCache);

    $result = $manager->complete('hello');

    expect($result->text)->toBe('echo:hello')
        ->and($fake->completeCalls)->toBe(1);

    Event::assertDispatched(
        AiCompletionGenerated::class,
        fn (AiCompletionGenerated $event): bool => $event->providerName === 'fake'
            && $event->result->text === 'echo:hello',
    );
});

it('persists the call via CostTracker', function (): void {
    config()->set('arqel-ai.default_provider', 'fake');
    Auth::shouldReceive('id')->andReturn(11);

    $manager = new AiManager(['fake' => new FakeProvider], new CostTracker, new AiCache);

    $manager->complete('persist me');

    expect(AiUsage::query()->count())->toBe(1)
        ->and(AiUsage::query()->first()?->user_id)->toBe(11);
});

it('short-circuits via cache when prompt has been seen', function (): void {
    config()->set('arqel-ai.default_provider', 'fake');

    $fake = new FakeProvider('fake');
    $cache = new AiCache;
    $manager = new AiManager(['fake' => $fake], new CostTracker, $cache);

    $manager->complete('cached prompt');
    $manager->complete('cached prompt');

    expect($fake->completeCalls)->toBe(1);
});

it('blocks complete() when daily limit is exceeded', function (): void {
    config()->set('arqel-ai.default_provider', 'fake');
    config()->set('arqel-ai.cost_tracking.daily_limit_usd', 0.0001);

    AiUsage::query()->create([
        'user_id' => null, 'provider' => 'fake', 'model' => 'fake-model',
        'input_tokens' => 0, 'output_tokens' => 0, 'cost_usd' => 1.0,
    ]);

    $manager = new AiManager(['fake' => new FakeProvider], new CostTracker, new AiCache);

    $manager->complete('blocked');
})->throws(DailyLimitExceeded::class);

it('forwards chat() and dispatches the event', function (): void {
    Event::fake([AiCompletionGenerated::class]);
    config()->set('arqel-ai.default_provider', 'fake');

    $fake = new FakeProvider('fake');
    $manager = new AiManager(['fake' => $fake], new CostTracker, new AiCache);

    $manager->chat([['role' => 'user', 'content' => 'hi']]);

    expect($fake->chatCalls)->toBe(1);
    Event::assertDispatched(AiCompletionGenerated::class);
});

it('bypasses cache for embed() but still asserts the limit', function (): void {
    config()->set('arqel-ai.default_provider', 'fake');

    $manager = new AiManager(['fake' => new FakeProvider], new CostTracker, new AiCache);

    $vector = $manager->embed('vector me');

    expect($vector)->toBe([0.1, 0.2, 0.3]);
});

it('honours options.provider override', function (): void {
    config()->set('arqel-ai.default_provider', 'a');

    $a = new FakeProvider('a');
    $b = new FakeProvider('b');
    $manager = new AiManager(['a' => $a, 'b' => $b], new CostTracker, new AiCache);

    $manager->complete('p', ['provider' => 'b']);

    expect($a->completeCalls)->toBe(0)
        ->and($b->completeCalls)->toBe(1);
});

it('skips cost tracker and cache when not provided', function (): void {
    Event::fake([AiCompletionGenerated::class]);
    config()->set('arqel-ai.default_provider', 'fake');

    $manager = new AiManager(['fake' => new FakeProvider]);

    $result = $manager->complete('plain');

    expect($result->text)->toBe('echo:plain')
        ->and(AiUsage::query()->count())->toBe(0);
    Event::assertDispatched(AiCompletionGenerated::class);
});

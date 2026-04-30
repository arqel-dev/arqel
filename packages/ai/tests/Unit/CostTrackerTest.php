<?php

declare(strict_types=1);

use Arqel\Ai\AiCompletionResult;
use Arqel\Ai\CostTracker;
use Arqel\Ai\Exceptions\DailyLimitExceeded;
use Arqel\Ai\Exceptions\UserLimitExceeded;
use Arqel\Ai\Models\AiUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('is a no-op when no usage rows exist and limits are configured', function (): void {
    config()->set('arqel-ai.cost_tracking.daily_limit_usd', 10.0);
    config()->set('arqel-ai.cost_tracking.per_user_limit_usd', 5.0);

    expect(fn () => (new CostTracker)->assertWithinLimit(42))->not->toThrow(DailyLimitExceeded::class);
});

it('throws DailyLimitExceeded when total spend hits the cap', function (): void {
    config()->set('arqel-ai.cost_tracking.daily_limit_usd', 1.0);

    AiUsage::query()->create([
        'user_id' => null,
        'provider' => 'claude',
        'model' => 'claude-opus-4-7',
        'input_tokens' => 1000,
        'output_tokens' => 1000,
        'cost_usd' => 1.5,
    ]);

    (new CostTracker)->assertWithinLimit(null);
})->throws(DailyLimitExceeded::class);

it('throws UserLimitExceeded when a user is over budget', function (): void {
    config()->set('arqel-ai.cost_tracking.daily_limit_usd', 1000.0);
    config()->set('arqel-ai.cost_tracking.per_user_limit_usd', 0.5);

    AiUsage::query()->create([
        'user_id' => 7,
        'provider' => 'claude',
        'model' => 'claude-opus-4-7',
        'input_tokens' => 100,
        'output_tokens' => 100,
        'cost_usd' => 0.6,
    ]);

    (new CostTracker)->assertWithinLimit(7);
})->throws(UserLimitExceeded::class);

it('treats null limits as unlimited', function (): void {
    config()->set('arqel-ai.cost_tracking.daily_limit_usd', null);
    config()->set('arqel-ai.cost_tracking.per_user_limit_usd', null);

    AiUsage::query()->create([
        'user_id' => 7,
        'provider' => 'claude',
        'model' => 'm',
        'input_tokens' => 0,
        'output_tokens' => 0,
        'cost_usd' => 999.99,
    ]);

    expect(fn () => (new CostTracker)->assertWithinLimit(7))->not->toThrow(DailyLimitExceeded::class);
});

it('records a row in ai_usage with the result snapshot', function (): void {
    $result = new AiCompletionResult('hello', 12, 34, 0.0042, 'claude-opus-4-7', []);

    (new CostTracker)->record(99, $result, 'claude');

    $row = AiUsage::query()->firstOrFail();

    expect($row->user_id)->toBe(99)
        ->and($row->provider)->toBe('claude')
        ->and($row->model)->toBe('claude-opus-4-7')
        ->and($row->input_tokens)->toBe(12)
        ->and($row->output_tokens)->toBe(34)
        ->and($row->cost_usd)->toBe(0.0042);
});

it('aggregates per-user spend correctly', function (): void {
    AiUsage::query()->create([
        'user_id' => 1, 'provider' => 'p', 'model' => 'm',
        'input_tokens' => 0, 'output_tokens' => 0, 'cost_usd' => 0.10,
    ]);
    AiUsage::query()->create([
        'user_id' => 1, 'provider' => 'p', 'model' => 'm',
        'input_tokens' => 0, 'output_tokens' => 0, 'cost_usd' => 0.25,
    ]);
    AiUsage::query()->create([
        'user_id' => 2, 'provider' => 'p', 'model' => 'm',
        'input_tokens' => 0, 'output_tokens' => 0, 'cost_usd' => 0.99,
    ]);

    $tracker = new CostTracker;

    expect($tracker->getCostForUserSince(1))->toBe(0.35)
        ->and($tracker->getCostForUserSince(2))->toBe(0.99);
});

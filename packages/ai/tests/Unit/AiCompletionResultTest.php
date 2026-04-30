<?php

declare(strict_types=1);

use Arqel\Ai\AiCompletionResult;

it('exposes the immutable result shape with token accounting', function (): void {
    $result = new AiCompletionResult(
        text: 'Olá!',
        inputTokens: 12,
        outputTokens: 4,
        estimatedCost: 0.000_15,
        model: 'claude-opus-4-7',
        raw: ['stop_reason' => 'end_turn'],
    );

    expect($result->text)->toBe('Olá!')
        ->and($result->inputTokens)->toBe(12)
        ->and($result->outputTokens)->toBe(4)
        ->and($result->estimatedCost)->toBe(0.000_15)
        ->and($result->model)->toBe('claude-opus-4-7')
        ->and($result->raw)->toBe(['stop_reason' => 'end_turn'])
        ->and($result->totalTokens())->toBe(16);
});

it('treats result properties as readonly', function (): void {
    $result = new AiCompletionResult(
        text: 'x',
        inputTokens: 0,
        outputTokens: 0,
        estimatedCost: null,
        model: null,
    );

    /** @phpstan-ignore-next-line — intentional mutation to assert immutability */
    expect(fn () => $result->text = 'mutated')
        ->toThrow(Error::class);
});

it('accepts null cost and model for providers that do not surface them', function (): void {
    $result = new AiCompletionResult(
        text: '',
        inputTokens: 0,
        outputTokens: 0,
        estimatedCost: null,
        model: null,
    );

    expect($result->estimatedCost)->toBeNull()
        ->and($result->model)->toBeNull()
        ->and($result->raw)->toBeNull()
        ->and($result->totalTokens())->toBe(0);
});

<?php

declare(strict_types=1);

namespace App\Ai;

use Arqel\Ai\AiCompletionResult;
use Arqel\Ai\Contracts\AiProvider;

/**
 * Deterministic, offline AI provider for the showcase app.
 *
 * Every method returns a fixed value and performs NO network calls, so
 * the panel boots and exercises the `arqel-dev/ai` wiring (AiManager,
 * CostTracker, AiCache) without an API key or any spend. Estimated cost
 * is always $0.
 */
final class StubProvider implements AiProvider
{
    public function complete(string $prompt, array $options = []): AiCompletionResult
    {
        return $this->result('This is a deterministic stub completion.');
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): AiCompletionResult
    {
        return $this->result('This is a deterministic stub chat reply.');
    }

    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        return [0.0, 0.1, 0.2, 0.3];
    }

    /**
     * @param callable(string): void $onChunk
     * @param array<string, mixed> $options
     */
    public function stream(string $prompt, callable $onChunk, array $options = []): void
    {
        $onChunk('stub');
    }

    public function name(): string
    {
        return 'stub';
    }

    public function supportsEmbeddings(): bool
    {
        return true;
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function supportsVision(): bool
    {
        return false;
    }

    private function result(string $text): AiCompletionResult
    {
        return new AiCompletionResult(
            text: $text,
            inputTokens: 0,
            outputTokens: 0,
            estimatedCost: 0.0,
            model: 'stub',
            raw: null,
        );
    }
}

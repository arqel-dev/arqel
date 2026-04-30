<?php

declare(strict_types=1);

namespace Arqel\Ai;

/**
 * Immutable provider response — `text` plus accounting fields used by the
 * cost-tracking layer (AI-006). `raw` carries the unparsed payload for
 * downstream consumers that need the provider's full response (e.g. tool
 * calls, streaming chunks, finish_reason).
 */
final readonly class AiCompletionResult
{
    /**
     * @param array<string, mixed>|null $raw
     */
    public function __construct(
        public readonly string $text,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly ?float $estimatedCost,
        public readonly ?string $model,
        public readonly ?array $raw = null,
    ) {}

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}

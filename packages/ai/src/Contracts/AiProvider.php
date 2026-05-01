<?php

declare(strict_types=1);

namespace Arqel\Ai\Contracts;

use Arqel\Ai\AiCompletionResult;

/**
 * Provider abstraction. Concretes (Claude, OpenAI, Ollama) ship in
 * AI-003..AI-005 and translate this contract to provider-specific HTTP /
 * SDK calls. Embedding-only or streaming-only providers SHOULD throw
 * `Arqel\Ai\Exceptions\AiException` from unsupported methods and report
 * accurately via `supportsEmbeddings()` / `supportsStreaming()`.
 */
interface AiProvider
{
    /**
     * @param array<string, mixed> $options
     */
    public function complete(string $prompt, array $options = []): AiCompletionResult;

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): AiCompletionResult;

    /**
     * @return array<int, float>
     */
    public function embed(string $text): array;

    /**
     * @param callable(string): void $onChunk
     * @param array<string, mixed> $options
     */
    public function stream(string $prompt, callable $onChunk, array $options = []): void;

    public function name(): string;

    public function supportsEmbeddings(): bool;

    public function supportsStreaming(): bool;

    public function supportsVision(): bool;
}

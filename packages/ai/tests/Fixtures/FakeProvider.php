<?php

declare(strict_types=1);

namespace Arqel\Ai\Tests\Fixtures;

use Arqel\Ai\AiCompletionResult;
use Arqel\Ai\Contracts\AiProvider;
use Arqel\Ai\Exceptions\AiException;

/**
 * In-memory provider used by the AiManager test suite. Records the
 * last call so assertions can verify that the manager forwarded the
 * prompt + options unchanged. `embed()` returns a deterministic
 * fixed-size vector so consumers can assert on length.
 */
class FakeProvider implements AiProvider
{
    public int $completeCalls = 0;

    public int $chatCalls = 0;

    public ?string $lastPrompt = null;

    /** @var array<int, array{role: string, content: string}>|null */
    public ?array $lastMessages = null;

    public function __construct(
        private readonly string $name = 'fake',
        private readonly float $costPerCall = 0.001,
    ) {}

    public function complete(string $prompt, array $options = []): AiCompletionResult
    {
        $this->completeCalls++;
        $this->lastPrompt = $prompt;

        return new AiCompletionResult('echo:'.$prompt, 5, 5, $this->costPerCall, 'fake-model', []);
    }

    public function chat(array $messages, array $options = []): AiCompletionResult
    {
        $this->chatCalls++;
        $this->lastMessages = $messages;

        return new AiCompletionResult('chat-reply', 5, 5, $this->costPerCall, 'fake-model', []);
    }

    public function embed(string $text): array
    {
        return [0.1, 0.2, 0.3];
    }

    public function stream(string $prompt, callable $onChunk, array $options = []): void
    {
        throw new AiException('stream not implemented in FakeProvider');
    }

    public function name(): string
    {
        return $this->name;
    }

    public function supportsEmbeddings(): bool
    {
        return true;
    }

    public function supportsStreaming(): bool
    {
        return false;
    }
}

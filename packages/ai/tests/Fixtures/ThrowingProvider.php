<?php

declare(strict_types=1);

namespace Arqel\Ai\Tests\Fixtures;

use Arqel\Ai\AiCompletionResult;
use Arqel\Ai\Contracts\AiProvider;
use Arqel\Ai\Exceptions\AiException;

/**
 * Provider que simula uma falha upstream (e.g. HTTP 503) lançando
 * `AiException` em `complete()`/`chat()`. Usado pelos testes de
 * controllers para validar que generate/classify/translate convertem
 * a exceção num 422 limpo em vez de propagar um 500 (issue #205).
 */
class ThrowingProvider implements AiProvider
{
    public function __construct(
        private readonly string $name = 'throwing',
        private readonly string $message = 'provider upstream 503',
    ) {}

    public function complete(string $prompt, array $options = []): AiCompletionResult
    {
        throw new AiException($this->message);
    }

    public function chat(array $messages, array $options = []): AiCompletionResult
    {
        throw new AiException($this->message);
    }

    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        throw new AiException($this->message);
    }

    public function stream(string $prompt, callable $onChunk, array $options = []): void
    {
        throw new AiException($this->message);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function supportsEmbeddings(): bool
    {
        return false;
    }

    public function supportsStreaming(): bool
    {
        return false;
    }

    public function supportsVision(): bool
    {
        return false;
    }
}

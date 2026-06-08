<?php

declare(strict_types=1);

namespace Arqel\Ai\Tests\Fixtures;

use Arqel\Ai\AiCompletionResult;
use Arqel\Ai\Contracts\AiProvider;
use Arqel\Ai\Exceptions\AiException;

/**
 * Provider que lança uma `AiException` específica (tipicamente uma
 * subclasse user-facing como `DailyLimitExceeded`/`UserLimitExceeded`)
 * em cada chamada. Usado pelos testes de controllers para validar que
 * as mensagens de limite framework-controlled são preservadas, enquanto
 * a `AiException` upstream genérica é sanitizada.
 */
class LimitThrowingProvider implements AiProvider
{
    public function __construct(
        private readonly string $name,
        private readonly AiException $exception,
    ) {}

    public function complete(string $prompt, array $options = []): AiCompletionResult
    {
        throw $this->exception;
    }

    public function chat(array $messages, array $options = []): AiCompletionResult
    {
        throw $this->exception;
    }

    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        throw $this->exception;
    }

    public function stream(string $prompt, callable $onChunk, array $options = []): void
    {
        throw $this->exception;
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
        return true;
    }
}
